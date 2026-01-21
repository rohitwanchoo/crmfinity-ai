<?php

namespace App\Services;

class PositionStackingOptimizer
{
    protected array $config;

    public function __construct()
    {
        $this->config = config('stacking_optimizer', $this->getDefaultConfig());
    }

    /**
     * Calculate optimal funding position considering existing MCAs
     */
    public function calculateOptimalPosition(array $data): array
    {
        $monthlyRevenue = $data['monthly_revenue'] ?? 0;
        $requestedAmount = $data['requested_amount'] ?? 0;
        $existingPositions = $data['existing_positions'] ?? [];
        $bankAnalysis = $data['bank_analysis'] ?? [];
        $riskScore = $data['risk_score'] ?? 50;

        // Calculate current MCA burden
        $currentBurden = $this->calculateCurrentBurden($existingPositions, $monthlyRevenue);

        // Calculate available capacity
        $capacity = $this->calculateFundingCapacity($monthlyRevenue, $currentBurden, $riskScore, $bankAnalysis);

        // Optimize position
        $optimalPosition = $this->optimizePosition($requestedAmount, $capacity, $existingPositions, $riskScore);

        // Calculate position details
        $positionDetails = $this->calculatePositionDetails($optimalPosition, $monthlyRevenue, $currentBurden);

        // Generate stacking analysis
        $stackingAnalysis = $this->analyzeStackingRisk($existingPositions, $optimalPosition, $monthlyRevenue);

        return [
            'current_state' => [
                'monthly_revenue' => $monthlyRevenue,
                'existing_positions' => count($existingPositions),
                'current_burden' => $currentBurden,
            ],
            'capacity' => $capacity,
            'optimal_position' => $optimalPosition,
            'position_details' => $positionDetails,
            'stacking_analysis' => $stackingAnalysis,
            'recommendation' => $this->generateRecommendation($optimalPosition, $stackingAnalysis),
        ];
    }

    /**
     * Calculate current MCA burden from existing positions
     */
    protected function calculateCurrentBurden(array $existingPositions, float $monthlyRevenue): array
    {
        if (empty($existingPositions) || $monthlyRevenue <= 0) {
            return [
                'total_daily_payment' => 0,
                'total_weekly_payment' => 0,
                'total_monthly_payment' => 0,
                'burden_ratio' => 0,
                'remaining_balance' => 0,
                'positions' => [],
            ];
        }

        $totalDaily = 0;
        $totalRemaining = 0;
        $positionDetails = [];

        foreach ($existingPositions as $position) {
            $dailyPayment = $position['daily_payment'] ?? 0;
            $remainingBalance = $position['remaining_balance'] ?? 0;
            $monthsRemaining = $dailyPayment > 0 ? ceil($remainingBalance / ($dailyPayment * 22)) : 0;

            $totalDaily += $dailyPayment;
            $totalRemaining += $remainingBalance;

            $positionDetails[] = [
                'funder' => $position['funder'] ?? 'Unknown',
                'daily_payment' => $dailyPayment,
                'remaining_balance' => $remainingBalance,
                'months_remaining' => $monthsRemaining,
                'factor_rate' => $position['factor_rate'] ?? null,
                'start_date' => $position['start_date'] ?? null,
            ];
        }

        $monthlyPayment = $totalDaily * 22;
        $burdenRatio = $monthlyRevenue > 0 ? ($monthlyPayment / $monthlyRevenue) * 100 : 0;

        return [
            'total_daily_payment' => round($totalDaily, 2),
            'total_weekly_payment' => round($totalDaily * 5, 2),
            'total_monthly_payment' => round($monthlyPayment, 2),
            'burden_ratio' => round($burdenRatio, 2),
            'remaining_balance' => round($totalRemaining, 2),
            'positions' => $positionDetails,
        ];
    }

