<?php

namespace App\Http\Controllers;

use App\Models\FinalImage;
use App\Models\Machine;
use App\Models\Transaction;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const SUCCESS_STATUS = 'COMPLETED';

    public function __invoke(Request $request): Response
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $cacheTtlSeconds = (int) config('dashboard.cache_ttl_seconds', 120);

        $now = Carbon::now($timezone);
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');

        $rangeStart = $startDateInput
            ? Carbon::parse($startDateInput, $timezone)->startOfDay()
            : $now->copy()->startOfDay();
        $rangeEnd = $endDateInput
            ? Carbon::parse($endDateInput, $timezone)->endOfDay()
            : $now->copy()->endOfDay();

        if ($rangeEnd->lt($rangeStart)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $rangeDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
        $previousRangeStart = $rangeStart->copy()->subDays($rangeDays);
        $previousRangeEnd = $rangeStart->copy()->subSecond();

        $isMitra = auth()->check() && auth()->user()->role === 'mitra';
        $activeMachineId = session('active_machine_id') ?? Machine::forCurrentUser()->first()?->id ?? 'none';
        $userRole = $isMitra ? 'mitra:' . auth()->id() : 'admin';

        $payload = Cache::remember(
            "dashboard:metrics:{$rangeStart->toDateString()}:{$rangeEnd->toDateString()}:machine:{$activeMachineId}:role:{$userRole}",
            now()->addSeconds($cacheTtlSeconds),
            function () use ($rangeStart, $rangeEnd, $previousRangeStart, $previousRangeEnd, $isMitra) {
                $period = $this->computeBreakdown($rangeStart, $rangeEnd);
                $previous = $this->computeBreakdown($previousRangeStart, $previousRangeEnd);
                $allTime = $this->computeBreakdown(null, null);

                // Mitra hanya berbagi hasil dari uang QRIS; admin melihat semuanya.
                $periodRevenue = $isMitra ? $period['qrisTotal'] : $period['grandTotal'];
                $previousRevenue = $isMitra ? $previous['qrisTotal'] : $previous['grandTotal'];
                $allTimeRevenue = $isMitra ? $allTime['qrisTotal'] : $allTime['grandTotal'];

                $periodTransactions = $this->activityQuery($isMitra)
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->count();
                $previousPeriodTransactions = $this->activityQuery($isMitra)
                    ->whereBetween('created_at', [$previousRangeStart, $previousRangeEnd])
                    ->count();
                $successTransactions = $this->activityQuery($isMitra)
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', self::SUCCESS_STATUS)
                    ->count();
                $successRate = $periodTransactions > 0
                    ? round(($successTransactions / $periodTransactions) * 100)
                    : 0;

                $periodSessions = Transaction::forCurrentUser()->whereBetween('started_at', [$rangeStart, $rangeEnd])->count();

                $stats = [
                    [
                        'title' => $isMitra ? 'Transaksi QRIS (Periode)' : 'Transaksi Periode',
                        'value' => (string) $periodTransactions,
                        'change' => $this->formatChange($periodTransactions, $previousPeriodTransactions, 'vs periode sebelumnya'),
                        'icon' => 'credit-card',
                    ],
                    [
                        'title' => $isMitra ? 'Pendapatan QRIS (Periode)' : 'Pendapatan Periode',
                        'value' => $this->formatRupiah($periodRevenue),
                        'change' => $this->formatChange($periodRevenue, $previousRevenue, 'vs periode sebelumnya'),
                        'icon' => 'dollar-sign',
                    ],
                    [
                        'title' => 'Sesi Photo Booth',
                        'value' => (string) $periodSessions,
                        'change' => 'Sesi yang dimulai pada periode terpilih',
                        'icon' => 'camera',
                    ],
                ];

                if (! $isMitra) {
                    $activeVoucherCount = Voucher::where('status', 'ready')->count();
                    $stats[] = [
                        'title' => 'Voucher Dipakai',
                        'value' => (string) $period['voucherCount'],
                        'change' => $activeVoucherCount . ' voucher ready',
                        'icon' => 'ticket',
                    ];
                }

                $recentActivities = $this->activityQuery($isMitra)
                    ->with(['machine:id,name', 'template:id,name'])
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->latest()
                    ->limit(4)
                    ->get()
                    ->map(function (Transaction $transaction) {
                        $machineName = $transaction->machine?->name ?? 'Unknown Machine';
                        $templateName = $transaction->template?->name ?? 'Tanpa Template';
                        $status = strtoupper((string) $transaction->status);

                        return [
                            'id' => $transaction->id,
                            'title' => "Transaksi {$transaction->transaction_id} {$status} di {$machineName} ({$templateName})",
                            'time' => $transaction->created_at?->diffForHumans() ?? '-',
                        ];
                    })
                    ->values()
                    ->all();

                $revenueSummary = [
                    'periodRevenue' => $this->formatRupiah($periodRevenue),
                    'previousPeriodRevenue' => $this->formatRupiah($previousRevenue),
                    'transactionCount' => (string) $periodTransactions,
                    'successRate' => $successRate . '%',
                    'allTimeRevenue' => $this->formatRupiah($allTimeRevenue),
                    'periodLabel' => sprintf(
                        '%s - %s',
                        $rangeStart->translatedFormat('d M Y'),
                        $rangeEnd->translatedFormat('d M Y')
                    ),
                    'previousPeriodLabel' => sprintf(
                        '%s - %s',
                        $previousRangeStart->translatedFormat('d M Y'),
                        $previousRangeEnd->translatedFormat('d M Y')
                    ),
                    'note' => $isMitra
                        ? 'Pendapatan = pembayaran sesi QRIS + cetak tambahan (dibayar via QRIS).'
                        : 'Pendapatan = Total QRIS (sesi + semua cetak tambahan) + nilai sesi voucher.',
                ];

                $transactionBreakdown = [
                    'qris' => [
                        'count' => $period['qrisCount'],
                        'base' => $this->formatRupiah($period['qrisBase']),
                        'print' => $this->formatRupiah($period['qrisPrint']),
                        'total' => $this->formatRupiah($period['qrisTotal']),
                    ],
                    'voucher' => $isMitra ? null : [
                        'count' => $period['voucherCount'],
                        'total' => $this->formatRupiah($period['voucherTotal']),
                    ],
                    'allTime' => [
                        'qris' => [
                            'count' => $allTime['qrisCount'],
                            'base' => $this->formatRupiah($allTime['qrisBase']),
                            'print' => $this->formatRupiah($allTime['qrisPrint']),
                            'total' => $this->formatRupiah($allTime['qrisTotal']),
                        ],
                        'voucher' => $isMitra ? null : [
                            'count' => $allTime['voucherCount'],
                            'total' => $this->formatRupiah($allTime['voucherTotal']),
                        ],
                    ],
                ];

                return [
                    'stats' => $stats,
                    'recentActivities' => $recentActivities,
                    'performanceTargets' => $this->buildPerformanceTargets($periodTransactions, $periodRevenue),
                    'transactionChartData' => $this->buildRangeTransactionChart(now()->subDays(6)->startOfDay(), now()->endOfDay(), $isMitra),
                    'revenueSummary' => $revenueSummary,
                    'transactionBreakdown' => $transactionBreakdown,
                ];
            }
        );

        // Fetch machines paper data outside of cache so it updates instantly
        $machinesPaper = Machine::forCurrentUser()->where('is_active', true)->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->name,
                'remaining' => $m->paper_condition['remaining'],
                'percentage' => $m->paper_condition['percentage'],
                'indicator' => $m->paper_condition['indicator']
            ];
        })->values()->all();

        return Inertia::render('dashboard', [
            'stats' => $payload['stats'],
            'recentActivities' => $payload['recentActivities'],
            'performanceTargets' => $payload['performanceTargets'],
            'transactionChartData' => $payload['transactionChartData'],
            'revenueSummary' => $payload['revenueSummary'],
            'transactionBreakdown' => $payload['transactionBreakdown'],
            'machinesPaper' => $machinesPaper,
            'reportFilters' => [
                'startDate' => $rangeStart->toDateString(),
                'endDate' => $rangeEnd->toDateString(),
            ],
        ]);
    }

    /**
     * Hitung rincian pendapatan QRIS & voucher untuk satu rentang tanggal.
     *
     * Kategori dibuat saling eksklusif supaya tidak ada transaksi yang
     * terhitung dua kali:
     * - Voucher : transaksi COMPLETED yang memakai voucher (voucher_id terisi).
     * - QRIS    : transaksi COMPLETED dengan payment_type "qris" TANPA voucher.
     *
     * Semua biaya cetak tambahan (final_images.amount_print, printed = true)
     * dibayar lewat QRIS — termasuk cetak tambahan pada sesi voucher — sehingga
     * seluruh nilai cetak masuk ke bucket QRIS.
     *
     * @return array{qrisCount:int,qrisBase:int,qrisPrint:int,qrisTotal:int,voucherCount:int,voucherBase:int,voucherTotal:int,grandTotal:int}
     */
    private function computeBreakdown(?Carbon $start, ?Carbon $end): array
    {
        $completed = function () use ($start, $end) {
            $query = Transaction::forCurrentUser()->where('status', self::SUCCESS_STATUS);

            if ($start && $end) {
                $query->whereBetween('created_at', [$start, $end]);
            }

            return $query;
        };

        $qrisQuery = $this->qrisOnly($completed());
        $voucherQuery = $completed()->whereNotNull('voucher_id');

        $qrisCount = (clone $qrisQuery)->count();
        $qrisBase = (int) (clone $qrisQuery)->sum('amount');
        $qrisOwnPrint = $this->printRevenueOnly($qrisQuery);

        $voucherCount = (clone $voucherQuery)->count();
        $voucherBase = (int) (clone $voucherQuery)->sum('amount');
        $voucherPrint = $this->printRevenueOnly($voucherQuery);

        $qrisPrint = $qrisOwnPrint + $voucherPrint;
        $qrisTotal = $qrisBase + $qrisPrint;
        $voucherTotal = $voucherBase;

        return [
            'qrisCount' => $qrisCount,
            'qrisBase' => $qrisBase,
            'qrisPrint' => $qrisPrint,
            'qrisTotal' => $qrisTotal,
            'voucherCount' => $voucherCount,
            'voucherBase' => $voucherBase,
            'voucherTotal' => $voucherTotal,
            'grandTotal' => $qrisTotal + $voucherTotal,
        ];
    }

    /**
     * Query dasar untuk metrik aktivitas (semua status).
     * Mitra hanya melihat aktivitas transaksi QRIS.
     */
    private function activityQuery(bool $isMitra)
    {
        $query = Transaction::forCurrentUser();

        return $isMitra ? $this->qrisOnly($query) : $query;
    }

    private function qrisOnly($query)
    {
        return $query
            ->whereRaw('LOWER(payment_type) = ?', ['qris'])
            ->whereNull('voucher_id');
    }

    private function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function formatChange(int $today, int $yesterday, string $suffix): string
    {
        if ($yesterday === 0) {
            if ($today === 0) {
                return '0% ' . $suffix;
            }

            return '+100% ' . $suffix;
        }

        $percent = (($today - $yesterday) / $yesterday) * 100;
        $rounded = round($percent);
        $sign = $rounded > 0 ? '+' : '';

        return "{$sign}{$rounded}% {$suffix}";
    }

    private function buildPerformanceTargets(int $periodTransactionCount, int $periodRevenue): array
    {
        $transactionTarget = (int) config('dashboard.targets.transactions_per_day', 100);
        $revenueTarget = (int) config('dashboard.targets.revenue_per_day', 5000000);
        $uptimeTarget = (int) config('dashboard.targets.machine_uptime_percent', 95);

        $activeMachines = Machine::forCurrentUser()->where('is_active', true)->count();
        $totalMachines = Machine::forCurrentUser()->count();

        $transactionProgress = max(0, min(100, (int) round(($periodTransactionCount / max(1, $transactionTarget)) * 100)));
        $revenueProgress = max(0, min(100, (int) round(($periodRevenue / max(1, $revenueTarget)) * 100)));
        $uptimeProgress = $totalMachines > 0
            ? max(0, min(100, (int) round(($activeMachines / $totalMachines) * 100)))
            : $uptimeTarget;

        return [
            ['label' => 'Target Transaksi', 'value' => $transactionProgress],
            ['label' => 'Target Pendapatan', 'value' => $revenueProgress],
            ['label' => 'Uptime Mesin', 'value' => $uptimeProgress],
        ];
    }

    private function buildRangeTransactionChart(\Carbon\CarbonInterface $rangeStart, \Carbon\CarbonInterface $rangeEnd, bool $isMitra): array
    {
        $raw = $this->activityQuery($isMitra)->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total')
        )
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'date');

        $chart = [];
        $totalDays = max(1, $rangeStart->diffInDays($rangeEnd) + 1);
        for ($i = 0; $i < $totalDays; $i++) {
            $date = $rangeStart->copy()->addDays($i);
            $dateKey = $date->toDateString();

            $chart[] = [
                'day' => $date->translatedFormat('d M'),
                'total' => (int) ($raw[$dateKey] ?? 0),
            ];
        }

        return $chart;
    }

    /**
     * Total pendapatan cetak (final_images.amount_print) untuk transaksi
     * pada query yang diberikan. Hanya cetakan yang benar-benar dicetak
     * (printed = true) yang dihitung.
     */
    private function printRevenueOnly($query): int
    {
        $transactionIds = (clone $query)->pluck('transactions.id');

        if ($transactionIds->isEmpty()) {
            return 0;
        }

        return (int) FinalImage::whereIn('transaction_id', $transactionIds)
            ->whereNotNull('amount_print')
            ->where('printed', true)
            ->selectRaw('SUM(amount_print) as print_total')
            ->value('print_total');
    }
}
