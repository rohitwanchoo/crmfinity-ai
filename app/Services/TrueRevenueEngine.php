<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * TrueRevenueEngine - Accurate bank statement revenue classification
 *
 * This service provides deterministic classification of bank transactions
 * to calculate True Revenue for MCA underwriting. It uses pattern-based
 * rules to correctly identify:
 *
 * - Business revenue (card settlements, customer payments, etc.)
 * - Non-revenue items (MCA funding, owner injections, transfers, etc.)
 *
 * Key Features:
 * - Deterministic rules checked before any AI suggestions
 * - Comprehensive MCA funder detection (40+ funders)
 * - Owner injection and tax refund exclusion
 * - Inter-account transfer detection
 * - Audit trail for every classification decision
 * - Monthly rollups with business day calculations
 */
class TrueRevenueEngine
{
    // Classification categories
    public const CLASSIFICATION_REVENUE = 'revenue';
    public const CLASSIFICATION_EXCLUDED = 'excluded';
    public const CLASSIFICATION_NEEDS_REVIEW = 'needs_review';

    // Classification source
    public const SOURCE_RULE = 'rule';
    public const SOURCE_AI = 'ai';
    public const SOURCE_MANUAL = 'manual';

    protected array $config;
    protected array $revenuePatterns;
    protected array $excludePatterns;
    protected array $industryPatterns;
    protected array $heuristics;

    public function __construct()
    {
        $this->config = config('revenue_classification', []);
        $this->revenuePatterns = $this->config['revenue_patterns'] ?? [];
        $this->excludePatterns = $this->config['exclude_patterns'] ?? [];
        $this->industryPatterns = $this->config['industry_patterns'] ?? [];
        $this->heuristics = $this->config['heuristics'] ?? [];
    }

    /**
     * Classify a single credit transaction for True Revenue calculation
     *
     * @param array $transaction Transaction data with 'description', 'amount', 'type', 'date'
     * @param string|null $industry Optional industry for industry-specific patterns
     * @return array Classification result with 'classification', 'reason', 'source', 'confidence'
     */
    public function classify(array $transaction, ?string $industry = null): array
    {
        $description = $transaction['description'] ?? '';
        $amount = (float) ($transaction['amount'] ?? 0);
        $type = $transaction['type'] ?? '';

        // Only classify credits - debits are never revenue
        if ($type !== 'credit') {
            return [
                'classification' => null,
                'reason' => 'Not a credit transaction',
                'source' => self::SOURCE_RULE,
                'confidence' => 1.0,
                'matched_pattern' => null,
            ];
        }

        // Step 1: Check EXCLUDE patterns FIRST (higher priority)
        // This ensures MCA funding is never counted as revenue
        foreach ($this->excludePatterns as $pattern => $reason) {
            if (@preg_match($pattern, $description)) {
                return [
                    'classification' => self::CLASSIFICATION_EXCLUDED,
                    'reason' => $reason,
                    'source' => self::SOURCE_RULE,
                    'confidence' => 0.95,
                    'matched_pattern' => $pattern,
                ];
            }
        }

        // Step 2: Check REVENUE patterns
        foreach ($this->revenuePatterns as $pattern => $reason) {
            if (@preg_match($pattern, $description)) {
                return [
                    'classification' => self::CLASSIFICATION_REVENUE,
                    'reason' => $reason,
                    'source' => self::SOURCE_RULE,
                    'confidence' => 0.95,
                    'matched_pattern' => $pattern,
                ];
            }
        }

        // Step 3: Check industry-specific patterns
        if ($industry && isset($this->industryPatterns[$industry])) {
            foreach ($this->industryPatterns[$industry] as $pattern => $reason) {
                if (@preg_match($pattern, $description)) {
                    return [
                        'classification' => self::CLASSIFICATION_REVENUE,
                        'reason' => $reason . ' (industry-specific)',
                        'source' => self::SOURCE_RULE,
                        'confidence' => 0.90,
                        'matched_pattern' => $pattern,
                    ];
                }
            }
        }

        // Step 4: Apply heuristics for unmatched items
        return $this->applyHeuristics($transaction);
    }

