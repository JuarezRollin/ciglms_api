<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\GuaranteeLetter;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(private ComplianceAccess $complianceAccess) {}

    public function getDashboardData(User $user): array
    {
        $statusOrder = [
            'For Review by CRD',
            'Approved',
            'Released',
            'On Hold',
            'Cancelled by CRD',
            'For Endorsement',
            'Awaiting SOA',
            'For Pullout',
            'For Creation',
            'For Compliance',
            'For Review by RMCD',
            'On Process',
            'For Obligation',
            'For Pre-Audit',
            'For Crediting',
            'Paid',
            'Cancelled by RMCD',
        ];

        $statusSummary = $this->getStatusSummary();
        $statusTotals = $statusSummary->keyBy('label');

        $statusTotal = fn (string $name): int => (int) ($statusTotals->get($name)['total'] ?? 0);
        $statusAmount = fn (string $name): float => (float) ($statusTotals->get($name)['amount'] ?? 0);

        $complianceCounts = $this->complianceAccess->openCountsFor($user);
        $reviewVoucherCount = Batch::query()->atStatus('For Review by RMCD')->count();
        $outgoingVoucherCounts = $this->getOutgoingVoucherCounts($user);
        $totalGlCount = GuaranteeLetter::query()->count();
        $totalGlAmount = (float) GuaranteeLetter::query()->sum('gl_amount');
        $monthlyTrend = $this->getMonthlyTrend();

        return [
            'user' => [
                'name' => $user->name,
            ],
            'compliance_open_total' => $complianceCounts['gl'] + $complianceCounts['voucher'],
            'review_voucher_count' => $reviewVoucherCount,
            'outgoing_voucher_counts' => $outgoingVoucherCounts,
            'stats' => [
                [
                    'label' => 'Total GLs',
                    'value' => $totalGlCount,
                    'amount' => $totalGlAmount,
                    'icon' => 'pi-file',
                ],
                [
                    'label' => 'Awaiting SOA',
                    'value' => $statusTotal('Awaiting SOA'),
                    'amount' => null,
                    'icon' => 'pi-clock',
                ],
                [
                    'label' => 'On Process',
                    'value' => $statusTotal('On Process'),
                    'amount' => null,
                    'icon' => 'pi-sync',
                ],
                [
                    'label' => 'For Compliance',
                    'value' => $statusTotal('For Compliance'),
                    'amount' => null,
                    'icon' => 'pi-exclamation-circle',
                ],
                [
                    'label' => 'Paid',
                    'value' => $statusTotal('Paid'),
                    'amount' => $statusAmount('Paid'),
                    'icon' => 'pi-check-circle',
                ],
            ],
            'summary' => [
                'total_amount' => $totalGlAmount,
                'previous_amount' => (float) $monthlyTrend->slice(0, now()->month - 1)->sum('amount'),
                'progress' => $totalGlCount > 0
                    ? round(($statusTotal('Paid') / $totalGlCount) * 100, 1)
                    : 0,
            ],
            'monthly_assistance_trend' => $monthlyTrend,
            'processing_status' => $statusSummary
                ->sortBy(function ($status) use ($statusOrder): int {
                    $index = array_search($status['label'], $statusOrder, true);

                    return $index === false ? PHP_INT_MAX : $index;
                })
                ->values(),
            'assistance_types' => $this->getAssistanceTypes(),
            'pending_actions' => [
                "{$statusTotal('Awaiting SOA')} GLs awaiting SOA",
                "{$statusTotal('For Pullout')} GLs ready for pullout",
                "{$statusTotal('For Compliance')} vouchers for compliance",
                "{$reviewVoucherCount} vouchers awaiting review",
                "{$statusTotal('Paid')} paid notices to review",
            ],
        ];
    }

    private function getStatusSummary()
    {
        return GuaranteeLetter::query()
            ->join('statuses', 'statuses.id', '=', 'guarantee_letters.status_id')
            ->selectRaw('statuses.name as label, COUNT(*) as total, COALESCE(SUM(guarantee_letters.gl_amount), 0) as amount')
            ->groupBy('statuses.id', 'statuses.name')
            ->get()
            ->map(fn ($status) => [
                'label' => $status->label,
                'total' => (int) $status->total,
                'amount' => (float) $status->amount,
            ]);
    }

    /** @return array<string, int> */
    private function getOutgoingVoucherCounts(User $user): array
    {
        $statuses = [
            'for_obligation' => 'For Obligation',
            'for_pre_audit' => 'For Pre-Audit',
            'for_crediting' => 'For Crediting',
            'paid' => 'Paid',
        ];

        $counts = [
            'box_a_review' => Batch::query()->atStatus('On Process')->whereDoesntHave('gls', fn ($query) => $query->whereNotNull('box_a_reviewed_by'))->count(),
            'on_process' => Batch::query()->atStatus('On Process')->whereDoesntHave('gls', fn ($query) => $query->whereNull('box_a_reviewed_by'))->count(),
        ];

        foreach ($statuses as $key => $status) {
            $counts[$key] = Batch::query()->atStatus($status)->count();
        }

        if (! $user->hasAnyRole(['admin', 'reviewer'])) {
            if ($user->hasRole('box a reviewer')) {
                $counts['on_process'] = 0;
            } elseif ($user->hasRole('outgoing')) {
                $counts['box_a_review'] = 0;
            } else {
                $counts['box_a_review'] = 0;
                $counts['on_process'] = 0;
            }
        }

        return $counts;
    }

    private function getMonthlyTrend()
    {
        $currentYear = now()->year;

        $monthlyAmounts = GuaranteeLetter::query()
            ->selectRaw('MONTH(gl_date) as month_number, COALESCE(SUM(gl_amount), 0) as amount')
            ->whereYear('gl_date', $currentYear)
            ->groupByRaw('MONTH(gl_date)')
            ->pluck('amount', 'month_number');

        return collect(range(1, 12))
            ->map(fn ($month) => [
                'month' => Carbon::create($currentYear, $month, 1)->format('M'),
                'amount' => (float) ($monthlyAmounts[$month] ?? 0),
            ])
            ->values();
    }

    private function getAssistanceTypes()
    {
        return GuaranteeLetter::query()
            ->join('assistance_types', 'assistance_types.id', '=', 'guarantee_letters.assistance_type_id')
            ->selectRaw('assistance_types.name as label, COUNT(*) as total, COALESCE(SUM(guarantee_letters.gl_amount), 0) as amount')
            ->groupBy('assistance_types.id', 'assistance_types.name')
            ->orderBy('assistance_types.name')
            ->get()
            ->map(fn ($type) => [
                'label' => $type->label,
                'total' => (int) $type->total,
                'amount' => (float) $type->amount,
            ]);
    }
}
