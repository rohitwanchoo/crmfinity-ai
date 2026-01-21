<?php

namespace App\Services;

use App\Models\AnalyzedTransaction;
use App\Models\MCAApplication;
use Illuminate\Support\Facades\Log;

class UnderwritingScoreService
{
    protected array $weights = [
        'true_revenue' => 0.25,
        'cash_flow' => 0.25,
        'balance_quality' => 0.20,
        'transaction_patterns' => 0.15,
        'risk_indicators' => 0.15,
    ];

    protected array $thresholds = [
        'auto_approve' => 75,
        'conditional_approve' => 60,
        'manual_review' => 45,
        'decline' => 30,
    ];

    /**
     * Calculate underwriting score for an application based on bank analysis
     */
    public function calculateScore(MCAApplication $application): array
    {
        $startTime = microtime(true);

        // Get all analyzed bank statements
        $analyzedDocuments = $application->documents()
            ->where('document_type', 'bank_statement')
            ->whereNotNull('analyzed_at')
            ->get();

        if ($analyzedDocuments->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No analyzed bank statements found',
                'score' => null,
            ];
        }

        // Gather all transactions
        $allTransactions = [];
        foreach ($analyzedDocuments as $document) {
            if ($document->analysis_session_id) {
                $transactions = AnalyzedTransaction::where('analysis_session_id', $document->analysis_session_id)
                    ->orderBy('transaction_date')
                    ->get()
                    ->map(function ($t) {
                        return [
                            'date' => $t->transaction_date,
                            'description' => $t->description,
                            'amount' => $t->amount,
                            'type' => $t->type,
                            'balance' => null, // Not stored in DB
                            'category' => $t->category,
                        ];
                    })
                    ->toArray();
                $allTransactions = array_merge($allTransactions, $transactions);
            }
        }

        // Calculate component scores
        $scores = [];
        $details = [];

        // 1. True Revenue Score
        $trueRevenueResult = $this->calculateTrueRevenueScore($analyzedDocuments, $application);
        $scores['true_revenue'] = $trueRevenueResult['score'];
        $details['true_revenue'] = $trueRevenueResult;

        // 2. Cash Flow Score
        $cashFlowResult = $this->calculateCashFlowScore($analyzedDocuments, $allTransactions);
        $scores['cash_flow'] = $cashFlowResult['score'];
        $details['cash_flow'] = $cashFlowResult;

        // 3. Balance Quality Score
        $balanceResult = $this->calculateBalanceScore($allTransactions);
        $scores['balance_quality'] = $balanceResult['score'];
        $details['balance_quality'] = $balanceResult;

        // 4. Transaction Pattern Score
        $patternResult = $this->calculatePatternScore($allTransactions);
        $scores['transaction_patterns'] = $patternResult['score'];
        $details['transaction_patterns'] = $patternResult;

        // 5. Risk Indicator Score
        $riskResult = $this->calculateRiskIndicatorScore($allTransactions);
        $scores['risk_indicators'] = $riskResult['score'];
        $details['risk_indicators'] = $riskResult;

        // Calculate weighted total
        $totalScore = 0;
        foreach ($scores as $key => $score) {
            $totalScore += $score * $this->weights[$key];
        }
        $totalScore = (int) round($totalScore);

        // Determine decision
        $decision = $this->determineDecision($totalScore, $details);