    /**
     * Apply heuristics for transactions that don't match any pattern
     */
    protected function applyHeuristics(array $transaction): array
    {
        $description = strtoupper($transaction['description'] ?? '');
        $amount = (float) ($transaction['amount'] ?? 0);

        $largeThreshold = $this->heuristics['large_deposit_threshold'] ?? 50000;
        $roundThreshold = $this->heuristics['round_number_threshold'] ?? 1000;
        $suspiciousAmounts = $this->heuristics['suspicious_loan_amounts'] ?? [];
        $defaultConfidence = $this->heuristics['default_confidence'] ?? 0.5;

        // Large deposits often need review (could be loans, investments, etc.)
        if ($amount >= $largeThreshold) {
            return [
                'classification' => self::CLASSIFICATION_NEEDS_REVIEW,
                'reason' => 'Large deposit (>$' . number_format($largeThreshold) . ') requires manual review',
                'source' => self::SOURCE_RULE,
                'confidence' => 0.5,
                'matched_pattern' => null,
            ];
        }

        // Check for suspicious loan amounts (exact common loan values)
        if (in_array($amount, $suspiciousAmounts)) {
            return [
                'classification' => self::CLASSIFICATION_NEEDS_REVIEW,
                'reason' => 'Deposit matches common loan amount - may be funding',
                'source' => self::SOURCE_RULE,
                'confidence' => 0.6,
                'matched_pattern' => null,
            ];
        }

        // Round numbers (exact thousands) may be transfers or loans
        if ($amount >= $roundThreshold && fmod($amount, 1000) == 0) {
            // Check if it's a very round number like 5000, 10000, etc.
            if ($amount >= 5000 && fmod($amount, 5000) == 0) {
                return [
                    'classification' => self::CLASSIFICATION_NEEDS_REVIEW,
                    'reason' => 'Round number deposit - may be transfer, loan, or capital injection',
                    'source' => self::SOURCE_RULE,
                    'confidence' => 0.6,
                    'matched_pattern' => null,
                ];
            }
        }

        // Default: Treat as likely revenue but with low confidence
        // This ensures conservative revenue estimates
        return [
            'classification' => self::CLASSIFICATION_REVENUE,
            'reason' => 'Default classification - no pattern match',
            'source' => self::SOURCE_RULE,
            'confidence' => $defaultConfidence,
            'matched_pattern' => null,
        ];
    }

    /**
     * Calculate True Revenue from a list of transactions
     *
     * @param array $transactions Array of transaction data
     * @param string|null $industry Optional industry for industry-specific patterns
     * @return array Summary with true_revenue, excluded_amount, needs_review_amount, etc.
     */
    public function calculateTrueRevenue(array $transactions, ?string $industry = null): array
    {
        $trueRevenue = 0;
        $excludedAmount = 0;
        $needsReviewAmount = 0;
        $classified = [];

        $revenueCount = 0;
        $excludedCount = 0;
        $needsReviewCount = 0;

        foreach ($transactions as $txn) {
            // Skip non-credit transactions
            if (($txn['type'] ?? '') !== 'credit') {
                continue;
            }

            $result = $this->classify($txn, $industry);
            $amount = (float) ($txn['amount'] ?? 0);

            // Store classification for audit trail
            $classifiedTxn = array_merge($txn, [
                'classification_result' => $result,
            ]);
            $classified[] = $classifiedTxn;

            switch ($result['classification']) {
                case self::CLASSIFICATION_REVENUE:
                    $trueRevenue += $amount;
                    $revenueCount++;
                    break;
                case self::CLASSIFICATION_EXCLUDED:
                    $excludedAmount += $amount;
                    $excludedCount++;
                    break;
                case self::CLASSIFICATION_NEEDS_REVIEW:
                    $needsReviewAmount += $amount;
                    $needsReviewCount++;
                    break;
            }
        }

        $totalCredits = $trueRevenue + $excludedAmount + $needsReviewAmount;
        $revenueRatio = $totalCredits > 0
            ? round(($trueRevenue / $totalCredits) * 100, 2)
            : 0;

        return [
            'true_revenue' => round($trueRevenue, 2),
            'excluded_amount' => round($excludedAmount, 2),
            'needs_review_amount' => round($needsReviewAmount, 2),
            'total_credits' => round($totalCredits, 2),
            'revenue_ratio' => $revenueRatio,
            'counts' => [
                'revenue' => $revenueCount,
                'excluded' => $excludedCount,
                'needs_review' => $needsReviewCount,
                'total' => $revenueCount + $excludedCount + $needsReviewCount,
            ],
            'classified_transactions' => $classified,
        ];
    }