    /**
     * Calculate available funding capacity
     */
    protected function calculateFundingCapacity(
        float $monthlyRevenue,
        array $currentBurden,
        int $riskScore,
        array $bankAnalysis
    ): array {
        // Base capacity is a percentage of monthly revenue
        $maxBurdenRatio = $this->getMaxBurdenRatio($riskScore);
        $currentBurdenRatio = $currentBurden['burden_ratio'];

        // Available burden capacity
        $availableBurdenRatio = max(0, $maxBurdenRatio - $currentBurdenRatio);

        // Calculate max additional monthly payment
        $maxAdditionalMonthly = ($availableBurdenRatio / 100) * $monthlyRevenue;
        $maxAdditionalDaily = $maxAdditionalMonthly / 22;

        // Factor in bank analysis if available
        $capacityMultiplier = 1.0;
        if (! empty($bankAnalysis)) {
            // Reduce capacity if poor cash flow
            if (isset($bankAnalysis['scoring']['score'])) {
                $bankScore = $bankAnalysis['scoring']['score'];
                if ($bankScore < 40) {
                    $capacityMultiplier = 0.5;
                } elseif ($bankScore < 60) {
                    $capacityMultiplier = 0.75;
                }
            }

            // Reduce for high NSF
            if (isset($bankAnalysis['risk_indicators']['nsf_frequency']['risk'])) {
                if ($bankAnalysis['risk_indicators']['nsf_frequency']['risk'] === 'high') {
                    $capacityMultiplier *= 0.7;
                }
            }
        }

        $adjustedMaxDaily = $maxAdditionalDaily * $capacityMultiplier;

        // Calculate funding amount based on typical factor rates
        $typicalFactorRate = $this->getTypicalFactorRate($riskScore);
        $typicalTermMonths = $this->getTypicalTerm($riskScore);
        $totalPayments = $typicalTermMonths * 22; // business days

        // Max funding = (daily payment * total payments) / factor rate
        $maxFundingAmount = ($adjustedMaxDaily * $totalPayments) / $typicalFactorRate;

        return [
            'max_burden_ratio' => $maxBurdenRatio,
            'available_burden_ratio' => round($availableBurdenRatio, 2),
            'max_additional_daily' => round($adjustedMaxDaily, 2),
            'max_additional_monthly' => round($adjustedMaxDaily * 22, 2),
            'max_funding_amount' => round($maxFundingAmount, 2),
            'capacity_multiplier' => $capacityMultiplier,
            'typical_factor_rate' => $typicalFactorRate,
            'typical_term_months' => $typicalTermMonths,
            'is_at_capacity' => $availableBurdenRatio < 5,
        ];
    }

    /**
     * Optimize the funding position
     */
    protected function optimizePosition(
        float $requestedAmount,
        array $capacity,
        array $existingPositions,
        int $riskScore
    ): array {
        // Cannot fund if at capacity
        if ($capacity['is_at_capacity']) {
            return [
                'can_fund' => false,
                'reason' => 'Merchant is at maximum MCA capacity',
                'recommended_amount' => 0,
                'position' => null,
            ];
        }

        // Calculate optimal amount
        $maxAmount = $capacity['max_funding_amount'];
        $optimalAmount = min($requestedAmount, $maxAmount);

        // Adjust for position count
        $positionCount = count($existingPositions);
        if ($positionCount >= 4) {
            return [
                'can_fund' => false,
                'reason' => 'Too many existing positions (4+)',
                'recommended_amount' => 0,
                'position' => null,
            ];
        }

        // Position adjustment based on stacking
        $positionAdjustment = 1 - ($positionCount * 0.1); // Reduce by 10% per existing position
        $optimalAmount *= $positionAdjustment;

        // Calculate terms based on risk
        $factorRate = $this->calculateFactorRate($riskScore, $positionCount);
        $termMonths = $this->calculateTermMonths($riskScore, $optimalAmount, $capacity['max_additional_daily']);

        // Calculate payments
        $paybackAmount = $optimalAmount * $factorRate;
        $dailyPayment = $paybackAmount / ($termMonths * 22);
        $holdbackPercentage = $this->calculateHoldback($riskScore, $positionCount);

        return [
            'can_fund' => $optimalAmount >= $this->config['minimum_funding_amount'],
            'reason' => $optimalAmount < $this->config['minimum_funding_amount']
                ? 'Calculated amount below minimum threshold'
                : 'Position optimized successfully',
            'recommended_amount' => round($optimalAmount, 2),
            'requested_amount' => $requestedAmount,
            'approval_percentage' => round(($optimalAmount / $requestedAmount) * 100, 2),
            'position' => [
                'funding_amount' => round($optimalAmount, 2),
                'factor_rate' => $factorRate,
                'payback_amount' => round($paybackAmount, 2),
                'term_months' => $termMonths,
                'daily_payment' => round($dailyPayment, 2),
                'weekly_payment' => round($dailyPayment * 5, 2),
                'holdback_percentage' => $holdbackPercentage,
                'position_number' => $positionCount + 1,
            ],
        ];
    }

