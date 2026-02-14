<?php

namespace App\Services;

class BankStatementAnalyzerService
{
    protected TransactionParserService $parser;

    public function __construct(TransactionParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Perform comprehensive bank statement analysis
     */
    public function analyze(array $transactions, array $metadata = []): array
    {
        $monthlyData = $this->parser->getMonthlyBreakdown($transactions);

        return [
            'revenue_analysis' => $this->analyzeRevenue($transactions, $monthlyData),
            'cash_flow_analysis' => $this->analyzeCashFlow($transactions, $monthlyData),
            'balance_analysis' => $this->analyzeBalances($transactions, $metadata),
            'trend_analysis' => $this->analyzeTrends($monthlyData),
            'seasonality' => $this->analyzeSeasonality($monthlyData),
            'mca_exposure' => $this->analyzeMCAExposure($transactions, $monthlyData),
            'risk_indicators' => $this->calculateRiskIndicators($transactions, $monthlyData),
            'scoring' => $this->calculateBankScore($transactions, $monthlyData, $metadata),
        ];
    }

    /**
     * Analyze revenue patterns
     */
    protected function analyzeRevenue(array $transactions, array $monthlyData): array
    {
        $revenues = array_column($monthlyData, 'revenue');
        $monthCount = count($revenues);

        if ($monthCount === 0) {
            return [
                'average_monthly' => 0,
                'total' => 0,
                'consistency_score' => 0,
                'trend' => 'insufficient_data',
                'volatility' => 0,
            ];
        }

        $total = array_sum($revenues);
        $average = $total / $monthCount;
        $stdDev = $this->standardDeviation($revenues);
        $volatility = $average > 0 ? ($stdDev / $average) * 100 : 0;

        // Calculate consistency score (0-100)
        $consistencyScore = 100 - min(100, $volatility);

        // Determine trend
        $trend = $this->calculateTrend($revenues);

        return [
            'average_monthly' => round($average, 2),
            'total' => round($total, 2),
            'min_monthly' => round(min($revenues), 2),
            'max_monthly' => round(max($revenues), 2),
            'consistency_score' => round($consistencyScore, 2),
            'trend' => $trend['direction'],
            'trend_percentage' => round($trend['percentage'], 2),
            'volatility' => round($volatility, 2),
            'monthly_breakdown' => $revenues,
        ];
    }

    /**
     * Analyze cash flow patterns
     */
    protected function analyzeCashFlow(array $transactions, array $monthlyData): array
    {
        $netFlows = [];
        foreach ($monthlyData as $month) {
            $netFlows[] = $month['credits'] - $month['debits'];
        }

        $monthCount = count($netFlows);
        if ($monthCount === 0) {
            return [
                'average_net_flow' => 0,
                'positive_months' => 0,
                'negative_months' => 0,
                'burn_rate' => 0,
            ];
        }

        $positiveMonths = count(array_filter($netFlows, fn ($v) => $v > 0));
        $negativeMonths = count(array_filter($netFlows, fn ($v) => $v < 0));

        $totalDebits = array_sum(array_column($monthlyData, 'debits'));
        $burnRate = $monthCount > 0 ? $totalDebits / $monthCount : 0;

        return [
            'average_net_flow' => round(array_sum($netFlows) / $monthCount, 2),
            'total_net_flow' => round(array_sum($netFlows), 2),
            'positive_months' => $positiveMonths,
            'negative_months' => $negativeMonths,
            'positive_ratio' => round($positiveMonths / $monthCount * 100, 2),
            'burn_rate' => round($burnRate, 2),
            'monthly_net_flows' => array_map(fn ($v) => round($v, 2), $netFlows),
        ];
    }

    /**
     * Analyze daily balances
     */
    protected function analyzeBalances(array $transactions, array $metadata): array
    {
        if (empty($transactions)) {
            return [
                'average_daily_balance' => 0,
                'min_balance' => 0,
                'negative_days' => 0,
                'low_balance_days' => 0,
            ];
        }

        // Calculate running balance
        $startBalance = $metadata['beginning_balance'] ?? 0;
        $runningBalance = $startBalance;
        $balances = [$runningBalance];
        $negativeCount = 0;
        $lowBalanceCount = 0;
        $lowBalanceThreshold = 500;

        foreach ($transactions as $txn) {
            if ($txn['type'] === 'credit') {
                $runningBalance += $txn['amount'];
            } else {
                $runningBalance -= $txn['amount'];
            }
            $balances[] = $runningBalance;

            if ($runningBalance < 0) {
                $negativeCount++;
            }
            if ($runningBalance < $lowBalanceThreshold) {
                $lowBalanceCount++;
            }
        }

        // Use average daily balance from statement if available, otherwise calculate
        if (isset($metadata['average_daily_balance']) && $metadata['average_daily_balance'] !== null) {
            $avgBalance = $metadata['average_daily_balance'];
        } else {
            $avgBalance = array_sum($balances) / count($balances);
        }

        $minBalance = min($balances);
        $maxBalance = max($balances);

        return [
            'beginning_balance' => round($startBalance, 2),
            'ending_balance' => round($runningBalance, 2),
            'average_daily_balance' => round($avgBalance, 2),
            'min_balance' => round($minBalance, 2),
            'max_balance' => round($maxBalance, 2),
            'negative_days' => $negativeCount,
            'low_balance_days' => $lowBalanceCount,
            'balance_volatility' => round($this->standardDeviation($balances), 2),
        ];
    }

    /**
     * Analyze revenue and cash flow trends
     */
    protected function analyzeTrends(array $monthlyData): array
    {
        if (count($monthlyData) < 2) {
            return [
                'revenue_trend' => 'insufficient_data',
                'cash_flow_trend' => 'insufficient_data',
                'transaction_volume_trend' => 'insufficient_data',
            ];
        }

        $revenues = array_column($monthlyData, 'revenue');
        $credits = array_column($monthlyData, 'credits');
        $debits = array_column($monthlyData, 'debits');
        $netFlows = array_map(fn ($c, $d) => $c - $d, $credits, $debits);
        $txnCounts = array_column($monthlyData, 'transaction_count');

        $revenueTrend = $this->calculateTrend($revenues);
        $cashFlowTrend = $this->calculateTrend($netFlows);
        $volumeTrend = $this->calculateTrend($txnCounts);

        // Calculate month-over-month growth rates
        $momGrowth = [];
        for ($i = 1; $i < count($revenues); $i++) {
            if ($revenues[$i - 1] > 0) {
                $momGrowth[] = (($revenues[$i] - $revenues[$i - 1]) / $revenues[$i - 1]) * 100;
            }
        }

        return [
            'revenue_trend' => $revenueTrend,
            'cash_flow_trend' => $cashFlowTrend,
            'transaction_volume_trend' => $volumeTrend,
            'mom_growth_rates' => array_map(fn ($v) => round($v, 2), $momGrowth),
            'average_mom_growth' => ! empty($momGrowth) ? round(array_sum($momGrowth) / count($momGrowth), 2) : 0,
            'months_analyzed' => count($monthlyData),
        ];
    }

    /**
     * Analyze seasonality patterns
     */
    protected function analyzeSeasonality(array $monthlyData): array
    {
        if (count($monthlyData) < 6) {
            return [
                'has_seasonality' => false,
                'pattern' => 'insufficient_data',
                'peak_months' => [],
                'low_months' => [],
            ];
        }

        $revenues = array_column($monthlyData, 'revenue');
        $months = array_column($monthlyData, 'month');

        // Calculate average and identify peaks/lows
        $average = array_sum($revenues) / count($revenues);
        $peaks = [];
        $lows = [];

        foreach ($monthlyData as $i => $data) {
            $deviation = ($data['revenue'] - $average) / ($average > 0 ? $average : 1) * 100;
            if ($deviation > 20) {
                $peaks[] = ['month' => $data['month'], 'deviation' => round($deviation, 2)];
            } elseif ($deviation < -20) {
                $lows[] = ['month' => $data['month'], 'deviation' => round($deviation, 2)];
            }
        }

        $hasSeasonality = ! empty($peaks) || ! empty($lows);
        $pattern = 'stable';
        if (count($peaks) > count($revenues) * 0.3) {
            $pattern = 'highly_variable';
        } elseif (! empty($peaks) && ! empty($lows)) {
            $pattern = 'seasonal';
        }

        return [
            'has_seasonality' => $hasSeasonality,
            'pattern' => $pattern,
            'peak_months' => $peaks,
            'low_months' => $lows,
            'coefficient_of_variation' => round($this->coefficientOfVariation($revenues), 2),
        ];
    }

    /**
     * Analyze MCA exposure from bank statements
     */
    protected function analyzeMCAExposure(array $transactions, array $monthlyData): array
    {
        $mcaTransactions = array_filter($transactions, fn ($t) => $t['is_mca_related']);
        $mcaDebits = array_filter($mcaTransactions, fn ($t) => $t['type'] === 'debit');
        $mcaCredits = array_filter($mcaTransactions, fn ($t) => $t['type'] === 'credit');

        $totalMCAPayments = array_sum(array_column($mcaDebits, 'amount'));
        $totalMCAFunding = array_sum(array_column($mcaCredits, 'amount'));

        $monthlyMCAPayments = array_column($monthlyData, 'mca_payments');
        $avgMCAPayment = ! empty($monthlyMCAPayments) ? array_sum($monthlyMCAPayments) / count($monthlyMCAPayments) : 0;

        // Estimate daily MCA payment
        $avgDailyMCA = count($mcaDebits) > 0 ? array_sum(array_column($mcaDebits, 'amount')) / count($mcaDebits) : 0;

        // Calculate MCA burden ratio
        $totalRevenue = array_sum(array_column($monthlyData, 'revenue'));
        $mcaBurdenRatio = $totalRevenue > 0 ? ($totalMCAPayments / $totalRevenue) * 100 : 0;

        // Detect multiple funders
        $funderPatterns = [];
        foreach ($mcaTransactions as $txn) {
            $desc = strtolower($txn['description']);
            // Extract potential funder name
            $words = explode(' ', $desc);
            if (count($words) >= 2) {
                $potentialFunder = $words[0].' '.$words[1];
                $funderPatterns[$potentialFunder] = ($funderPatterns[$potentialFunder] ?? 0) + 1;
            }
        }
        $estimatedFunders = count(array_filter($funderPatterns, fn ($c) => $c >= 3));

        return [
            'total_mca_payments' => round($totalMCAPayments, 2),
            'total_mca_funding' => round($totalMCAFunding, 2),
            'average_monthly_mca' => round($avgMCAPayment, 2),
            'estimated_daily_payment' => round($avgDailyMCA, 2),
            'mca_transaction_count' => count($mcaTransactions),
            'mca_burden_ratio' => round($mcaBurdenRatio, 2),
            'estimated_active_funders' => $estimatedFunders,
            'risk_level' => $this->getMCAExposureRiskLevel($mcaBurdenRatio, $estimatedFunders),
        ];
    }

    /**
     * Calculate risk indicators from bank data
     */
    protected function calculateRiskIndicators(array $transactions, array $monthlyData): array
    {
        $indicators = [];

        // NSF/Overdraft frequency
        $nsfCount = array_sum(array_column($monthlyData, 'nsf_count'));
        $monthCount = count($monthlyData);
        $nsfFrequency = $monthCount > 0 ? $nsfCount / $monthCount : 0;
        $indicators['nsf_frequency'] = [
            'value' => round($nsfFrequency, 2),
            'total_count' => $nsfCount,
            'risk' => $nsfFrequency > 2 ? 'high' : ($nsfFrequency > 0.5 ? 'medium' : 'low'),
        ];

        // Revenue decline detection
        $revenues = array_column($monthlyData, 'revenue');
        if (count($revenues) >= 3) {
            $recentAvg = array_sum(array_slice($revenues, -3)) / 3;
            $olderAvg = array_sum(array_slice($revenues, 0, -3)) / max(1, count($revenues) - 3);
            $declinePercent = $olderAvg > 0 ? (($olderAvg - $recentAvg) / $olderAvg) * 100 : 0;
            $indicators['revenue_decline'] = [
                'value' => round($declinePercent, 2),
                'risk' => $declinePercent > 30 ? 'high' : ($declinePercent > 15 ? 'medium' : 'low'),
            ];
        }

        // Cash flow stress
        $netFlows = [];
        foreach ($monthlyData as $month) {
            $netFlows[] = $month['credits'] - $month['debits'];
        }
        $negativeMonths = count(array_filter($netFlows, fn ($v) => $v < 0));
        $negativeRatio = $monthCount > 0 ? $negativeMonths / $monthCount : 0;
        $indicators['cash_flow_stress'] = [
            'negative_months' => $negativeMonths,
            'ratio' => round($negativeRatio * 100, 2),
            'risk' => $negativeRatio > 0.5 ? 'high' : ($negativeRatio > 0.25 ? 'medium' : 'low'),
        ];

        // Transaction volume volatility
        $txnCounts = array_column($monthlyData, 'transaction_count');
        $txnVolatility = ! empty($txnCounts) ? $this->coefficientOfVariation($txnCounts) : 0;
        $indicators['volume_volatility'] = [
            'value' => round($txnVolatility, 2),
            'risk' => $txnVolatility > 50 ? 'high' : ($txnVolatility > 25 ? 'medium' : 'low'),
        ];

        // Large deposit concentration
        $credits = array_filter($transactions, fn ($t) => $t['type'] === 'credit');
        if (! empty($credits)) {
            $amounts = array_column($credits, 'amount');
            $largestDeposit = max($amounts);
            $totalCredits = array_sum($amounts);
            $concentration = ($largestDeposit / $totalCredits) * 100;
            $indicators['deposit_concentration'] = [
                'largest_deposit' => round($largestDeposit, 2),
                'concentration_percent' => round($concentration, 2),
                'risk' => $concentration > 40 ? 'high' : ($concentration > 25 ? 'medium' : 'low'),
            ];
        }

        return $indicators;
    }

    /**
     * Calculate comprehensive bank analysis score
     */
    protected function calculateBankScore(array $transactions, array $monthlyData, array $metadata): array
    {
        $score = 50; // Base score
        $flags = [];
        $details = [];

        // Revenue consistency (max +20, min -15)
        $revenues = array_column($monthlyData, 'revenue');
        if (! empty($revenues)) {
            $consistency = 100 - min(100, $this->coefficientOfVariation($revenues));
            if ($consistency >= 80) {
                $score += 20;
                $details['revenue_consistency'] = 'excellent';
            } elseif ($consistency >= 60) {
                $score += 10;
                $details['revenue_consistency'] = 'good';
            } elseif ($consistency < 40) {
                $score -= 15;
                $details['revenue_consistency'] = 'poor';
                $flags[] = 'High revenue volatility';
            }
        }

        // Revenue trend (max +15, min -15)
        if (count($revenues) >= 3) {
            $trend = $this->calculateTrend($revenues);
            if ($trend['direction'] === 'increasing' && $trend['percentage'] > 10) {
                $score += 15;
                $details['revenue_trend'] = 'strong_growth';
            } elseif ($trend['direction'] === 'increasing') {
                $score += 8;
                $details['revenue_trend'] = 'growing';
            } elseif ($trend['direction'] === 'declining' && abs($trend['percentage']) > 20) {
                $score -= 15;
                $details['revenue_trend'] = 'significant_decline';
                $flags[] = 'Significant revenue decline detected';
            } elseif ($trend['direction'] === 'declining') {
                $score -= 8;
                $details['revenue_trend'] = 'declining';
                $flags[] = 'Revenue decline detected';
            }
        }

        // NSF/Overdraft history (max -25)
        $nsfCount = array_sum(array_column($monthlyData, 'nsf_count'));
        $score -= min(25, $nsfCount * 5);
        if ($nsfCount > 0) {
            $flags[] = "NSF/Overdraft occurrences: {$nsfCount}";
            $details['nsf_count'] = $nsfCount;
        }

        // MCA burden (max -20)
        $mcaExposure = $this->analyzeMCAExposure($transactions, $monthlyData);
        if ($mcaExposure['mca_burden_ratio'] > 30) {
            $score -= 20;
            $flags[] = 'High MCA payment burden (>'.round($mcaExposure['mca_burden_ratio']).'% of revenue)';
        } elseif ($mcaExposure['mca_burden_ratio'] > 15) {
            $score -= 10;
            $flags[] = 'Moderate MCA payment burden';
        }
        if ($mcaExposure['estimated_active_funders'] >= 3) {
            $score -= 10;
            $flags[] = 'Multiple active MCA positions detected';
        }
        $details['mca_burden'] = $mcaExposure;

        // Cash flow health (max +15, min -15)
        foreach ($monthlyData as $month) {
            $netFlows[] = $month['credits'] - $month['debits'];
        }
        if (! empty($netFlows)) {
            $positiveMonths = count(array_filter($netFlows, fn ($v) => $v > 0));
            $ratio = $positiveMonths / count($netFlows);
            if ($ratio >= 0.8) {
                $score += 15;
                $details['cash_flow_health'] = 'excellent';
            } elseif ($ratio >= 0.6) {
                $score += 8;
                $details['cash_flow_health'] = 'good';
            } elseif ($ratio < 0.4) {
                $score -= 15;
                $details['cash_flow_health'] = 'poor';
                $flags[] = 'Frequent negative cash flow months';
            }
        }

        // Average daily balance
        $avgBalance = $this->analyzeBalances($transactions, $metadata)['average_daily_balance'];
        if ($avgBalance >= 15000) {
            $score += 10;
            $details['balance_health'] = 'excellent';
        } elseif ($avgBalance >= 5000) {
            $score += 5;
            $details['balance_health'] = 'good';
        } elseif ($avgBalance < 1000) {
            $score -= 10;
            $details['balance_health'] = 'poor';
            $flags[] = 'Low average daily balance';
        }
        $details['average_daily_balance'] = $avgBalance;

        // Clamp score to 0-100
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'risk_level' => $this->getScoreRiskLevel($score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    /**
     * Calculate linear trend
     */
    protected function calculateTrend(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['direction' => 'stable', 'percentage' => 0, 'slope' => 0];
        }

        // Simple linear regression
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        $slope = $denominator != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominator : 0;

        $firstValue = $values[0] > 0 ? $values[0] : 1;
        $percentChange = ($slope * ($n - 1)) / $firstValue * 100;

        $direction = 'stable';
        if ($percentChange > 5) {
            $direction = 'increasing';
        } elseif ($percentChange < -5) {
            $direction = 'declining';
        }

        return [
            'direction' => $direction,
            'percentage' => $percentChange,
            'slope' => $slope,
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
     * Calculate coefficient of variation
     */
    protected function coefficientOfVariation(array $values): float
    {
        $mean = count($values) > 0 ? array_sum($values) / count($values) : 0;
        if ($mean == 0) {
            return 0;
        }

        $stdDev = $this->standardDeviation($values);

        return ($stdDev / abs($mean)) * 100;
    }

    /**
     * Get MCA exposure risk level
     */
    protected function getMCAExposureRiskLevel(float $burdenRatio, int $funderCount): string
    {
        if ($burdenRatio > 30 || $funderCount >= 4) {
            return 'high';
        }
        if ($burdenRatio > 15 || $funderCount >= 2) {
            return 'medium';
        }
        if ($burdenRatio > 5 || $funderCount >= 1) {
            return 'low';
        }

        return 'none';
    }

    /**
     * Get risk level from score
     */
    protected function getScoreRiskLevel(int $score): string
    {
        if ($score >= 80) {
            return 'low';
        }
        if ($score >= 60) {
            return 'medium-low';
        }
        if ($score >= 40) {
            return 'medium';
        }
        if ($score >= 20) {
            return 'medium-high';
        }

        return 'high';
    }
}
