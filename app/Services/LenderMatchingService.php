<?php

namespace App\Services;

use App\Models\McaLenderGuideline;

class LenderMatchingService
{
    /**
     * Match lender guidelines against the merchant's financial profile.
     *
     * @param array $criteria {
     *   avg_monthly_deposits      float   — avg monthly deposit dollar total
     *   avg_monthly_true_revenue  float   — avg monthly true revenue
     *   avg_negative_days         float   — avg negative days per month
     *   avg_nsf_per_month         float   — avg NSF events per month
     *   active_mca_positions      int     — unique detected MCA lenders
     *   avg_daily_balance         float|null
     * }
     */
    public function match(array $criteria): array
    {
        $lenders = McaLenderGuideline::where('status', 'ACTIVE')
            ->orderBy('lender_name')
            ->get();

        $all = [];

        foreach ($lenders as $lender) {
            $checks   = $this->checkCriteria($lender, $criteria);
            $qualified = collect($checks)->every(fn($c) => $c['passed'] || $c['skipped']);

            $all[] = [
                'lender'       => $lender,
                'qualified'    => $qualified,
                'criteria'     => $checks,
                'fail_reasons' => collect($checks)
                    ->filter(fn($c) => !$c['passed'] && !$c['skipped'])
                    ->pluck('reason')
                    ->values()
                    ->all(),
            ];
        }

        $qualified = array_values(array_filter($all, fn($r) => $r['qualified']));

        return [
            'qualified'     => $qualified,
            'all'           => $all,
            'criteria_used' => $criteria,
            'counts'        => [
                'qualified' => count($qualified),
                'total'     => count($all),
            ],
        ];
    }

    private function checkCriteria(McaLenderGuideline $lender, array $criteria): array
    {
        $checks = [];

        // ── Min monthly deposits ───────────────────────────────────────────────
        if ($lender->min_monthly_deposits !== null) {
            $actual  = $criteria['avg_monthly_deposits'];
            $passed  = $actual >= (float) $lender->min_monthly_deposits;
            $checks[] = [
                'name'     => 'Monthly Deposits',
                'required' => '$' . number_format($lender->min_monthly_deposits, 0),
                'actual'   => '$' . number_format($actual, 0),
                'passed'   => $passed,
                'skipped'  => false,
                'reason'   => $passed ? null
                    : 'Monthly deposits ($' . number_format($actual, 0) . ') below minimum ($' . number_format($lender->min_monthly_deposits, 0) . ')',
            ];
        }

        // ── Max negative days per month ────────────────────────────────────────
        if ($lender->max_negative_days !== null) {
            $actual  = round($criteria['avg_negative_days'], 1);
            $passed  = $actual <= (int) $lender->max_negative_days;
            $checks[] = [
                'name'     => 'Negative Days',
                'required' => '≤ ' . $lender->max_negative_days . '/mo',
                'actual'   => $actual . '/mo',
                'passed'   => $passed,
                'skipped'  => false,
                'reason'   => $passed ? null
                    : 'Negative days (' . $actual . '/mo) exceeds maximum (' . $lender->max_negative_days . '/mo)',
            ];
        }

        // ── Max NSFs per month ─────────────────────────────────────────────────
        if ($lender->max_nsfs !== null) {
            $actual  = round($criteria['avg_nsf_per_month'], 1);
            $passed  = $actual <= (int) $lender->max_nsfs;
            $checks[] = [
                'name'     => 'NSF Count',
                'required' => '≤ ' . $lender->max_nsfs . '/mo',
                'actual'   => $actual . '/mo',
                'passed'   => $passed,
                'skipped'  => false,
                'reason'   => $passed ? null
                    : 'NSF count (' . $actual . '/mo) exceeds maximum (' . $lender->max_nsfs . '/mo)',
            ];
        }

        // ── Max open MCA positions ─────────────────────────────────────────────
        if ($lender->max_positions !== null) {
            $actual  = (int) $criteria['active_mca_positions'];
            $passed  = $actual <= (int) $lender->max_positions;
            $checks[] = [
                'name'     => 'MCA Positions',
                'required' => '≤ ' . $lender->max_positions,
                'actual'   => (string) $actual,
                'passed'   => $passed,
                'skipped'  => false,
                'reason'   => $passed ? null
                    : 'Active MCA positions (' . $actual . ') exceeds maximum (' . $lender->max_positions . ')',
            ];
        }

        // ── Min average daily balance ──────────────────────────────────────────
        if ($lender->min_avg_daily_balance !== null) {
            if ($criteria['avg_daily_balance'] === null) {
                // Balance data not available — skip gracefully
                $checks[] = [
                    'name'     => 'Avg Daily Balance',
                    'required' => '$' . number_format($lender->min_avg_daily_balance, 0),
                    'actual'   => 'N/A',
                    'passed'   => true,
                    'skipped'  => true,
                    'reason'   => null,
                ];
            } else {
                $actual  = $criteria['avg_daily_balance'];
                $passed  = $actual >= (float) $lender->min_avg_daily_balance;
                $checks[] = [
                    'name'     => 'Avg Daily Balance',
                    'required' => '$' . number_format($lender->min_avg_daily_balance, 0),
                    'actual'   => '$' . number_format($actual, 0),
                    'passed'   => $passed,
                    'skipped'  => false,
                    'reason'   => $passed ? null
                        : 'Avg daily balance ($' . number_format($actual, 0) . ') below minimum ($' . number_format($lender->min_avg_daily_balance, 0) . ')',
                ];
            }
        }

        return $checks;
    }
}