    /**
     * Calculate position details and projections
     */
    protected function calculatePositionDetails(
        array $optimalPosition,
        float $monthlyRevenue,
        array $currentBurden
    ): array {
        if (! $optimalPosition['can_fund']) {
            return [
                'viable' => false,
                'reason' => $optimalPosition['reason'],
            ];
        }

        $position = $optimalPosition['position'];
        $newDailyPayment = $position['daily_payment'];
        $newMonthlyPayment = $newDailyPayment * 22;

        $totalDailyPayment = $currentBurden['total_daily_payment'] + $newDailyPayment;
        $totalMonthlyPayment = $currentBurden['total_monthly_payment'] + $newMonthlyPayment;

        $newBurdenRatio = $monthlyRevenue > 0 ? ($totalMonthlyPayment / $monthlyRevenue) * 100 : 0;

        // Calculate net cash available after all MCA payments
        $netCashAfterMCA = $monthlyRevenue - $totalMonthlyPayment;
        $netCashRatio = $monthlyRevenue > 0 ? ($netCashAfterMCA / $monthlyRevenue) * 100 : 0;

        // Payment schedule
        $paymentSchedule = $this->generatePaymentSchedule($position);

        return [
            'viable' => true,
            'new_burden' => [
                'total_daily_payment' => round($totalDailyPayment, 2),
                'total_monthly_payment' => round($totalMonthlyPayment, 2),
                'burden_ratio' => round($newBurdenRatio, 2),
                'burden_increase' => round($newBurdenRatio - $currentBurden['burden_ratio'], 2),
            ],
            'cash_flow_impact' => [
                'net_cash_after_mca' => round($netCashAfterMCA, 2),
                'net_cash_ratio' => round($netCashRatio, 2),
                'is_sustainable' => $netCashRatio >= 40, // At least 40% of revenue remains
            ],
            'payment_schedule' => $paymentSchedule,
            'break_even_months' => $position['term_months'],
        ];
    }