    /**
     * Calculate monthly True Revenue breakdown with business day calculations
     *
     * @param array $transactions Array of transaction data
     * @param string|null $industry Optional industry for industry-specific patterns
     * @return array Monthly breakdown with true_revenue, daily_true_revenue, etc.
     */
    public function getMonthlyBreakdown(array $transactions, ?string $industry = null): array
    {
        $businessDaysPerMonth = $this->config['business_days_per_month'] ?? 21.67;
        $monthlyData = [];

        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (!$date) {
                continue;
            }

            // Parse date and get month key
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                continue;
            }

            $monthKey = date('Y-m', $timestamp);

            if (!isset($monthlyData[$monthKey])) {
                $daysInMonth = (int) date('t', $timestamp);
                $monthlyData[$monthKey] = [
                    'month_key' => $monthKey,
                    'month_name' => date('F Y', $timestamp),
                    'total_credits' => 0,
                    'true_revenue' => 0,
                    'excluded' => 0,
                    'needs_review' => 0,
                    'calendar_days' => $daysInMonth,
                    'business_days' => $businessDaysPerMonth,
                    'transaction_count' => 0,
                    'excluded_transactions' => [],
                    'needs_review_transactions' => [],
                ];
            }

            // Only process credits
            if (($txn['type'] ?? '') !== 'credit') {
                continue;
            }

            $result = $this->classify($txn, $industry);
            $amount = (float) ($txn['amount'] ?? 0);

            $monthlyData[$monthKey]['total_credits'] += $amount;
            $monthlyData[$monthKey]['transaction_count']++;

