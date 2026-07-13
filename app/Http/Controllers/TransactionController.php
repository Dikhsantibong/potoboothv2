<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions.
     */
    public function index(Request $request): Response
    {
        $query = Transaction::with(['machine', 'template', 'finalImage'])->latest();

        if (auth()->check() && auth()->user()->role === 'mitra') {
            $query->whereIn('transactions.machine_id', function($q) {
                $q->select('id')->from('machines')->where('user_id', auth()->id());
            });
        }

        // Search by transaction_id or machine name
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('transaction_id', 'like', '%' . $request->search . '%')
                  ->orWhereHas('machine', function($mq) use ($request) {
                      $mq->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return Inertia::render('transactions/index', [
            'transactions' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Display the specified transaction detail.
     */
    public function show(Transaction $transaction): Response
    {
        if (auth()->check() && auth()->user()->role === 'mitra') {
            abort(403, 'Mitra is not allowed to view transaction details.');
        }

        $transaction->load(['machine', 'template', 'photos.frame', 'finalImage', 'voucher']);

        return Inertia::render('transactions/show', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Remove the specified transaction from storage.
     */
    public function destroy(Transaction $transaction)
    {
        $transaction->load(['photos', 'finalImage']);

        // Delete session photos
        foreach ($transaction->photos as $photo) {
            if ($photo->photo_path) {
                Storage::disk('public')->delete($photo->photo_path);
            }
        }

        // Delete final image and video
        if ($transaction->finalImage) {
            if ($transaction->finalImage->image_path) {
                Storage::disk('public')->delete($transaction->finalImage->image_path);
            }
            if ($transaction->finalImage->video_path) {
                Storage::disk('public')->delete($transaction->finalImage->video_path);
            }
        }

        $transaction->delete();

        return redirect()->route('transactions.index')
            ->with('message', 'Transaction deleted successfully.');
    }

    /**
     * Export laporan transaksi ke Excel.
     *
     * Angka pada laporan ini dihitung dengan aturan yang SAMA PERSIS dengan
     * dashboard (DashboardController::computeBreakdown):
     * - Voucher : transaksi COMPLETED yang memakai voucher (voucher_id terisi).
     * - QRIS    : transaksi COMPLETED payment_type "qris" TANPA voucher.
     * - Semua biaya cetak tambahan (termasuk pada sesi voucher) dibayar via
     *   QRIS sehingga masuk ke kolom pendapatan cetak QRIS.
     * - Untuk role mitra hanya bagian QRIS yang dihitung dan ditampilkan.
     */
    public function export(Request $request)
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');

        $startDate = $request->filled('start_date')
            ? \Carbon\Carbon::parse($request->query('start_date'), $timezone)->startOfDay()
            : now($timezone)->startOfDay();

        $endDate = $request->filled('end_date')
            ? \Carbon\Carbon::parse($request->query('end_date'), $timezone)->endOfDay()
            : now($timezone)->endOfDay();

        if ($endDate->lt($startDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $isMitra = auth()->check() && auth()->user()->role === 'mitra';

        // Mesin aktif mengikuti MachineScope (pilihan pada machine switcher),
        // supaya identitas pada laporan sesuai dengan data yang terfilter.
        $machineQuery = \App\Models\Machine::query()->with('user:id,name');
        if ($isMitra) {
            $machineQuery->where('user_id', auth()->id());
        }

        $activeMachine = null;
        if ($activeMachineId = session('active_machine_id')) {
            $activeMachine = (clone $machineQuery)->where('id', $activeMachineId)->first();
        }
        $activeMachine ??= (clone $machineQuery)->orderBy('name')->first();

        $machineName = $activeMachine?->name ?? 'Semua Mesin';
        $mitraName = $activeMachine?->user?->name
            ?? ($isMitra ? auth()->user()->name : null);

        $transactions = Transaction::forCurrentUser()
            ->with('finalImage')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'COMPLETED')
            ->orderBy('created_at')
            ->get();

        // Agregasi per tanggal dengan kategori yang saling eksklusif,
        // mengikuti aturan perhitungan dashboard.
        $days = [];
        foreach ($transactions as $t) {
            $isVoucher = $t->voucher_id !== null;
            $isQris = ! $isVoucher && strtolower((string) $t->payment_type) === 'qris';

            if (! $isVoucher && ! $isQris) {
                continue;
            }

            $dateKey = $t->created_at->format('Y-m-d');
            if (! isset($days[$dateKey])) {
                $days[$dateKey] = [
                    'qris_count' => 0,
                    'qris_base' => 0,
                    'qris_print' => 0,
                    'voucher_count' => 0,
                    'voucher_base' => 0,
                ];
            }

            $printAmount = ($t->finalImage && $t->finalImage->printed && $t->finalImage->amount_print)
                ? (int) $t->finalImage->amount_print
                : 0;

            if ($isVoucher) {
                $days[$dateKey]['voucher_count']++;
                $days[$dateKey]['voucher_base'] += (int) $t->amount;
                // Cetak tambahan pada sesi voucher dibayar via QRIS.
                $days[$dateKey]['qris_print'] += $printAmount;
            } else {
                $days[$dateKey]['qris_count']++;
                $days[$dateKey]['qris_base'] += (int) $t->amount;
                $days[$dateKey]['qris_print'] += $printAmount;
            }
        }
        ksort($days);

        $rupiah = fn (int $amount) => 'Rp ' . number_format($amount, 0, ',', '.');

        $slug = fn (?string $value) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $value), '_');

        $fileNameParts = array_filter([
            'Laporan',
            $isMitra ? 'QRIS' : 'Transaksi',
            $slug($mitraName),
            $slug($machineName),
            $startDate->format('Ymd') . '_sd_' . $endDate->format('Ymd'),
        ]);
        $fileName = implode('_', $fileNameParts) . '.xlsx';

        $columnCount = $isMitra ? 5 : 8;

        // Tulis workbook utuh ke file sementara lalu kirim sebagai download
        // biasa (dengan Content-Length). Streaming langsung via toBrowser()
        // mengirim respons tanpa Content-Length dan mengakhiri PHP dengan
        // exit; di macOS hal ini membuat Excel menganggap file masih dipakai
        // ("locked for editing") saat dibuka dari download bar.
        $tempPath = tempnam(sys_get_temp_dir(), 'laporan_export_');
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($tempPath, 'xlsx', function ($spoutWriter) use ($columnCount) {
            /** @var \OpenSpout\Writer\XLSX\Writer $spoutWriter */
            $options = $spoutWriter->getOptions();
            $options->setColumnWidth(24, 1);
            for ($col = 2; $col <= $columnCount; $col++) {
                $options->setColumnWidth(22, $col);
            }
        })->noHeaderRow();

        $bold = (new \OpenSpout\Common\Entity\Style\Style())->setFontBold();

        // --- Judul & info periode ---
        $writer->addRow([$isMitra ? 'LAPORAN TRANSAKSI QRIS - POTOPI PHOTOBOOTH' : 'LAPORAN TRANSAKSI - POTOPI PHOTOBOOTH'], $bold);
        if ($mitraName) {
            $writer->addRow(['Mitra', $mitraName]);
        }
        $writer->addRow(['Mesin', $machineName]);
        $writer->addRow(['Periode', $startDate->format('d/m/Y') . ' s/d ' . $endDate->format('d/m/Y')]);
        $writer->addRow(['Dibuat pada', now($timezone)->format('d/m/Y H:i')]);
        if (! $isMitra) {
            $writer->addRow(['Catatan', 'Semua biaya cetak tambahan (termasuk dari sesi voucher) dibayar via QRIS dan masuk kolom Pendapatan Cetak.']);
        }
        $writer->addRow(['']);

        // --- Header tabel ---
        $header = [
            'Tanggal',
            'Jumlah Transaksi QRIS',
            'Pendapatan Sesi QRIS',
            'Pendapatan Cetak (QRIS)',
            'Total QRIS',
        ];
        if (! $isMitra) {
            $header[] = 'Jumlah Transaksi Voucher';
            $header[] = 'Nilai Sesi Voucher';
            $header[] = 'Total Pendapatan';
        }
        $writer->addRow($header, $bold);

        // --- Baris data per tanggal ---
        $totals = [
            'qris_count' => 0,
            'qris_base' => 0,
            'qris_print' => 0,
            'voucher_count' => 0,
            'voucher_base' => 0,
        ];

        foreach ($days as $date => $d) {
            $qrisTotal = $d['qris_base'] + $d['qris_print'];

            foreach ($totals as $key => $value) {
                $totals[$key] += $d[$key];
            }

            $row = [
                \Carbon\Carbon::parse($date)->format('d/m/Y'),
                $d['qris_count'],
                $rupiah($d['qris_base']),
                $rupiah($d['qris_print']),
                $rupiah($qrisTotal),
            ];
            if (! $isMitra) {
                $row[] = $d['voucher_count'];
                $row[] = $rupiah($d['voucher_base']);
                $row[] = $rupiah($qrisTotal + $d['voucher_base']);
            }
            $writer->addRow($row);
        }

        if (empty($days)) {
            $writer->addRow(['Tidak ada transaksi pada periode ini.']);
        }

        // --- Baris total ---
        $totalQris = $totals['qris_base'] + $totals['qris_print'];

        $writer->addRow(['']);
        $totalRow = [
            'TOTAL',
            $totals['qris_count'],
            $rupiah($totals['qris_base']),
            $rupiah($totals['qris_print']),
            $rupiah($totalQris),
        ];
        if (! $isMitra) {
            $totalRow[] = $totals['voucher_count'];
            $totalRow[] = $rupiah($totals['voucher_base']);
            $totalRow[] = $rupiah($totalQris + $totals['voucher_base']);
        }
        $writer->addRow($totalRow, $bold);

        // --- Ringkasan (sama dengan kartu di dashboard) ---
        $writer->addRow(['']);
        $writer->addRow(['RINGKASAN PERIODE'], $bold);
        $writer->addRow(['Total Transaksi QRIS', $totals['qris_count']]);
        $writer->addRow(['Pendapatan Sesi QRIS', $rupiah($totals['qris_base'])]);
        $writer->addRow(['Pendapatan Cetak Tambahan (QRIS)', $rupiah($totals['qris_print'])]);
        $writer->addRow(['Total Pendapatan QRIS', $rupiah($totalQris)], $bold);
        if (! $isMitra) {
            $writer->addRow(['Total Transaksi Voucher', $totals['voucher_count']]);
            $writer->addRow(['Nilai Sesi Voucher', $rupiah($totals['voucher_base'])]);
            $writer->addRow(['Total Pendapatan Keseluruhan', $rupiah($totalQris + $totals['voucher_base'])], $bold);
        }

        $writer->close();

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