    /**
     * Analyze overall stacking risk
     */
    protected function analyzeStackingRisk(
        array $existingPositions,
        array $optimalPosition,
        float $monthlyRevenue
    ): array {
        $positionCount = count($existingPositions);
        $newPositionCount = $optimalPosition['can_fund'] ? $positionCount + 1 : $positionCount;

        // Calculate concentration risk
        $totalExposure = array_sum(array_column($existingPositions, 'remaining_balance'));
        if ($optimalPosition['can_fund']) {
            $totalExposure += $optimalPosition['position']['payback_amount'];
        }

        $exposureRatio = $monthlyRevenue > 0 ? $totalExposure / ($monthlyRevenue * 12) : 0;

        // Determine risk level
        $riskLevel = 'low';
        $riskScore = 100;
        $riskFactors = [];

        // Position count risk
        if ($newPositionCount >= 4) {
            $riskLevel = 'high';
            $riskScore -= 30;
            $riskFactors[] = 'Maximum position count reached';
        } elseif ($newPositionCount >= 3) {
            $riskLevel = max($riskLevel, 'medium');
            $riskScore -= 15;
            $riskFactors[] = 'Multiple active positions';
        }

        // Exposure ratio risk
        if ($exposureRatio > 1.5) {
            $riskLevel = 'high';
            $riskScore -= 25;
            $riskFactors[] = 'Exposure exceeds 18 months of revenue';
        } elseif ($exposureRatio > 1.0) {
            $riskLevel = max($riskLevel, 'medium');
            $riskScore -= 15;
            $riskFactors[] = 'Exposure exceeds annual revenue';
        }

        // Payment overlap analysis
        $overlapRisk = $this->analyzePaymentOverlap($existingPositions, $optimalPosition);
        if ($overlapRisk['high_overlap']) {
            $riskScore -= 15;
            $riskFactors[] = $overlapRisk['message'];
        }

        return [
            'risk_level' => $riskLevel,
            'risk_score' => max(0, $riskScore),
            'position_count' => $newPositionCount,
            'total_exposure' => round($totalExposure, 2),
            'exposure_ratio' => round($exposureRatio, 2),
            'risk_factors' => $riskFactors,
            'overlap_analysis' => $overlapRisk,
        ];
    }

    /**
     * Analyze payment overlap between positions
     */
    protected function analyzePaymentOverlap(array $existingPositions, array $optimalPosition): array
    {
        if (empty($existingPositions) || ! $optimalPosition['can_fund']) {
            return [
                'high_overlap' => false,
                'overlap_months' => 0,
                'message' => 'No overlap to analyze',
            ];
        }

        // Calculate when existing positions end
        $overlapMonths = 0;
        $newTermMonths = $optimalPosition['position']['term_months'];

        foreach ($existingPositions as $position) {
            $remainingMonths = 0;
            if (isset($position['remaining_balance']) && isset($position['daily_payment']) && $position['daily_payment'] > 0) {
                $remainingMonths = ceil($position['remaining_balance'] / ($position['daily_payment'] * 22));
            }

            // Overlap is the minimum of remaining and new term
            $overlapMonths += min($remainingMonths, $newTermMonths);
        }

        $highOverlap = $overlapMonths > $newTermMonths * count($existingPositions) * 0.75;

        return [
            'high_overlap' => $highOverlap,
            'overlap_months' => $overlapMonths,
            'message' => $highOverlap
                ? 'Significant payment overlap with existing positions'
                : 'Acceptable payment overlap',
        ];
    }

    /**
     * Generate payment schedule projection
     */
    protected function generatePaymentSchedule(array $position): array
    {
        $schedule = [];
        $balance = $position['payback_amount'];
        $dailyPayment = $position['daily_payment'];
        $monthlyPayment = $dailyPayment * 22;

        for ($month = 1; $month <= $position['term_months']; $month++) {
            $payment = min($monthlyPayment, $balance);
            $balance -= $payment;

            $schedule[] = [
                'month' => $month,
                'payment' => round($payment, 2),
                'remaining_balance' => round(max(0, $balance), 2),
            ];

            if ($balance <= 0) {
                break;
            }
        }

        return $schedule;
    }

