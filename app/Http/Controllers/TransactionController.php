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

    public function export(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfDay());
        $endDate = $request->query('end_date', now()->endOfDay());

        $transactions = Transaction::with(['machine'])
            ->forCurrentUser()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'COMPLETED')
            ->whereRaw('LOWER(payment_type) = ?', ['qris'])
            ->get();

        $fileName = 'Laporan_QRIS_' . date('Ymd_His') . '.xlsx';

        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::streamDownload($fileName);

        foreach ($transactions as $t) {
            $writer->addRow([
                'ID Transaksi' => $t->transaction_id,
                'Mesin' => $t->machine ? $t->machine->name : '-',
                'Tipe Pembayaran' => strtoupper($t->payment_type),
                'Status' => $t->status,
                'Total (Rp)' => $t->amount,
                'Tanggal Transaksi' => $t->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        return $writer->toBrowser();
    }
}
