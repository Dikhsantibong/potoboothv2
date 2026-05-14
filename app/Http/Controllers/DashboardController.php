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

        $payload = Cache::remember(
            "dashboard:metrics:{$rangeStart->toDateString()}:{$rangeEnd->toDateString()}",
            now()->addSeconds($cacheTtlSeconds),
            function () use ($rangeStart, $rangeEnd, $previousRangeStart, $previousRangeEnd) {
                $successStatus = 'COMPLETED';

                $periodTransactions = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])->count();
                $previousPeriodTransactions = Transaction::whereBetween('created_at', [$previousRangeStart, $previousRangeEnd])->count();

                $periodRevenue = (int) $this->revenueQuery(
                    Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
                        ->where('status', $successStatus)
                );
                $previousPeriodRevenue = (int) $this->revenueQuery(
                    Transaction::whereBetween('created_at', [$previousRangeStart, $previousRangeEnd])
                        ->where('status', $successStatus)
                );

                $periodSessions = Transaction::whereBetween('started_at', [$rangeStart, $rangeEnd])->count();
                $periodVoucherUsage = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->whereNotNull('voucher_id')
                    ->count();
                $activeVoucherCount = Voucher::where('status', 'ready')->count();

                $stats = [
                    [
                        'title' => 'Transaksi Periode',
                        'value' => (string) $periodTransactions,
                        'change' => $this->formatChange($periodTransactions, $previousPeriodTransactions, 'vs periode sebelumnya'),
                        'icon' => 'credit-card',
                    ],
                    [
                        'title' => 'Pendapatan Periode',
                        'value' => 'Rp ' . number_format($periodRevenue, 0, ',', '.'),
                        'change' => $this->formatChange($periodRevenue, $previousPeriodRevenue, 'vs periode sebelumnya'),
                        'icon' => 'dollar-sign',
                    ],
                    [
                        'title' => 'Sesi Photo Booth',
                        'value' => (string) $periodSessions,
                        'change' => 'Berdasarkan started_at di periode terpilih',
                        'icon' => 'camera',
                    ],
                    [
                        'title' => 'Voucher Dipakai',
                        'value' => (string) $periodVoucherUsage,
                        'change' => $activeVoucherCount . ' voucher ready',
                        'icon' => 'ticket',
                    ],
                ];

                $recentActivities = Transaction::with(['machine:id,name', 'template:id,name'])
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

                $previousPeriodLabel = sprintf(
                    '%s - %s',
                    $previousRangeStart->translatedFormat('d M Y'),
                    $previousRangeEnd->translatedFormat('d M Y')
                );
                $selectedPeriodLabel = sprintf(
                    '%s - %s',
                    $rangeStart->translatedFormat('d M Y'),
                    $rangeEnd->translatedFormat('d M Y')
                );
                $successTransactions = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', $successStatus)
                    ->count();
                $successRate = $periodTransactions > 0
                    ? round(($successTransactions / $periodTransactions) * 100)
                    : 0;

                $totalRevenue = (int) $this->revenueQuery(
                    Transaction::where('status', $successStatus)
                );

                $revenueSummary = [
                    'today' => 'Rp ' . number_format($periodRevenue, 0, ',', '.'),
                    'yesterday' => 'Rp ' . number_format($previousPeriodRevenue, 0, ',', '.'),
                    'thisWeek' => (string) $periodTransactions,
                    'thisMonth' => $successRate . '%',
                    'total' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                    'periodLabel' => $selectedPeriodLabel,
                    'previousPeriodLabel' => $previousPeriodLabel,
                ];

                // --- QRIS & Voucher transaction breakdown ---
                $qrisQuery = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', $successStatus)
                    ->whereRaw('LOWER(payment_type) = ?', ['qris']);
                $qrisCount = (clone $qrisQuery)->count();
                $qrisBase = (int) (clone $qrisQuery)->sum('amount');
                $qrisPrint = $this->printRevenueOnly(clone $qrisQuery);

                $voucherQuery = Transaction::whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->where('status', $successStatus)
                    ->whereNotNull('voucher_id');
                $voucherCount = (clone $voucherQuery)->count();
                $voucherBase = (int) (clone $voucherQuery)->sum('amount');
                $voucherPrint = $this->printRevenueOnly(clone $voucherQuery);

                // Add ALL extra prints (including from voucher sessions) to QRIS
                $qrisTotal = $qrisBase + $qrisPrint + $voucherPrint;
                $voucherTotal = $voucherBase; // Voucher only gets base session

                // All-time accumulated
                $allQrisQuery = Transaction::where('status', $successStatus)
                    ->whereRaw('LOWER(payment_type) = ?', ['qris']);
                $allQrisCount = (clone $allQrisQuery)->count();
                $allQrisBase = (int) (clone $allQrisQuery)->sum('amount');
                $allQrisPrint = $this->printRevenueOnly(clone $allQrisQuery);

                $allVoucherQuery = Transaction::where('status', $successStatus)
                    ->whereNotNull('voucher_id');
                $allVoucherCount = (clone $allVoucherQuery)->count();
                $allVoucherBase = (int) (clone $allVoucherQuery)->sum('amount');
                $allVoucherPrint = $this->printRevenueOnly(clone $allVoucherQuery);

                $allQrisTotal = $allQrisBase + $allQrisPrint + $allVoucherPrint;
                $allVoucherTotal = $allVoucherBase;

                $transactionBreakdown = [
                    'qris' => [
                        'count' => $qrisCount,
                        'total' => 'Rp ' . number_format($qrisTotal, 0, ',', '.'),
                        'totalRaw' => $qrisTotal,
                    ],
                    'voucher' => [
                        'count' => $voucherCount,
                        'total' => 'Rp ' . number_format($voucherTotal, 0, ',', '.'),
                        'totalRaw' => $voucherTotal,
                    ],
                    'allTime' => [
                        'qris' => [
                            'count' => $allQrisCount,
                            'total' => 'Rp ' . number_format($allQrisTotal, 0, ',', '.'),
                        ],
                        'voucher' => [
                            'count' => $allVoucherCount,
                            'total' => 'Rp ' . number_format($allVoucherTotal, 0, ',', '.'),
                        ],
                    ],
                ];

                return [
                    'stats' => $stats,
                    'recentActivities' => $recentActivities,
                    'performanceTargets' => $this->buildPerformanceTargets($rangeStart, $rangeEnd),
                    'transactionChartData' => $this->buildRangeTransactionChart($rangeStart, $rangeEnd),
                    'revenueSummary' => $revenueSummary,
                    'transactionBreakdown' => $transactionBreakdown,
                ];
            }
        );

        return Inertia::render('dashboard', [
            'stats' => $payload['stats'],
            'recentActivities' => $payload['recentActivities'],
            'performanceTargets' => $payload['performanceTargets'],
            'transactionChartData' => $payload['transactionChartData'],
            'revenueSummary' => $payload['revenueSummary'],
            'transactionBreakdown' => $payload['transactionBreakdown'],
            'reportFilters' => [
                'startDate' => $rangeStart->toDateString(),
                'endDate' => $rangeEnd->toDateString(),
            ],
        ]);
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

    private function buildPerformanceTargets(Carbon $todayStart, Carbon $todayEnd): array
    {
        $transactionTarget = (int) config('dashboard.targets.transactions_per_day', 100);
        $revenueTarget = (int) config('dashboard.targets.revenue_per_day', 5000000);
        $uptimeTarget = (int) config('dashboard.targets.machine_uptime_percent', 95);

        $todayTransactionCount = Transaction::whereBetween('created_at', [$todayStart, $todayEnd])->count();
        $todayRevenue = (int) $this->revenueQuery(
            Transaction::whereBetween('created_at', [$todayStart, $todayEnd])
                ->where('status', 'COMPLETED')
        );
        $activeMachines = Machine::where('is_active', true)->count();
        $totalMachines = Machine::count();

        $transactionProgress = max(0, min(100, (int) round(($todayTransactionCount / max(1, $transactionTarget)) * 100)));
        $revenueProgress = max(0, min(100, (int) round(($todayRevenue / max(1, $revenueTarget)) * 100)));
        $uptimeProgress = $totalMachines > 0
            ? max(0, min(100, (int) round(($activeMachines / $totalMachines) * 100)))
            : $uptimeTarget;

        return [
            ['label' => 'Target Transaksi', 'value' => $transactionProgress],
            ['label' => 'Target Pendapatan', 'value' => $revenueProgress],
            ['label' => 'Uptime Mesin', 'value' => $uptimeProgress],
        ];
    }

    private function buildRangeTransactionChart(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $raw = Transaction::select(
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
     * Calculate total revenue (session amount + print costs) for a given query.
     *
     * Revenue = SUM(transactions.amount) + SUM(final_images.amount_print)
     *
     * Uses a subquery approach instead of JOIN to avoid column ambiguity
     * (both transactions and final_images have created_at columns).
     */
    private function revenueQuery($query): int
    {
        $baseAmount = (int) (clone $query)->sum('transactions.amount');
        $printRevenue = $this->printRevenueOnly($query);

        return $baseAmount + $printRevenue;
    }

    /**
     * Calculate only the print revenue for a given query.
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