    /**
     * Generate final recommendation
     */
    protected function generateRecommendation(array $optimalPosition, array $stackingAnalysis): array
    {
        if (! $optimalPosition['can_fund']) {
            return [
                'decision' => 'DECLINE',
                'confidence' => 'high',
                'reason' => $optimalPosition['reason'],
                'alternatives' => $this->getAlternatives($optimalPosition, $stackingAnalysis),
            ];
        }

        if ($stackingAnalysis['risk_level'] === 'high') {
            return [
                'decision' => 'DECLINE',
                'confidence' => 'high',
                'reason' => 'High stacking risk: '.implode(', ', $stackingAnalysis['risk_factors']),
                'alternatives' => $this->getAlternatives($optimalPosition, $stackingAnalysis),
            ];
        }

        if ($stackingAnalysis['risk_level'] === 'medium') {
            return [
                'decision' => 'APPROVE_WITH_CONDITIONS',
                'confidence' => 'medium',
                'reason' => 'Moderate stacking risk requires additional review',
                'conditions' => [
                    'Verify all existing MCA positions',
                    'Confirm recent bank statements',
                    'Consider reduced term',
                ],
                'offer' => $optimalPosition['position'],
            ];
        }

        return [
            'decision' => 'APPROVE',
            'confidence' => 'high',
            'reason' => 'Position is within acceptable parameters',
            'offer' => $optimalPosition['position'],
        ];
    }

    /**
     * Get alternative recommendations
     */
    protected function getAlternatives(array $optimalPosition, array $stackingAnalysis): array
    {
        $alternatives = [];

        if ($stackingAnalysis['position_count'] >= 3) {
            $alternatives[] = [
                'type' => 'buyout',
                'description' => 'Consider buying out existing positions',
            ];
        }

        if ($stackingAnalysis['exposure_ratio'] > 1.0) {
            $alternatives[] = [
                'type' => 'wait',
                'description' => 'Wait for existing positions to pay down',
            ];
        }

        $alternatives[] = [
            'type' => 'reduced_amount',
            'description' => 'Consider a smaller funding amount',
            'suggested_amount' => round($optimalPosition['recommended_amount'] * 0.5, 2),
        ];

        return $alternatives;
    }

    /**
     * Get maximum burden ratio based on risk score.
     *
     * CRITICAL: MCA industry standard is 20% maximum total withhold.
     * This is enforced as a HARD CAP regardless of risk score.
     * Previously this allowed up to 50% for low-risk merchants, which
     * created unsustainable payment obligations.
     *
     * The 20% cap ensures merchants retain at least 80% of their daily
     * revenue for operating expenses after all MCA payments.
     */
    protected function getMaxBurdenRatio(int $riskScore): float
    {
        // HARD CAP: Never exceed 20% total withhold (MCA industry standard)
        $hardCap = config('mca_pricing.max_withhold_percentage', 0.20) * 100;

        // Risk-based adjustments WITHIN the 20% cap
        // Lower risk = allowed closer to the cap
        // Higher risk = more conservative limit
        if ($riskScore >= 80) {
            return min($hardCap, 20); // Low risk - can use full 20%
        }
        if ($riskScore >= 60) {
            return min($hardCap, 18); // Medium-low risk - 18%
        }
        if ($riskScore >= 40) {
            return min($hardCap, 15); // Medium risk - 15%
        }

        return min($hardCap, 12); // High risk - only 12% allowed
    }

    /**
     * Get typical factor rate based on risk score
     */
    protected function getTypicalFactorRate(int $riskScore): float
    {
        if ($riskScore >= 80) {
            return 1.15;
        }
        if ($riskScore >= 60) {
            return 1.25;
        }
        if ($riskScore >= 40) {
            return 1.35;
        }

        return 1.45;
    }

    /**
     * Get typical term based on risk score
     */
    protected function getTypicalTerm(int $riskScore): int
    {
        if ($riskScore >= 80) {
            return 12;
        }
        if ($riskScore >= 60) {
            return 9;
        }
        if ($riskScore >= 40) {
            return 6;
        }

        return 4;
    }

    /**
     * Calculate factor rate considering stacking
     */
    protected function calculateFactorRate(int $riskScore, int $positionCount): float
    {
        $baseRate = $this->getTypicalFactorRate($riskScore);

        // Add premium for each existing position
        $stackingPremium = $positionCount * 0.03;

        return round($baseRate + $stackingPremium, 2);
    }