        // Compile flags
        $flags = $this->compileFlags($details);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'success' => true,
            'score' => $totalScore,
            'decision' => $decision['recommendation'],
            'decision_details' => $decision,
            'component_scores' => $scores,
            'component_details' => $details,
            'flags' => $flags,
            'summary' => $this->generateSummary($totalScore, $decision, $flags, $scores),
            'execution_time_ms' => $executionTime,
        ];
    }

    /**
     * Calculate True Revenue Score (0-100)
     */
    protected function calculateTrueRevenueScore(iterable $documents, MCAApplication $application): array
    {
        $totalTrueRevenue = 0;
        $monthlyRevenues = [];
        $flags = [];

        foreach ($documents as $doc) {
            $trueRevenue = (float) $doc->true_revenue;
            $totalTrueRevenue += $trueRevenue;

            if ($doc->statement_period) {
                $monthlyRevenues[$doc->statement_period] = $trueRevenue;
            }
        }

        $avgMonthlyRevenue = count($monthlyRevenues) > 0
            ? $totalTrueRevenue / count($monthlyRevenues)
            : $totalTrueRevenue;

        // Compare with stated monthly revenue
        $statedRevenue = (float) $application->monthly_revenue;
        $revenueRatio = $statedRevenue > 0 ? $avgMonthlyRevenue / $statedRevenue : 0;

        // Calculate consistency
        $consistency = $this->calculateConsistency($monthlyRevenues);

        // Score calculation
        $score = 50; // Base score

        // Revenue amount scoring
        if ($avgMonthlyRevenue >= 50000) {
            $score += 25;
        } elseif ($avgMonthlyRevenue >= 25000) {
            $score += 20;
        } elseif ($avgMonthlyRevenue >= 15000) {
            $score += 15;
        } elseif ($avgMonthlyRevenue >= 10000) {
            $score += 10;
        } elseif ($avgMonthlyRevenue < 5000) {
            $score -= 15;
            $flags[] = 'Low monthly true revenue';
        }

        // Revenue verification scoring
        if ($revenueRatio >= 0.9 && $revenueRatio <= 1.1) {
            $score += 15; // Revenue matches stated
        } elseif ($revenueRatio >= 0.7 && $revenueRatio < 0.9) {
            $score += 5;
            $flags[] = 'Actual revenue slightly below stated';
        } elseif ($revenueRatio < 0.7 && $statedRevenue > 0) {
            $score -= 10;
            $flags[] = 'Actual revenue significantly below stated amount';
        } elseif ($revenueRatio > 1.2) {
            $score += 10; // Revenue exceeds stated
        }

        // Consistency bonus
        if ($consistency >= 0.8) {
            $score += 10;
        } elseif ($consistency >= 0.6) {
            $score += 5;
        } elseif ($consistency < 0.4) {
            $score -= 10;
            $flags[] = 'Inconsistent monthly revenue';
        }

        return [
            'score' => max(0, min(100, $score)),
            'total_true_revenue' => round($totalTrueRevenue, 2),
            'avg_monthly_revenue' => round($avgMonthlyRevenue, 2),
            'stated_revenue' => $statedRevenue,
            'revenue_ratio' => round($revenueRatio, 2),
            'consistency' => round($consistency, 2),
            'months_analyzed' => count($monthlyRevenues),
            'flags' => $flags,
        ];
    }

    /**
     * Calculate Cash Flow Score (0-100)
     */
    protected function calculateCashFlowScore(iterable $documents, array $transactions): array
    {
        $totalCredits = 0;
        $totalDebits = 0;
        $flags = [];

        foreach ($documents as $doc) {
            $totalCredits += (float) $doc->total_credits;
            $totalDebits += (float) $doc->total_debits;
        }

        $netFlow = $totalCredits - $totalDebits;
        $flowRatio = $totalDebits > 0 ? $totalCredits / $totalDebits : ($totalCredits > 0 ? 2 : 0);

        // Calculate monthly net flows
        $monthlyNetFlows = $this->getMonthlyNetFlows($transactions);
        $negativeMonths = count(array_filter($monthlyNetFlows, fn ($flow) => $flow < 0));
        $totalMonths = count($monthlyNetFlows);

        $score = 50;

        // Overall flow ratio scoring
        if ($flowRatio >= 1.3) {
            $score += 25;
        } elseif ($flowRatio >= 1.15) {
            $score += 20;
        } elseif ($flowRatio >= 1.05) {
            $score += 10;
        } elseif ($flowRatio >= 0.95) {
            $score += 0; // Break-even
        } elseif ($flowRatio < 0.95) {
            $score -= 15;
            $flags[] = 'Negative net cash flow';
        }

        // Negative months penalty
        if ($totalMonths > 0) {
            $negativeRatio = $negativeMonths / $totalMonths;
            if ($negativeRatio > 0.5) {
                $score -= 20;
                $flags[] = 'Majority of months show negative cash flow';
            } elseif ($negativeRatio > 0.25) {
                $score -= 10;
                $flags[] = 'Some months with negative cash flow';
            }
        }

        // Net flow amount bonus
        if ($netFlow > 10000) {
            $score += 15;
        } elseif ($netFlow > 5000) {
            $score += 10;
        } elseif ($netFlow < -5000) {
            $score -= 15;
            $flags[] = 'Significant negative net flow';
        }

        return [
            'score' => max(0, min(100, $score)),
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'net_flow' => round($netFlow, 2),
            'flow_ratio' => round($flowRatio, 2),
            'negative_months' => $negativeMonths,
            'total_months' => $totalMonths,
            'flags' => $flags,
        ];
    }

    /**
     * Calculate Balance Quality Score (0-100)
     */
    protected function calculateBalanceScore(array $transactions): array
    {
        if (empty($transactions)) {
            return ['score' => 50, 'avg_balance' => 0, 'min_balance' => 0, 'negative_days' => 0, 'flags' => []];
        }

        $balances = array_column($transactions, 'balance');
        $balances = array_filter($balances, fn ($b) => $b !== null && $b !== '');
        $balances = array_map('floatval', $balances);

        if (empty($balances)) {
            return ['score' => 50, 'avg_balance' => 0, 'min_balance' => 0, 'negative_days' => 0, 'flags' => ['No balance data available']];
        }

        $avgBalance = array_sum($balances) / count($balances);
        $minBalance = min($balances);
        $maxBalance = max($balances);
        $negativeDays = count(array_filter($balances, fn ($b) => $b < 0));

        $flags = [];
        $score = 50;

        // Average balance scoring
        if ($avgBalance >= 25000) {
            $score += 25;
        } elseif ($avgBalance >= 15000) {
            $score += 20;
        } elseif ($avgBalance >= 10000) {
            $score += 15;
        } elseif ($avgBalance >= 5000) {
            $score += 10;
        } elseif ($avgBalance < 2000) {
            $score -= 15;
            $flags[] = 'Low average daily balance';
        }

        // Minimum balance scoring
        if ($minBalance >= 5000) {
            $score += 10;
        } elseif ($minBalance >= 1000) {
            $score += 5;
        } elseif ($minBalance < 0) {
            $score -= 15;
            $flags[] = 'Account went negative';
        } elseif ($minBalance < 500) {
            $score -= 10;
            $flags[] = 'Very low minimum balance';
        }

        // Negative days penalty
        if ($negativeDays > 10) {
            $score -= 20;
            $flags[] = "Account negative for {$negativeDays} days";
        } elseif ($negativeDays > 5) {
            $score -= 10;
            $flags[] = "Account negative for {$negativeDays} days";
        } elseif ($negativeDays > 0) {
            $score -= 5;
        }

        return [
            'score' => max(0, min(100, $score)),
            'avg_balance' => round($avgBalance, 2),
            'min_balance' => round($minBalance, 2),
            'max_balance' => round($maxBalance, 2),
            'negative_days' => $negativeDays,
            'total_balance_entries' => count($balances),
            'flags' => $flags,
        ];
    }

    /**
     * Calculate Transaction Pattern Score (0-100)
     */
    protected function calculatePatternScore(array $transactions): array
    {
        if (empty($transactions)) {
            return ['score' => 50, 'transaction_count' => 0, 'daily_avg' => 0, 'flags' => []];
        }

        $totalCount = count($transactions);
        $credits = array_filter($transactions, fn ($t) => ($t['type'] ?? '') === 'credit');
        $debits = array_filter($transactions, fn ($t) => ($t['type'] ?? '') === 'debit');

        // Get date range
        $dates = array_column($transactions, 'date');
        $dates = array_filter($dates);
        if (count($dates) >= 2) {
            sort($dates);
            $firstDate = new \DateTime($dates[0]);
            $lastDate = new \DateTime($dates[count($dates) - 1]);
            $daySpan = max(1, $firstDate->diff($lastDate)->days);
        } else {
            $daySpan = 30; // Default
        }

        $dailyAvg = $totalCount / $daySpan;
        $creditCount = count($credits);
        $debitCount = count($debits);

        $flags = [];
        $score = 50;

        // Transaction volume scoring
        if ($totalCount >= 100) {
            $score += 15;
        } elseif ($totalCount >= 50) {
            $score += 10;
        } elseif ($totalCount >= 25) {
            $score += 5;
        } elseif ($totalCount < 10) {
            $score -= 10;
            $flags[] = 'Very low transaction volume';
        }

        // Transaction frequency (daily avg)
        if ($dailyAvg >= 3) {
            $score += 15;
        } elseif ($dailyAvg >= 1.5) {
            $score += 10;
        } elseif ($dailyAvg >= 0.5) {
            $score += 5;
        } elseif ($dailyAvg < 0.3) {
            $score -= 10;
            $flags[] = 'Low transaction frequency';
        }

        // Credit/Debit ratio
        if ($creditCount > 0 && $debitCount > 0) {
            $ratio = $debitCount / $creditCount;
            if ($ratio >= 1 && $ratio <= 3) {
                $score += 10; // Healthy spending pattern
            } elseif ($ratio > 5) {
                $score -= 5;
                $flags[] = 'Very high debit to credit ratio';
            }
        }

        // Weekly consistency check
        $weeklyActivity = $this->analyzeWeeklyActivity($transactions);
        if ($weeklyActivity['consistency'] >= 0.7) {
            $score += 10;
        } elseif ($weeklyActivity['consistency'] < 0.4) {
            $score -= 5;
            $flags[] = 'Inconsistent weekly transaction patterns';
        }

        return [
            'score' => max(0, min(100, $score)),
            'transaction_count' => $totalCount,
            'credit_count' => $creditCount,
            'debit_count' => $debitCount,
            'daily_avg' => round($dailyAvg, 2),
            'day_span' => $daySpan,
            'weekly_consistency' => $weeklyActivity['consistency'],
            'flags' => $flags,
        ];
    }

    /**
     * Calculate Risk Indicator Score (0-100, higher is better = less risk)
     */
    protected function calculateRiskIndicatorScore(array $transactions): array
    {
        $flags = [];
        $score = 80; // Start high, deduct for risks

        // NSF/Overdraft detection
        $nsfPatterns = ['nsf', 'overdraft', 'insufficient', 'return item', 'returned check', 'od fee'];
        $nsfCount = 0;
        $nsfTotal = 0;

        foreach ($transactions as $txn) {
            $desc = strtolower($txn['description'] ?? '');
            foreach ($nsfPatterns as $pattern) {
                if (strpos($desc, $pattern) !== false) {
                    $nsfCount++;
                    $nsfTotal += abs((float) ($txn['amount'] ?? 0));
                    break;
                }
            }
        }

        if ($nsfCount >= 5) {
            $score -= 30;
            $flags[] = "High NSF/overdraft frequency ({$nsfCount} occurrences)";
        } elseif ($nsfCount >= 3) {
            $score -= 20;
            $flags[] = "Multiple NSF/overdraft occurrences ({$nsfCount})";
        } elseif ($nsfCount > 0) {
            $score -= 10;
            $flags[] = "NSF/overdraft detected ({$nsfCount})";
        }

        // MCA/Loan payment detection
        $mcaPatterns = ['mca', 'merchant cash', 'bizfi', 'ondeck', 'kabbage', 'can capital', 'credibly', 'rapid', 'yellowstone'];
        $mcaPayments = 0;
        $mcaTotal = 0;

        foreach ($transactions as $txn) {
            $desc = strtolower($txn['description'] ?? '');
            $type = $txn['type'] ?? '';
            if ($type === 'debit') {
                foreach ($mcaPatterns as $pattern) {
                    if (strpos($desc, $pattern) !== false) {
                        $mcaPayments++;
                        $mcaTotal += abs((float) ($txn['amount'] ?? 0));
                        break;
                    }
                }
            }
        }

        if ($mcaPayments >= 20) {
            $score -= 25;
            $flags[] = 'Multiple active MCA positions detected';
        } elseif ($mcaPayments >= 10) {
            $score -= 15;
            $flags[] = 'MCA payments detected';
        } elseif ($mcaPayments > 0) {
            $score -= 5;
            $flags[] = "MCA activity detected ({$mcaPayments} payments)";
        }

        // Large unusual withdrawals
        $largeWithdrawals = 0;
        foreach ($transactions as $txn) {
            $amount = abs((float) ($txn['amount'] ?? 0));
            $type = $txn['type'] ?? '';
            if ($type === 'debit' && $amount >= 10000) {
                $largeWithdrawals++;
            }
        }

        if ($largeWithdrawals >= 5) {
            $score -= 10;
            $flags[] = 'Frequent large withdrawals';
        }

        // Gambling/high-risk patterns
        $gamblingPatterns = ['casino', 'gambling', 'poker', 'lottery', 'draft king', 'fanduel', 'bet365'];
        $gamblingFound = false;
        foreach ($transactions as $txn) {
            $desc = strtolower($txn['description'] ?? '');
            foreach ($gamblingPatterns as $pattern) {
                if (strpos($desc, $pattern) !== false) {
                    $gamblingFound = true;
                    break 2;
                }
            }
        }

        if ($gamblingFound) {
            $score -= 15;
            $flags[] = 'Gambling-related transactions detected';
        }

        return [
            'score' => max(0, min(100, $score)),
            'nsf_count' => $nsfCount,
            'nsf_total' => round($nsfTotal, 2),
            'mca_payments' => $mcaPayments,
            'mca_total' => round($mcaTotal, 2),
            'large_withdrawals' => $largeWithdrawals,
            'gambling_detected' => $gamblingFound,
            'flags' => $flags,
        ];
    }

    /**
     * Calculate consistency of values
     */
    protected function calculateConsistency(array $values): float
    {
        if (count($values) < 2) {
            return 1.0;
        }

        $values = array_values($values);
        $avg = array_sum($values) / count($values);

        if ($avg == 0) {
            return 0;
        }

        $variance = 0;
        foreach ($values as $val) {
            $variance += pow($val - $avg, 2);
        }
        $stdDev = sqrt($variance / count($values));

        // Coefficient of variation (lower is more consistent)
        $cv = $stdDev / abs($avg);

        // Convert to 0-1 scale (1 = very consistent)
        return max(0, min(1, 1 - ($cv / 2)));
    }

    /**
     * Get monthly net flows from transactions
     */
    protected function getMonthlyNetFlows(array $transactions): array
    {
        $monthlyFlows = [];

        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (! $date) {
                continue;
            }

            $month = substr($date, 0, 7); // YYYY-MM format
            if (! isset($monthlyFlows[$month])) {
                $monthlyFlows[$month] = 0;
            }

            $amount = (float) ($txn['amount'] ?? 0);
            $type = $txn['type'] ?? '';

            if ($type === 'credit') {
                $monthlyFlows[$month] += $amount;
            } elseif ($type === 'debit') {
                $monthlyFlows[$month] -= $amount;
            }
        }

        return $monthlyFlows;
    }

    /**
     * Analyze weekly transaction activity
     */
    protected function analyzeWeeklyActivity(array $transactions): array
    {
        $weeklyTotals = [];

        foreach ($transactions as $txn) {
            $date = $txn['date'] ?? null;
            if (! $date) {
                continue;
            }

            $weekNum = date('W', strtotime($date)).'-'.date('Y', strtotime($date));
            if (! isset($weeklyTotals[$weekNum])) {
                $weeklyTotals[$weekNum] = 0;
            }
            $weeklyTotals[$weekNum]++;
        }

        return [
            'weeks' => count($weeklyTotals),
            'consistency' => $this->calculateConsistency(array_values($weeklyTotals)),
        ];
    }

    /**
     * Determine underwriting decision based on score and details
     */
    protected function determineDecision(int $score, array $details): array
    {
        $criticalFlags = [];

        // Check for critical issues
        if (($details['risk_indicators']['nsf_count'] ?? 0) >= 5) {
            $criticalFlags[] = 'High NSF frequency';
        }
        if (($details['risk_indicators']['mca_payments'] ?? 0) >= 20) {
            $criticalFlags[] = 'Heavy MCA stacking';
        }
        if (($details['balance_quality']['negative_days'] ?? 0) >= 10) {
            $criticalFlags[] = 'Frequent negative balance';
        }

        // Apply critical flag penalty
        if (! empty($criticalFlags)) {
            $score = min($score, 50);
        }

        if ($score >= $this->thresholds['auto_approve']) {
            return [
                'recommendation' => 'APPROVE',
                'type' => 'auto',
                'confidence' => 'high',
                'message' => 'Strong financial indicators support approval',
                'tier' => 1,
            ];
        }

        if ($score >= $this->thresholds['conditional_approve']) {
            return [
                'recommendation' => 'CONDITIONAL_APPROVE',
                'type' => 'conditional',
                'confidence' => 'medium',
                'message' => 'Acceptable risk profile with some concerns',
                'tier' => 2,
                'conditions' => $this->suggestConditions($details),
            ];
        }

        if ($score >= $this->thresholds['manual_review']) {
            return [
                'recommendation' => 'REVIEW',
                'type' => 'manual',
                'confidence' => 'low',
                'message' => 'Requires manual underwriter review',
                'tier' => 3,
                'review_areas' => $this->identifyReviewAreas($details),
            ];
        }

        if ($score >= $this->thresholds['decline']) {
            return [
                'recommendation' => 'HIGH_RISK',
                'type' => 'escalate',
                'confidence' => 'low',
                'message' => 'High risk - senior review required',
                'tier' => 4,
            ];
        }

        return [
            'recommendation' => 'DECLINE',
            'type' => 'auto',
            'confidence' => 'high',
            'message' => 'Does not meet minimum underwriting criteria',
            'tier' => 5,
            'critical_flags' => $criticalFlags,
        ];
    }

    /**
     * Suggest conditions for conditional approval
     */
    protected function suggestConditions(array $details): array
    {
        $conditions = [];

        if (($details['risk_indicators']['mca_payments'] ?? 0) > 0) {
            $conditions[] = 'Verify payoff of existing MCA positions';
        }

        if (($details['true_revenue']['revenue_ratio'] ?? 1) < 0.8) {
            $conditions[] = 'Additional revenue documentation required';
        }

        if (($details['balance_quality']['avg_balance'] ?? 0) < 5000) {
            $conditions[] = 'Require higher reserve or holdback';
        }

        return $conditions;
    }

    /**
     * Identify areas requiring manual review
     */
    protected function identifyReviewAreas(array $details): array
    {
        $areas = [];

        if (($details['true_revenue']['consistency'] ?? 1) < 0.6) {
            $areas[] = 'Revenue consistency analysis';
        }

        if (($details['cash_flow']['negative_months'] ?? 0) > 1) {
            $areas[] = 'Cash flow patterns';
        }

        if (($details['risk_indicators']['nsf_count'] ?? 0) > 0) {
            $areas[] = 'NSF/overdraft history';
        }

        if (($details['transaction_patterns']['weekly_consistency'] ?? 1) < 0.5) {
            $areas[] = 'Transaction pattern irregularities';
        }

        return $areas;
    }

    /**
     * Compile all flags from component details
     */
    protected function compileFlags(array $details): array
    {
        $flags = [];

        foreach ($details as $component => $detail) {
            if (isset($detail['flags']) && is_array($detail['flags'])) {
                foreach ($detail['flags'] as $flag) {
                    $flags[] = [
                        'component' => $component,
                        'message' => $flag,
                        'severity' => $this->determineFlagSeverity($flag),
                    ];
                }
            }
        }

        // Sort by severity
        usort($flags, function ($a, $b) {
            $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return ($order[$a['severity']] ?? 4) <=> ($order[$b['severity']] ?? 4);
        });

        return $flags;
    }

    /**
     * Determine flag severity
     */
    protected function determineFlagSeverity(string $flag): string
    {
        $flag = strtolower($flag);

        $critical = ['mca positions', 'gambling', 'high nsf'];
        $high = ['negative', 'nsf', 'overdraft', 'significantly below'];
        $medium = ['low', 'inconsistent', 'multiple'];

        foreach ($critical as $term) {
            if (strpos($flag, $term) !== false) {
                return 'critical';
            }
        }
        foreach ($high as $term) {
            if (strpos($flag, $term) !== false) {
                return 'high';
            }
        }
        foreach ($medium as $term) {
            if (strpos($flag, $term) !== false) {
                return 'medium';
            }
        }

        return 'low';
    }

    /**
     * Generate human-readable summary
     */
    protected function generateSummary(int $score, array $decision, array $flags, array $scores): string
    {
        $summary = "UNDERWRITING DECISION SCORE: {$score}/100\n";
        $summary .= "Recommendation: {$decision['recommendation']} ({$decision['confidence']} confidence)\n\n";

        $summary .= "Component Breakdown:\n";
        $componentNames = [
            'true_revenue' => 'True Revenue',
            'cash_flow' => 'Cash Flow',
            'balance_quality' => 'Balance Quality',
            'transaction_patterns' => 'Transaction Patterns',
            'risk_indicators' => 'Risk Indicators',
        ];

        foreach ($scores as $key => $componentScore) {
            $name = $componentNames[$key] ?? $key;
            $weight = $this->weights[$key] * 100;
            $summary .= "  - {$name}: {$componentScore}/100 (weight: {$weight}%)\n";
        }

        if (! empty($flags)) {
            $summary .= "\nKey Findings (".count($flags)."):\n";
            foreach (array_slice($flags, 0, 5) as $flag) {
                $severity = strtoupper($flag['severity']);
                $summary .= "  [{$severity}] {$flag['message']}\n";
            }
        }

        $summary .= "\n{$decision['message']}";

        return $summary;
    }

    /**
     * Calculate and save underwriting score for application
     */
    public function calculateAndSave(MCAApplication $application): array
    {
        $result = $this->calculateScore($application);

        if ($result['success']) {
            $application->update([
                'underwriting_score' => $result['score'],
                'underwriting_decision' => $result['decision'],
                'underwriting_details' => [
                    'component_scores' => $result['component_scores'],
                    'component_details' => $result['component_details'],
                    'flags' => $result['flags'],
                    'decision_details' => $result['decision_details'],
                    'summary' => $result['summary'],
                ],
                'underwriting_calculated_at' => now(),
            ]);

            Log::info("Underwriting score calculated for Application #{$application->id}: {$result['score']}/100 - {$result['decision']}");
        }

        return $result;
    }
}
