<?php

namespace App\Services;

use Carbon\Carbon;

class FraudDetectionService
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('fraud_detection', $this->getDefaultConfig());
    }

    /**
     * Analyze transactions for fraud indicators
     */
    public function analyze(array $transactions, array $applicationData = []): array
    {
        $indicators = [];
        $flags = [];
        $score = 100; // Start with perfect score, deduct for issues

        // Run all fraud detection checks
        $checks = [
            'duplicate_transactions' => $this->checkDuplicateTransactions($transactions),
            'round_number_deposits' => $this->checkRoundNumberDeposits($transactions),
            'suspicious_timing' => $this->checkSuspiciousTiming($transactions),
            'deposit_velocity' => $this->checkDepositVelocity($transactions),
            'structured_deposits' => $this->checkStructuredDeposits($transactions),
            'unusual_patterns' => $this->checkUnusualPatterns($transactions),
            'revenue_manipulation' => $this->checkRevenueManipulation($transactions),
            'fake_revenue_indicators' => $this->checkFakeRevenueIndicators($transactions),
            'statement_gaps' => $this->checkStatementGaps($transactions),
            'weekend_anomalies' => $this->checkWeekendAnomalies($transactions),
        ];

        foreach ($checks as $checkName => $result) {
            $indicators[$checkName] = $result;

            if ($result['risk_level'] === 'high') {
                $score -= $result['score_impact'];
                $flags[] = [
                    'type' => $checkName,
                    'severity' => 'high',
                    'message' => $result['message'],
                    'details' => $result['details'] ?? [],
                ];
            } elseif ($result['risk_level'] === 'medium') {
                $score -= $result['score_impact'] / 2;
                $flags[] = [
                    'type' => $checkName,
                    'severity' => 'medium',
                    'message' => $result['message'],
                    'details' => $result['details'] ?? [],
                ];
            }
        }

        // Additional cross-reference checks if application data available
        if (! empty($applicationData)) {
            $crossRefResult = $this->crossReferenceCheck($transactions, $applicationData);
            $indicators['cross_reference'] = $crossRefResult;
            if ($crossRefResult['risk_level'] !== 'low') {
                $score -= $crossRefResult['score_impact'];
                $flags = array_merge($flags, $crossRefResult['flags']);
            }
        }

        $score = max(0, min(100, $score));

        return [
            'fraud_score' => $score,
            'risk_level' => $this->getRiskLevel($score),
            'indicators' => $indicators,
            'flags' => $flags,
            'flag_count' => [
                'high' => count(array_filter($flags, fn ($f) => $f['severity'] === 'high')),
                'medium' => count(array_filter($flags, fn ($f) => $f['severity'] === 'medium')),
            ],
            'recommendation' => $this->getRecommendation($score, $flags),
        ];
    }

    /**
     * Check for duplicate transactions
     */
    protected function checkDuplicateTransactions(array $transactions): array
    {
        $duplicates = [];
        $seen = [];

        foreach ($transactions as $i => $txn) {
            $key = $txn['date'].'|'.$txn['amount'].'|'.substr($txn['description'], 0, 20);

            if (isset($seen[$key])) {
                $duplicates[] = [
                    'transaction_1' => $seen[$key],
                    'transaction_2' => $i,
                    'amount' => $txn['amount'],
                    'date' => $txn['date'],
                ];
            }
            $seen[$key] = $i;
        }

        $duplicateCount = count($duplicates);
        $riskLevel = 'low';
        $scoreImpact = 0;

        if ($duplicateCount >= 5) {
            $riskLevel = 'high';
            $scoreImpact = 25;
        } elseif ($duplicateCount >= 2) {
            $riskLevel = 'medium';
            $scoreImpact = 10;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'count' => $duplicateCount,
            'message' => $duplicateCount > 0 ? "Found {$duplicateCount} duplicate transactions" : 'No duplicates found',
            'details' => $duplicates,
        ];
    }

    /**
     * Check for suspicious round number deposits
     */
    protected function checkRoundNumberDeposits(array $transactions): array
    {
        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit');
        $roundDeposits = [];
        $totalCredits = count($credits);

        foreach ($credits as $i => $txn) {
            // Check if amount is a round number (divisible by 100 or 1000)
            if ($txn['amount'] >= 1000 && $txn['amount'] % 1000 === 0) {
                $roundDeposits[] = [
                    'index' => $i,
                    'amount' => $txn['amount'],
                    'date' => $txn['date'],
                    'description' => $txn['description'],
                ];
            }
        }

        $roundCount = count($roundDeposits);
        $roundRatio = $totalCredits > 0 ? $roundCount / $totalCredits : 0;

        $riskLevel = 'low';
        $scoreImpact = 0;

        // Suspicious if more than 50% of deposits are round thousands
        if ($roundRatio > 0.5 && $roundCount >= 5) {
            $riskLevel = 'high';
            $scoreImpact = 20;
        } elseif ($roundRatio > 0.3 && $roundCount >= 3) {
            $riskLevel = 'medium';
            $scoreImpact = 10;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'count' => $roundCount,
            'ratio' => round($roundRatio * 100, 2),
            'message' => $riskLevel !== 'low' ? "Unusual number of round-figure deposits ({$roundCount})" : 'Round number deposits within normal range',
            'details' => array_slice($roundDeposits, 0, 10),
        ];
    }

    /**
     * Check for suspicious timing patterns
     */
    protected function checkSuspiciousTiming(array $transactions): array
    {
        $timingIssues = [];

        // Group transactions by date
        $byDate = [];
        foreach ($transactions as $txn) {
            $date = $txn['date'];
            if (! isset($byDate[$date])) {
                $byDate[$date] = [];
            }
            $byDate[$date][] = $txn;
        }

        // Check for days with unusually high transaction volume
        $avgPerDay = count($transactions) / max(1, count($byDate));
        foreach ($byDate as $date => $dayTxns) {
            if (count($dayTxns) > $avgPerDay * 3) {
                $timingIssues[] = [
                    'type' => 'high_volume_day',
                    'date' => $date,
                    'count' => count($dayTxns),
                    'average' => round($avgPerDay, 2),
                ];
            }
        }

        // Check for transactions clustered at month end (potential window dressing)
        $monthEndTxns = 0;
        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit');
        foreach ($credits as $txn) {
            $day = (int) Carbon::parse($txn['date'])->format('d');
            if ($day >= 28) {
                $monthEndTxns++;
            }
        }
        $monthEndRatio = count($credits) > 0 ? $monthEndTxns / count($credits) : 0;
        if ($monthEndRatio > 0.4) {
            $timingIssues[] = [
                'type' => 'month_end_clustering',
                'ratio' => round($monthEndRatio * 100, 2),
                'message' => 'High concentration of deposits at month end',
            ];
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($timingIssues) >= 3) {
            $riskLevel = 'high';
            $scoreImpact = 15;
        } elseif (count($timingIssues) >= 1) {
            $riskLevel = 'medium';
            $scoreImpact = 8;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'issue_count' => count($timingIssues),
            'message' => count($timingIssues) > 0 ? 'Suspicious timing patterns detected' : 'No timing issues detected',
            'details' => $timingIssues,
        ];
    }

    /**
     * Check for unusual deposit velocity
     */
    protected function checkDepositVelocity(array $transactions): array
    {
        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit');
        if (count($credits) < 10) {
            return [
                'risk_level' => 'low',
                'score_impact' => 0,
                'message' => 'Insufficient data for velocity analysis',
            ];
        }

        // Calculate daily deposit amounts
        $dailyDeposits = [];
        foreach ($credits as $txn) {
            $date = $txn['date'];
            $dailyDeposits[$date] = ($dailyDeposits[$date] ?? 0) + $txn['amount'];
        }

        $values = array_values($dailyDeposits);
        $mean = array_sum($values) / count($values);
        $stdDev = $this->standardDeviation($values);

        // Find outliers (more than 3 standard deviations)
        $outliers = [];
        foreach ($dailyDeposits as $date => $amount) {
            if ($stdDev > 0 && abs($amount - $mean) > 3 * $stdDev) {
                $outliers[] = [
                    'date' => $date,
                    'amount' => $amount,
                    'deviation' => round(($amount - $mean) / $stdDev, 2),
                ];
            }
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($outliers) >= 5) {
            $riskLevel = 'high';
            $scoreImpact = 15;
        } elseif (count($outliers) >= 2) {
            $riskLevel = 'medium';
            $scoreImpact = 8;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'outlier_count' => count($outliers),
            'message' => count($outliers) > 0 ? 'Unusual deposit velocity spikes detected' : 'Deposit velocity is consistent',
            'details' => $outliers,
        ];
    }

    /**
     * Check for structured deposits (potential smurfing)
     */
    protected function checkStructuredDeposits(array $transactions): array
    {
        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit');
        $structuredDeposits = [];

        // Check for deposits just under reporting thresholds
        $thresholds = [10000, 5000, 3000]; // Common structuring thresholds

        foreach ($credits as $txn) {
            foreach ($thresholds as $threshold) {
                // Within 5% below threshold
                if ($txn['amount'] >= $threshold * 0.95 && $txn['amount'] < $threshold) {
                    $structuredDeposits[] = [
                        'amount' => $txn['amount'],
                        'date' => $txn['date'],
                        'threshold' => $threshold,
                        'below_by' => $threshold - $txn['amount'],
                    ];
                }
            }
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($structuredDeposits) >= 4) {
            $riskLevel = 'high';
            $scoreImpact = 25;
        } elseif (count($structuredDeposits) >= 2) {
            $riskLevel = 'medium';
            $scoreImpact = 12;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'count' => count($structuredDeposits),
            'message' => count($structuredDeposits) > 0 ? 'Potential structured deposits detected' : 'No structured deposit patterns',
            'details' => $structuredDeposits,
        ];
    }

    /**
     * Check for unusual transaction patterns
     */
    protected function checkUnusualPatterns(array $transactions): array
    {
        $patterns = [];

        // Check for same-day in-and-out (deposit followed by withdrawal of similar amount)
        $byDate = [];
        foreach ($transactions as $txn) {
            $byDate[$txn['date']][] = $txn;
        }

        foreach ($byDate as $date => $dayTxns) {
            $credits = array_filter($dayTxns, fn ($t) => $t['type'] === 'credit');
            $debits = array_filter($dayTxns, fn ($t) => $t['type'] === 'debit');

            foreach ($credits as $credit) {
                foreach ($debits as $debit) {
                    // Same-day matching within 10%
                    if (abs($credit['amount'] - $debit['amount']) / $credit['amount'] < 0.1) {
                        $patterns[] = [
                            'type' => 'same_day_match',
                            'date' => $date,
                            'credit' => $credit['amount'],
                            'debit' => $debit['amount'],
                        ];
                    }
                }
            }
        }

        // Check for repetitive exact amounts
        $amounts = array_column($transactions, 'amount');
        $amountCounts = array_count_values(array_map(fn ($a) => (string) $a, $amounts));
        $repetitive = array_filter($amountCounts, fn ($c) => $c >= 5);

        foreach ($repetitive as $amount => $count) {
            $patterns[] = [
                'type' => 'repetitive_amount',
                'amount' => (float) $amount,
                'count' => $count,
            ];
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($patterns) >= 5) {
            $riskLevel = 'high';
            $scoreImpact = 20;
        } elseif (count($patterns) >= 2) {
            $riskLevel = 'medium';
            $scoreImpact = 10;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'pattern_count' => count($patterns),
            'message' => count($patterns) > 0 ? 'Unusual transaction patterns detected' : 'Transaction patterns appear normal',
            'details' => $patterns,
        ];
    }

    /**
     * Check for potential revenue manipulation
     */
    protected function checkRevenueManipulation(array $transactions): array
    {
        $issues = [];

        // Check for circular transactions (self-transfers that look like revenue)
        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit' && $t['is_revenue']);

        // Look for deposits from personal-looking sources
        $personalPatterns = ['personal', 'savings', 'from checking', 'my account', 'self'];
        $suspiciousCredits = [];

        foreach ($credits as $txn) {
            $descLower = strtolower($txn['description']);
            foreach ($personalPatterns as $pattern) {
                if (str_contains($descLower, $pattern)) {
                    $suspiciousCredits[] = [
                        'amount' => $txn['amount'],
                        'date' => $txn['date'],
                        'description' => $txn['description'],
                        'pattern' => $pattern,
                    ];
                    break;
                }
            }
        }

        if (count($suspiciousCredits) >= 3) {
            $issues['personal_deposits'] = $suspiciousCredits;
        }

        // Check for deposits matching common kiting patterns
        $largeCredits = array_filter($credits, fn ($t) => $t['amount'] >= 5000);
        $largeDebits = array_filter($transactions, fn ($t) => $t['type'] === 'debit' && $t['amount'] >= 5000);

        // Check if large deposits are quickly withdrawn
        $potentialKiting = 0;
        foreach ($largeCredits as $credit) {
            $creditDate = Carbon::parse($credit['date']);
            foreach ($largeDebits as $debit) {
                $debitDate = Carbon::parse($debit['date']);
                $daysDiff = $creditDate->diffInDays($debitDate);
                if ($daysDiff >= 0 && $daysDiff <= 3 && abs($credit['amount'] - $debit['amount']) < $credit['amount'] * 0.2) {
                    $potentialKiting++;
                }
            }
        }

        if ($potentialKiting >= 3) {
            $issues['potential_kiting'] = $potentialKiting;
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($issues) >= 2 || ($issues['potential_kiting'] ?? 0) >= 5) {
            $riskLevel = 'high';
            $scoreImpact = 25;
        } elseif (count($issues) >= 1) {
            $riskLevel = 'medium';
            $scoreImpact = 12;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'issue_count' => count($issues),
            'message' => count($issues) > 0 ? 'Potential revenue manipulation indicators' : 'No manipulation indicators',
            'details' => $issues,
        ];
    }

    /**
     * Check for fake revenue indicators
     */
    protected function checkFakeRevenueIndicators(array $transactions): array
    {
        $indicators = [];

        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit');

        // Check for deposits from loan/funding sources marked as revenue
        $loanPatterns = ['loan', 'advance', 'funding', 'credit line', 'loc ', 'sba'];
        foreach ($credits as $txn) {
            if ($txn['is_revenue']) {
                $descLower = strtolower($txn['description']);
                foreach ($loanPatterns as $pattern) {
                    if (str_contains($descLower, $pattern)) {
                        $indicators[] = [
                            'type' => 'loan_as_revenue',
                            'amount' => $txn['amount'],
                            'date' => $txn['date'],
                            'description' => $txn['description'],
                        ];
                        break;
                    }
                }
            }
        }

        // Check for refunds that might be counted as revenue
        $refundPatterns = ['refund', 'return', 'reversal', 'credit back'];
        foreach ($credits as $txn) {
            if ($txn['is_revenue']) {
                $descLower = strtolower($txn['description']);
                foreach ($refundPatterns as $pattern) {
                    if (str_contains($descLower, $pattern)) {
                        $indicators[] = [
                            'type' => 'refund_as_revenue',
                            'amount' => $txn['amount'],
                            'date' => $txn['date'],
                            'description' => $txn['description'],
                        ];
                        break;
                    }
                }
            }
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($indicators) >= 5) {
            $riskLevel = 'high';
            $scoreImpact = 20;
        } elseif (count($indicators) >= 2) {
            $riskLevel = 'medium';
            $scoreImpact = 10;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'count' => count($indicators),
            'message' => count($indicators) > 0 ? 'Potential fake revenue indicators found' : 'Revenue sources appear legitimate',
            'details' => $indicators,
        ];
    }

    /**
     * Check for gaps in statement data
     */
    protected function checkStatementGaps(array $transactions): array
    {
        if (count($transactions) < 10) {
            return [
                'risk_level' => 'low',
                'score_impact' => 0,
                'message' => 'Insufficient data to check for gaps',
            ];
        }

        $dates = array_unique(array_column($transactions, 'date'));
        sort($dates);

        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $prevDate = Carbon::parse($dates[$i - 1]);
            $currDate = Carbon::parse($dates[$i]);
            $daysDiff = $prevDate->diffInDays($currDate);

            // Gap of more than 5 business days is suspicious
            if ($daysDiff > 7) {
                $gaps[] = [
                    'from' => $dates[$i - 1],
                    'to' => $dates[$i],
                    'days' => $daysDiff,
                ];
            }
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if (count($gaps) >= 3) {
            $riskLevel = 'high';
            $scoreImpact = 15;
        } elseif (count($gaps) >= 1) {
            $riskLevel = 'medium';
            $scoreImpact = 8;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'gap_count' => count($gaps),
            'message' => count($gaps) > 0 ? 'Statement gaps detected - possible edited statements' : 'No significant gaps in transaction dates',
            'details' => $gaps,
        ];
    }

    /**
     * Check for weekend transaction anomalies
     */
    protected function checkWeekendAnomalies(array $transactions): array
    {
        $weekdayCredits = 0;
        $weekendCredits = 0;
        $weekendCreditTotal = 0;

        foreach ($transactions as $txn) {
            if ($txn['type'] === 'credit') {
                $dayOfWeek = Carbon::parse($txn['date'])->dayOfWeek;
                if ($dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY) {
                    $weekendCredits++;
                    $weekendCreditTotal += $txn['amount'];
                } else {
                    $weekdayCredits++;
                }
            }
        }

        $totalCredits = $weekdayCredits + $weekendCredits;
        $weekendRatio = $totalCredits > 0 ? $weekendCredits / $totalCredits : 0;

        // Most legitimate business deposits occur on weekdays
        // High weekend deposit ratio could indicate manufactured statements
        $riskLevel = 'low';
        $scoreImpact = 0;

        if ($weekendRatio > 0.3 && $weekendCredits >= 10) {
            $riskLevel = 'high';
            $scoreImpact = 15;
        } elseif ($weekendRatio > 0.2 && $weekendCredits >= 5) {
            $riskLevel = 'medium';
            $scoreImpact = 8;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'weekend_ratio' => round($weekendRatio * 100, 2),
            'weekend_count' => $weekendCredits,
            'weekend_total' => round($weekendCreditTotal, 2),
            'message' => $riskLevel !== 'low' ? 'Unusual number of weekend deposits' : 'Weekend transaction pattern is normal',
        ];
    }

    /**
     * Cross-reference bank data with application data
     */
    protected function crossReferenceCheck(array $transactions, array $applicationData): array
    {
        $flags = [];
        $issues = 0;

        // Check if stated monthly revenue matches bank deposits
        if (isset($applicationData['monthly_revenue'])) {
            $statedRevenue = $applicationData['monthly_revenue'];
            $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit' && $t['is_revenue']);
            $actualRevenue = array_sum(array_column($credits, 'amount'));
            $months = $this->getMonthCount($transactions);
            $avgMonthlyRevenue = $months > 0 ? $actualRevenue / $months : 0;

            $variance = $statedRevenue > 0 ? abs($avgMonthlyRevenue - $statedRevenue) / $statedRevenue * 100 : 0;

            if ($variance > 50) {
                $flags[] = [
                    'type' => 'revenue_mismatch',
                    'severity' => 'high',
                    'message' => 'Stated revenue differs from bank data by '.round($variance).'%',
                    'details' => [
                        'stated' => $statedRevenue,
                        'calculated' => round($avgMonthlyRevenue, 2),
                        'variance' => round($variance, 2),
                    ],
                ];
                $issues++;
            } elseif ($variance > 25) {
                $flags[] = [
                    'type' => 'revenue_mismatch',
                    'severity' => 'medium',
                    'message' => 'Stated revenue differs from bank data by '.round($variance).'%',
                    'details' => [
                        'stated' => $statedRevenue,
                        'calculated' => round($avgMonthlyRevenue, 2),
                        'variance' => round($variance, 2),
                    ],
                ];
            }
        }

        // Check if business name appears in transactions
        if (isset($applicationData['business_name'])) {
            $businessName = strtolower($applicationData['business_name']);
            $nameFound = false;
            foreach ($transactions as $txn) {
                if (str_contains(strtolower($txn['description']), $businessName)) {
                    $nameFound = true;
                    break;
                }
            }
            if (! $nameFound) {
                $flags[] = [
                    'type' => 'business_name_missing',
                    'severity' => 'medium',
                    'message' => 'Business name not found in transaction descriptions',
                ];
            }
        }

        $riskLevel = 'low';
        $scoreImpact = 0;

        if ($issues >= 2) {
            $riskLevel = 'high';
            $scoreImpact = 20;
        } elseif ($issues >= 1 || count($flags) >= 2) {
            $riskLevel = 'medium';
            $scoreImpact = 10;
        }

        return [
            'risk_level' => $riskLevel,
            'score_impact' => $scoreImpact,
            'flags' => $flags,
        ];
    }

    /**
     * Get risk level from fraud score
     */
    protected function getRiskLevel(int $score): string
    {
        if ($score >= 80) {
            return 'low';
        }
        if ($score >= 60) {
            return 'medium';
        }
        if ($score >= 40) {
            return 'elevated';
        }

        return 'high';
    }

    /**
     * Get recommendation based on fraud analysis
     */
    protected function getRecommendation(int $score, array $flags): array
    {
        $highFlags = count(array_filter($flags, fn ($f) => $f['severity'] === 'high'));

        if ($score < 40 || $highFlags >= 3) {
            return [
                'action' => 'DECLINE',
                'reason' => 'Multiple high-risk fraud indicators detected',
                'requires_review' => false,
            ];
        }

        if ($score < 60 || $highFlags >= 1) {
            return [
                'action' => 'MANUAL_REVIEW',
                'reason' => 'Fraud indicators require human review',
                'requires_review' => true,
                'review_focus' => array_column($flags, 'type'),
            ];
        }

        if ($score < 80) {
            return [
                'action' => 'PROCEED_WITH_CAUTION',
                'reason' => 'Minor indicators present but acceptable',
                'requires_review' => false,
            ];
        }

        return [
            'action' => 'PROCEED',
            'reason' => 'No significant fraud indicators',
            'requires_review' => false,
        ];
    }

    /**
     * Calculate standard deviation
     */
    protected function standardDeviation(array $values): float
    {
        $n = count($values);
        if ($n === 0) {
            return 0;
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn ($v) => pow($v - $mean, 2), $values)) / $n;

        return sqrt($variance);
    }

    /**
     * Get month count from transactions
     */
    protected function getMonthCount(array $transactions): int
    {
        if (empty($transactions)) {
            return 0;
        }

        $months = [];
        foreach ($transactions as $txn) {
            $month = Carbon::parse($txn['date'])->format('Y-m');
            $months[$month] = true;
        }

        return count($months);
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'duplicate_threshold' => 5,
            'round_number_ratio' => 0.5,
            'structuring_threshold' => 10000,
            'gap_days_threshold' => 7,
        ];
    }
}