    /**
     * Calculate term months
     */
    protected function calculateTermMonths(int $riskScore, float $amount, float $maxDaily): int
    {
        $baseTerm = $this->getTypicalTerm($riskScore);

        // Adjust term if payment capacity requires it
        $factorRate = $this->getTypicalFactorRate($riskScore);
        $payback = $amount * $factorRate;
        $requiredPayments = $payback / $maxDaily;
        $requiredMonths = ceil($requiredPayments / 22);

        return min($baseTerm, max(3, (int) $requiredMonths));
    }

    /**
     * Calculate holdback percentage
     */
    protected function calculateHoldback(int $riskScore, int $positionCount): float
    {
        $baseHoldback = 0.10;

        if ($riskScore < 60) {
            $baseHoldback += 0.03;
        }
        if ($riskScore < 40) {
            $baseHoldback += 0.03;
        }

        // Add for stacking
        $baseHoldback += $positionCount * 0.02;

        return min(0.25, round($baseHoldback, 2)); // Cap at 25%
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfig(): array
    {
        return [
            'minimum_funding_amount' => 5000,
            'maximum_burden_ratio' => 50,
            'maximum_positions' => 4,
            'base_factor_rate' => 1.20,
            'stacking_premium_per_position' => 0.03,
        ];
    }

    /**
     * Buyout analysis for existing positions
     */
    public function analyzeBuyout(array $existingPositions, float $newFundingAmount): array
    {
        if (empty($existingPositions)) {
            return [
                'recommended' => false,
                'reason' => 'No existing positions to buy out',
            ];
        }

        $buyoutCandidates = [];
        $totalBuyoutCost = 0;

        foreach ($existingPositions as $position) {
            $remainingBalance = $position['remaining_balance'] ?? 0;
            $dailyPayment = $position['daily_payment'] ?? 0;
            $monthsRemaining = $dailyPayment > 0 ? ceil($remainingBalance / ($dailyPayment * 22)) : 0;

            // Calculate buyout discount (positions near completion are better candidates)
            $payoffPercentage = 1.0;
            if ($monthsRemaining <= 2) {
                $payoffPercentage = 0.95; // 5% discount for near-term
            } elseif ($monthsRemaining <= 4) {
                $payoffPercentage = 0.98;
            }

            $buyoutAmount = $remainingBalance * $payoffPercentage;
            $monthlySavings = $dailyPayment * 22;

            $buyoutCandidates[] = [
                'funder' => $position['funder'] ?? 'Unknown',
                'remaining_balance' => $remainingBalance,
                'buyout_amount' => round($buyoutAmount, 2),
                'monthly_savings' => round($monthlySavings, 2),
                'months_remaining' => $monthsRemaining,
                'priority' => $this->calculateBuyoutPriority($remainingBalance, $monthlySavings, $monthsRemaining),
            ];

            $totalBuyoutCost += $buyoutAmount;
        }

        // Sort by priority
        usort($buyoutCandidates, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        $recommended = $totalBuyoutCost <= $newFundingAmount * 0.7; // Recommend if buyout uses less than 70%

        return [
            'recommended' => $recommended,
            'reason' => $recommended
                ? 'Buyout would simplify position and reduce daily payment burden'
                : 'Buyout cost too high relative to new funding',
            'total_buyout_cost' => round($totalBuyoutCost, 2),
            'remaining_for_working_capital' => round($newFundingAmount - $totalBuyoutCost, 2),
            'candidates' => $buyoutCandidates,
        ];
    }

    /**
     * Calculate buyout priority score
     */
    protected function calculateBuyoutPriority(float $balance, float $monthlySavings, int $monthsRemaining): float
    {
        // Higher priority for: higher monthly savings, lower balance, more months remaining
        $savingsScore = min(100, ($monthlySavings / 1000) * 10);
        $balanceScore = max(0, 50 - ($balance / 10000) * 10);
        $termScore = min(50, $monthsRemaining * 5);

        return $savingsScore + $balanceScore + $termScore;
    }
}