            switch ($result['classification']) {
                case self::CLASSIFICATION_REVENUE:
                    $monthlyData[$monthKey]['true_revenue'] += $amount;
                    break;
                case self::CLASSIFICATION_EXCLUDED:
                    $monthlyData[$monthKey]['excluded'] += $amount;
                    $monthlyData[$monthKey]['excluded_transactions'][] = [
                        'description' => $txn['description'] ?? '',
                        'amount' => $amount,
                        'reason' => $result['reason'],
                    ];
                    break;
                case self::CLASSIFICATION_NEEDS_REVIEW:
                    $monthlyData[$monthKey]['needs_review'] += $amount;
                    $monthlyData[$monthKey]['needs_review_transactions'][] = [
                        'description' => $txn['description'] ?? '',
                        'amount' => $amount,
                        'reason' => $result['reason'],
                    ];
                    break;
            }
        }

        // Calculate daily revenue using business days
        foreach ($monthlyData as &$month) {
            $month['true_revenue'] = round($month['true_revenue'], 2);
            $month['excluded'] = round($month['excluded'], 2);
            $month['needs_review'] = round($month['needs_review'], 2);
            $month['total_credits'] = round($month['total_credits'], 2);

            $month['daily_true_revenue'] = $month['business_days'] > 0
                ? round($month['true_revenue'] / $month['business_days'], 2)
                : 0;

            $month['revenue_ratio'] = $month['total_credits'] > 0
                ? round(($month['true_revenue'] / $month['total_credits']) * 100, 2)
                : 0;
        }

        // Sort by month
        ksort($monthlyData);

        return array_values($monthlyData);
    }

    /**
     * Get revenue volatility metrics across months
     *
     * @param array $monthlyBreakdown Result from getMonthlyBreakdown()
     * @return array Volatility metrics including std_dev, variance, trend
     */
    public function getVolatilityMetrics(array $monthlyBreakdown): array
    {
        if (count($monthlyBreakdown) < 2) {
            return [
                'has_data' => false,
                'message' => 'Insufficient data for volatility analysis (need 2+ months)',
            ];
        }

        $revenues = array_column($monthlyBreakdown, 'true_revenue');
        $count = count($revenues);

        $mean = array_sum($revenues) / $count;
        $variance = 0;

        foreach ($revenues as $revenue) {
            $variance += pow($revenue - $mean, 2);
        }
        $variance /= $count;
        $stdDev = sqrt($variance);

        // Coefficient of variation (relative volatility)
        $cv = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

        // Calculate trend (linear regression slope)
        $xMean = ($count - 1) / 2;
        $numerator = 0;
        $denominator = 0;

        foreach ($revenues as $i => $revenue) {
            $numerator += ($i - $xMean) * ($revenue - $mean);
            $denominator += pow($i - $xMean, 2);
        }

        $slope = $denominator > 0 ? $numerator / $denominator : 0;
        $trendDirection = $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable');
        $trendPercentage = $mean > 0 ? round(($slope / $mean) * 100, 2) : 0;

        // Determine volatility level
        $volatilityLevel = 'low';
        if ($cv > 30) {
            $volatilityLevel = 'high';
        } elseif ($cv > 15) {
            $volatilityLevel = 'medium';
        }

        return [
            'has_data' => true,
            'months_analyzed' => $count,
            'average_revenue' => round($mean, 2),
            'min_revenue' => round(min($revenues), 2),
            'max_revenue' => round(max($revenues), 2),
            'std_deviation' => round($stdDev, 2),
            'variance' => round($variance, 2),
            'coefficient_of_variation' => round($cv, 2),
            'volatility_level' => $volatilityLevel,
            'trend' => [
                'direction' => $trendDirection,
                'monthly_change' => round($slope, 2),
                'percentage_change' => $trendPercentage,
            ],
        ];
    }

    /**
     * Detect potential MCA payments in debit transactions
     *
     * @param array $transactions Array of transaction data
     * @return array Detected MCA payments with funder, amount, frequency
     */
    public function detectMcaPayments(array $transactions): array
    {
        $mcaPatterns = [
            '/ONDECK|ON\s*DECK/i' => 'OnDeck',
            '/KABBAGE/i' => 'Kabbage',
            '/FUNDBOX/i' => 'Fundbox',
            '/BLUEVINE|BLUE\s*VINE/i' => 'BlueVine',
            '/CREDIBLY/i' => 'Credibly',
            '/KAPITUS/i' => 'Kapitus',
            '/RAPID\s*FINANCE/i' => 'Rapid Finance',
            '/CAN\s*CAPITAL/i' => 'CAN Capital',
            '/NATIONAL\s*FUNDING/i' => 'National Funding',
            '/SQUARE\s*CAPITAL/i' => 'Square Capital',
            '/PAYPAL\s*WORK/i' => 'PayPal Working Capital',
            '/SHOPIFY\s*CAP/i' => 'Shopify Capital',
            '/STRIPE\s*CAP/i' => 'Stripe Capital',
            '/CLEARCO|CLEARBANC/i' => 'Clearco',
            '/LIBERTAS/i' => 'Libertas',
            '/MCA\s*(PAYMENT|PYMT|PMT)/i' => 'Unknown MCA',
            '/MERCHANT\s*CASH/i' => 'Unknown MCA',
            '/DAILY\s*(PAYMENT|PYMT|PMT)/i' => 'Unknown MCA',
        ];

        $detected = [];
        $byFunder = [];

        foreach ($transactions as $txn) {
            // Only check debits
            if (($txn['type'] ?? '') !== 'debit') {
                continue;
            }

            $description = $txn['description'] ?? '';
            $amount = (float) ($txn['amount'] ?? 0);
            $date = $txn['date'] ?? '';

            foreach ($mcaPatterns as $pattern => $funder) {
                if (@preg_match($pattern, $description)) {
                    if (!isset($byFunder[$funder])) {
                        $byFunder[$funder] = [
                            'funder' => $funder,
                            'total_amount' => 0,
                            'payment_count' => 0,
                            'payments' => [],
                        ];
                    }

                    $byFunder[$funder]['total_amount'] += $amount;
                    $byFunder[$funder]['payment_count']++;
                    $byFunder[$funder]['payments'][] = [
                        'date' => $date,
                        'amount' => $amount,
                        'description' => $description,
                    ];
                    break;
                }
            }
        }

        // Calculate average daily payment per funder
        foreach ($byFunder as &$funderData) {
            if ($funderData['payment_count'] > 0) {
                $funderData['average_payment'] = round(
                    $funderData['total_amount'] / $funderData['payment_count'],
                    2
                );

                // Estimate daily payment (most MCAs are daily)
                $funderData['estimated_daily_payment'] = $funderData['average_payment'];

                // Estimate monthly payment
                $funderData['estimated_monthly_payment'] = round(
                    $funderData['average_payment'] * 22, // ~22 business days
                    2
                );
            }
        }

        // Calculate totals
        $totalDailyPayment = array_sum(array_column($byFunder, 'estimated_daily_payment'));
        $totalMonthlyPayment = array_sum(array_column($byFunder, 'estimated_monthly_payment'));

        return [
            'active_positions' => count($byFunder),
            'total_daily_payment' => round($totalDailyPayment, 2),
            'total_monthly_payment' => round($totalMonthlyPayment, 2),
            'by_funder' => array_values($byFunder),
        ];
    }

    /**
     * Calculate MCA capacity based on True Revenue and existing positions
     *
     * @param float $monthlyTrueRevenue Monthly True Revenue
     * @param float $existingDailyPayment Total existing MCA daily payments
     * @return array Capacity metrics including remaining capacity and max funding
     */
    public function calculateMcaCapacity(float $monthlyTrueRevenue, float $existingDailyPayment = 0): array
    {
        $businessDays = $this->config['business_days_per_month'] ?? 21.67;
        $maxWithhold = $this->config['max_withhold_percentage'] ?? 0.20;

        // Calculate daily true revenue
        $dailyTrueRevenue = $monthlyTrueRevenue / $businessDays;

        // Maximum allowed daily payment (20% of daily revenue)
        $maxDailyPayment = $dailyTrueRevenue * $maxWithhold;

        // Remaining capacity for new position
        $remainingCapacity = max(0, $maxDailyPayment - $existingDailyPayment);

        // Current withhold percentage
        $currentWithhold = $dailyTrueRevenue > 0
            ? ($existingDailyPayment / $dailyTrueRevenue) * 100
            : 0;

        // Remaining withhold capacity
        $remainingWithholdPercent = max(0, ($maxWithhold * 100) - $currentWithhold);

        return [
            'monthly_true_revenue' => round($monthlyTrueRevenue, 2),
            'daily_true_revenue' => round($dailyTrueRevenue, 2),
            'max_withhold_percentage' => $maxWithhold * 100,
            'max_daily_payment' => round($maxDailyPayment, 2),
            'existing_daily_payment' => round($existingDailyPayment, 2),
            'current_withhold_percent' => round($currentWithhold, 2),
            'remaining_daily_capacity' => round($remainingCapacity, 2),
            'remaining_withhold_percent' => round($remainingWithholdPercent, 2),
            'at_capacity' => $remainingCapacity <= 0,
            'can_take_position' => $remainingCapacity > 0,
        ];
    }

    /**
     * Generate classification summary for reporting
     *
     * @param array $classificationResult Result from calculateTrueRevenue()
     * @return array Summary grouped by classification reason
     */
    public function getClassificationSummary(array $classificationResult): array
    {
        $byReason = [];

        foreach ($classificationResult['classified_transactions'] as $txn) {
            $result = $txn['classification_result'] ?? [];
            $reason = $result['reason'] ?? 'Unknown';
            $classification = $result['classification'] ?? 'unknown';
            $amount = (float) ($txn['amount'] ?? 0);

            $key = $classification . '::' . $reason;

            if (!isset($byReason[$key])) {
                $byReason[$key] = [
                    'classification' => $classification,
                    'reason' => $reason,
                    'count' => 0,
                    'total_amount' => 0,
                    'source' => $result['source'] ?? 'unknown',
                ];
            }

            $byReason[$key]['count']++;
            $byReason[$key]['total_amount'] += $amount;
        }

        // Sort by total amount descending
        usort($byReason, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);

        // Round amounts
        foreach ($byReason as &$item) {
            $item['total_amount'] = round($item['total_amount'], 2);
        }

        return $byReason;
    }
}
