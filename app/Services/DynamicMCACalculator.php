<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * DynamicMCACalculator - MCA Offer Calculation with 20% Cap Enforcement
 *
 * This service calculates MCA offers dynamically based on:
 * - Monthly True Revenue (from TrueRevenueEngine)
 * - Existing MCA positions and daily payments
 * - Industry risk factors
 * - Credit score adjustments
 * - Revenue volatility
 *
 * CRITICAL RULE: Total daily remittance MUST NEVER exceed 20% of True Revenue
 *
 * Example calculation:
 * - Monthly True Revenue: $100,000
 * - Daily True Revenue: $4,615 (÷21.67 business days)
 * - Max 20% withhold: $923/day
 * - Existing MCA: $554/day (12%)
 * - Remaining capacity: $369/day (8%)
 * - Max new funding @ 1.45 factor / 6 months: $33,103
 */
class DynamicMCACalculator
{
    // Offer statuses
    public const STATUS_APPROVED = 'approved';
    public const STATUS_APPROVED_REDUCED = 'approved_reduced';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    // Decline reasons
    public const DECLINE_AT_CAPACITY = 'at_capacity';
    public const DECLINE_TOO_MANY_POSITIONS = 'too_many_positions';
    public const DECLINE_LOW_RISK_SCORE = 'low_risk_score';
    public const DECLINE_BELOW_MINIMUM = 'below_minimum';
    public const DECLINE_HIGH_VOLATILITY = 'high_volatility';

    protected array $config;
    protected float $maxWithholdPercentage;
    protected float $businessDaysPerMonth;

    public function __construct()
    {
        $this->config = config('mca_pricing', []);
        $this->maxWithholdPercentage = $this->config['max_withhold_percentage'] ?? 0.20;
        $this->businessDaysPerMonth = $this->config['business_days_per_month'] ?? 21.67;
    }

    /**
     * Calculate MCA offer with full breakdown and explanation
     *
     * @param array $input {
     *   @type float $monthly_true_revenue Monthly True Revenue
     *   @type float $existing_daily_payment Total existing MCA daily payments
     *   @type float $requested_amount Requested funding amount
     *   @type int $position Desired position (1, 2, 3, 4)
     *   @type int $term_months Desired term in months
     *   @type float $factor_rate Desired factor rate (optional, will calculate if not provided)
     *   @type string $industry Industry type
     *   @type int $credit_score Personal credit score
     *   @type int $risk_score Overall risk score (0-100)
     *   @type string $volatility_level Revenue volatility level (low, medium, high)
     * }
     * @return array Complete offer with breakdown and explanation
     */
    public function calculateOffer(array $input): array
    {
        // Extract inputs with defaults
        $monthlyTrueRevenue = (float) ($input['monthly_true_revenue'] ?? 0);
        $existingDailyPayment = (float) ($input['existing_daily_payment'] ?? 0);
        $requestedAmount = (float) ($input['requested_amount'] ?? 0);
        $position = (int) ($input['position'] ?? 1);
        $termMonths = (int) ($input['term_months'] ?? 6);
        $requestedFactorRate = isset($input['factor_rate']) ? (float) $input['factor_rate'] : null;
        $industry = $input['industry'] ?? null;
        $creditScore = (int) ($input['credit_score'] ?? 0);
        $riskScore = (int) ($input['risk_score'] ?? 50);
        $volatilityLevel = $input['volatility_level'] ?? 'medium';

        // Initialize result
        $result = [
            'status' => self::STATUS_DECLINED,
            'can_fund' => false,
            'input' => $input,
            'capacity' => [],
            'offer' => null,
            'adjustments' => [],
            'warnings' => [],
            'explanation' => '',
            'math_breakdown' => [],
        ];

        // Validate minimum inputs
        if ($monthlyTrueRevenue <= 0) {
            $result['decline_reason'] = 'Invalid or missing monthly revenue';
            $result['explanation'] = 'Cannot calculate offer without valid monthly True Revenue.';
            return $result;
        }

        // Step 1: Calculate capacity
        $capacity = $this->calculateCapacity($monthlyTrueRevenue, $existingDailyPayment, $industry);
        $result['capacity'] = $capacity;

        // Step 2: Check if at capacity
        if ($capacity['at_capacity']) {
            $result['decline_reason'] = self::DECLINE_AT_CAPACITY;
            $result['explanation'] = sprintf(
                'Merchant is at maximum withhold capacity. Current withhold: %.1f%% of %.1f%% maximum.',
                $capacity['current_withhold_percent'],
                $capacity['max_withhold_percent']
            );
            return $result;
        }

        // Step 3: Check position limit
        $maxPositions = $this->config['max_positions'] ?? 4;
        if ($position > $maxPositions) {
            $result['decline_reason'] = self::DECLINE_TOO_MANY_POSITIONS;
            $result['explanation'] = sprintf(
                'Position %d exceeds maximum allowed positions (%d).',
                $position,
                $maxPositions
            );
            return $result;
        }

        // Step 4: Get factor rate tier
        $tier = $this->getFactorRateTier($riskScore);
        $result['tier'] = $tier;

        // Step 5: Calculate adjusted factor rate
        $factorRate = $this->calculateAdjustedFactorRate(
            $tier,
            $position,
            $creditScore,
            $industry,
            $volatilityLevel
        );
        $result['adjustments']['factor_rate'] = $factorRate['adjustments'];

        // Use requested factor rate if valid and within bounds
        $finalFactorRate = $factorRate['final_rate'];
        if ($requestedFactorRate !== null) {
            $minRate = $this->config['validation']['factor_rate']['min'] ?? 1.10;
            $maxRate = $factorRate['max_rate'];
            if ($requestedFactorRate >= $minRate && $requestedFactorRate <= $maxRate) {
                $finalFactorRate = $requestedFactorRate;
            } else {
                $result['warnings'][] = sprintf(
                    'Requested factor rate %.2f adjusted to %.2f (valid range: %.2f - %.2f)',
                    $requestedFactorRate,
                    $finalFactorRate,
                    $minRate,
                    $maxRate
                );
            }
        }

        // Step 6: Calculate adjusted term
        $adjustedTerm = $this->calculateAdjustedTerm(
            $termMonths,
            $tier,
            $creditScore,
            $industry,
            $volatilityLevel
        );
        $result['adjustments']['term'] = $adjustedTerm['adjustments'];
        $finalTermMonths = $adjustedTerm['final_term'];

        // Step 7: Calculate maximum funding based on capacity
        $maxFunding = $this->calculateMaxFunding(
            $capacity['remaining_daily_capacity'],
            $finalTermMonths,
            $finalFactorRate
        );

        // Step 8: Calculate approved amount
        $approvalPercentage = $this->calculateApprovalPercentage(
            $tier,
            $position,
            $creditScore,
            $volatilityLevel
        );
        $result['adjustments']['approval'] = $approvalPercentage['adjustments'];

        // Maximum based on approval percentage of requested
        $maxByApproval = $requestedAmount * $approvalPercentage['final_percentage'];

        // Take the minimum of capacity-based max and approval-based max
        $approvedAmount = min($maxFunding['max_amount'], $maxByApproval);

        // Apply minimum funding threshold
        $minFunding = $this->config['minimum_funding_amount'] ?? 5000;
        if ($approvedAmount < $minFunding) {
            $result['decline_reason'] = self::DECLINE_BELOW_MINIMUM;
            $result['explanation'] = sprintf(
                'Calculated funding amount $%s is below minimum threshold of $%s.',
                number_format($approvedAmount, 2),
                number_format($minFunding, 2)
            );
            return $result;
        }

        // Step 9: Calculate final offer terms
        $paybackAmount = $approvedAmount * $finalFactorRate;
        $totalBusinessDays = $finalTermMonths * $this->businessDaysPerMonth;
        $dailyPayment = $paybackAmount / $totalBusinessDays;

        // Recalculate withhold with new offer
        $newTotalDailyPayment = $existingDailyPayment + $dailyPayment;
        $newWithholdPercent = ($newTotalDailyPayment / $capacity['daily_true_revenue']) * 100;

        // CRITICAL: Final validation - never exceed max withhold
        if ($newWithholdPercent > ($capacity['max_withhold_percent'])) {
            // Reduce daily payment to fit within cap
            $allowedDailyPayment = $capacity['remaining_daily_capacity'];
            $dailyPayment = $allowedDailyPayment;
            $paybackAmount = $dailyPayment * $totalBusinessDays;
            $approvedAmount = $paybackAmount / $finalFactorRate;

            // Recheck minimum
            if ($approvedAmount < $minFunding) {
                $result['decline_reason'] = self::DECLINE_AT_CAPACITY;
                $result['explanation'] = sprintf(
                    'After applying 20%% withhold cap, funding amount $%s is below minimum.',
                    number_format($approvedAmount, 2)
                );
                return $result;
            }

            $newTotalDailyPayment = $existingDailyPayment + $dailyPayment;
            $newWithholdPercent = ($newTotalDailyPayment / $capacity['daily_true_revenue']) * 100;

            $result['warnings'][] = 'Offer reduced to comply with 20% maximum withhold cap';
        }

        // Step 10: Calculate holdback percentage
        $holdback = $this->calculateHoldback($riskScore, $position, $industry);
        $result['adjustments']['holdback'] = $holdback['adjustments'];

        // Step 11: Build final offer
        $offer = [
            'funding_amount' => round($approvedAmount, 2),
            'factor_rate' => round($finalFactorRate, 4),
            'payback_amount' => round($paybackAmount, 2),
            'term_months' => $finalTermMonths,
            'term_business_days' => (int) round($totalBusinessDays),
            'daily_payment' => round($dailyPayment, 2),
            'weekly_payment' => round($dailyPayment * 5, 2),
            'monthly_payment' => round($dailyPayment * $this->businessDaysPerMonth, 2),
            'holdback_percentage' => round($holdback['final_holdback'] * 100, 2),
            'position' => $position,
            'cost_of_capital' => round(($paybackAmount - $approvedAmount), 2),
            'cost_percentage' => round((($paybackAmount / $approvedAmount) - 1) * 100, 2),
        ];

        // Withhold breakdown
        $withholdBreakdown = [
            'existing_daily_payment' => round($existingDailyPayment, 2),
            'existing_withhold_percent' => round($capacity['current_withhold_percent'], 2),
            'new_daily_payment' => round($dailyPayment, 2),
            'new_withhold_percent' => round(($dailyPayment / $capacity['daily_true_revenue']) * 100, 2),
            'total_daily_payment' => round($newTotalDailyPayment, 2),
            'total_withhold_percent' => round($newWithholdPercent, 2),
            'remaining_capacity_after' => round($capacity['max_daily_payment'] - $newTotalDailyPayment, 2),
            'remaining_percent_after' => round($capacity['max_withhold_percent'] - $newWithholdPercent, 2),
        ];
        $offer['withhold_breakdown'] = $withholdBreakdown;

        // Math breakdown for transparency
        $result['math_breakdown'] = [
            'step_1_revenue' => [
                'monthly_true_revenue' => round($monthlyTrueRevenue, 2),
                'business_days_per_month' => $this->businessDaysPerMonth,
                'daily_true_revenue' => round($capacity['daily_true_revenue'], 2),
                'formula' => 'Monthly Revenue ÷ Business Days',
            ],
            'step_2_capacity' => [
                'max_withhold_percent' => $capacity['max_withhold_percent'],
                'max_daily_payment' => round($capacity['max_daily_payment'], 2),
                'existing_daily_payment' => round($existingDailyPayment, 2),
                'remaining_daily_capacity' => round($capacity['remaining_daily_capacity'], 2),
                'formula' => 'Daily Revenue × Max Withhold % - Existing Payments',
            ],
            'step_3_max_funding' => [
                'remaining_daily_capacity' => round($capacity['remaining_daily_capacity'], 2),
                'term_business_days' => (int) round($totalBusinessDays),
                'factor_rate' => round($finalFactorRate, 4),
                'max_payback' => round($capacity['remaining_daily_capacity'] * $totalBusinessDays, 2),
                'max_funding' => round($maxFunding['max_amount'], 2),
                'formula' => 'Remaining Capacity × Term Days ÷ Factor Rate',
            ],
            'step_4_approved' => [
                'requested_amount' => round($requestedAmount, 2),
                'max_by_capacity' => round($maxFunding['max_amount'], 2),
                'approval_percentage' => round($approvalPercentage['final_percentage'] * 100, 2),
                'max_by_approval' => round($maxByApproval, 2),
                'approved_amount' => round($approvedAmount, 2),
                'formula' => 'MIN(Capacity Max, Request × Approval %)',
            ],
            'step_5_final' => [
                'approved_amount' => round($approvedAmount, 2),
                'factor_rate' => round($finalFactorRate, 4),
                'payback_amount' => round($paybackAmount, 2),
                'term_business_days' => (int) round($totalBusinessDays),
                'daily_payment' => round($dailyPayment, 2),
                'formula' => 'Funded × Factor Rate ÷ Term Days',
            ],
        ];

        // Determine status
        $status = self::STATUS_APPROVED;
        if ($approvedAmount < $requestedAmount * 0.99) {
            $status = self::STATUS_APPROVED_REDUCED;
        }

        // Build explanation
        $explanation = $this->buildExplanation($offer, $withholdBreakdown, $status, $requestedAmount);

        $result['status'] = $status;
        $result['can_fund'] = true;
        $result['offer'] = $offer;
        $result['explanation'] = $explanation;

        return $result;
    }

    /**
     * Calculate capacity based on True Revenue and existing positions
     */
    protected function calculateCapacity(
        float $monthlyTrueRevenue,
        float $existingDailyPayment,
        ?string $industry
    ): array {
        $dailyTrueRevenue = $monthlyTrueRevenue / $this->businessDaysPerMonth;

        // Check for industry-specific max withhold override
        $maxWithhold = $this->maxWithholdPercentage;
        if ($industry && isset($this->config['industry_adjustments'][$industry]['max_withhold_override'])) {
            $override = $this->config['industry_adjustments'][$industry]['max_withhold_override'];
            if ($override !== null) {
                $maxWithhold = min($maxWithhold, $override);
            }
        }

        $maxDailyPayment = $dailyTrueRevenue * $maxWithhold;
        $remainingCapacity = max(0, $maxDailyPayment - $existingDailyPayment);
        $currentWithhold = $dailyTrueRevenue > 0
            ? ($existingDailyPayment / $dailyTrueRevenue) * 100
            : 0;

        return [
            'monthly_true_revenue' => round($monthlyTrueRevenue, 2),
            'daily_true_revenue' => round($dailyTrueRevenue, 2),
            'max_withhold_percent' => round($maxWithhold * 100, 2),
            'max_daily_payment' => round($maxDailyPayment, 2),
            'existing_daily_payment' => round($existingDailyPayment, 2),
            'current_withhold_percent' => round($currentWithhold, 2),
            'remaining_daily_capacity' => round($remainingCapacity, 2),
            'remaining_withhold_percent' => round(($maxWithhold * 100) - $currentWithhold, 2),
            'at_capacity' => $remainingCapacity <= 0,
        ];
    }

    /**
     * Get factor rate tier based on risk score
     */
    protected function getFactorRateTier(int $riskScore): array
    {
        $tiers = $this->config['factor_rate_tiers'] ?? [];

        foreach ($tiers as $key => $tier) {
            if ($riskScore >= $tier['min_risk_score']) {
                return array_merge($tier, ['key' => $key]);
            }
        }

        // Default to highest risk tier
        $lastTier = end($tiers);
        return array_merge($lastTier, ['key' => array_key_last($tiers)]);
    }

    /**
     * Calculate adjusted factor rate with all adjustments
     */
    protected function calculateAdjustedFactorRate(
        array $tier,
        int $position,
        int $creditScore,
        ?string $industry,
        string $volatilityLevel
    ): array {
        $baseRate = $tier['base_factor_rate'];
        $maxRate = $tier['max_factor_rate'];
        $adjustments = [];

        // Position stacking adjustment
        $positionAdj = $this->config['stacking_adjustments']['position_adjustments'][$position]['factor_adjustment'] ?? 0;
        if ($positionAdj != 0) {
            $adjustments[] = [
                'type' => 'position',
                'description' => "Position $position adjustment",
                'value' => $positionAdj,
            ];
        }

        // Credit score adjustment
        $creditAdj = $this->getCreditScoreAdjustment($creditScore);
        if ($creditAdj['factor_adjustment'] != 0) {
            $adjustments[] = [
                'type' => 'credit_score',
                'description' => "{$creditAdj['tier']} credit ($creditScore)",
                'value' => $creditAdj['factor_adjustment'],
            ];
        }

        // Industry adjustment
        if ($industry && isset($this->config['industry_adjustments'][$industry])) {
            $industryAdj = $this->config['industry_adjustments'][$industry]['factor_adjustment'];
            if ($industryAdj != 0) {
                $adjustments[] = [
                    'type' => 'industry',
                    'description' => ucfirst(str_replace('_', ' ', $industry)),
                    'value' => $industryAdj,
                ];
            }
        }

        // Volatility adjustment
        if (isset($this->config['volatility_adjustments'][$volatilityLevel])) {
            $volAdj = $this->config['volatility_adjustments'][$volatilityLevel]['factor_adjustment'];
            if ($volAdj != 0) {
                $adjustments[] = [
                    'type' => 'volatility',
                    'description' => ucfirst($volatilityLevel) . ' volatility',
                    'value' => $volAdj,
                ];
            }
        }

        // Calculate final rate
        $totalAdjustment = array_sum(array_column($adjustments, 'value'));
        $finalRate = $baseRate + $totalAdjustment;

        // Clamp to valid range
        $minRate = $this->config['validation']['factor_rate']['min'] ?? 1.10;
        $finalRate = max($minRate, min($maxRate, $finalRate));

        return [
            'base_rate' => $baseRate,
            'max_rate' => $maxRate,
            'adjustments' => $adjustments,
            'total_adjustment' => round($totalAdjustment, 4),
            'final_rate' => round($finalRate, 4),
        ];
    }

    /**
     * Get credit score tier and adjustment
     */
    protected function getCreditScoreAdjustment(int $creditScore): array
    {
        $tiers = $this->config['credit_score_adjustments'] ?? [];

        foreach ($tiers as $tier => $data) {
            if ($creditScore >= $data['min_score']) {
                return array_merge($data, ['tier' => $tier]);
            }
        }

        $lastTier = end($tiers);
        return array_merge($lastTier, ['tier' => array_key_last($tiers)]);
    }

    /**
     * Calculate adjusted term with all adjustments
     */
    protected function calculateAdjustedTerm(
        int $requestedTerm,
        array $tier,
        int $creditScore,
        ?string $industry,
        string $volatilityLevel
    ): array {
        $maxTerm = $tier['max_term_months'];
        $adjustments = [];

        $termAdjustment = 0;

        // Credit score adjustment
        $creditAdj = $this->getCreditScoreAdjustment($creditScore);
        if ($creditAdj['term_adjustment'] != 0) {
            $termAdjustment += $creditAdj['term_adjustment'];
            $adjustments[] = [
                'type' => 'credit_score',
                'description' => "{$creditAdj['tier']} credit",
                'value' => $creditAdj['term_adjustment'],
            ];
        }

        // Industry adjustment
        if ($industry && isset($this->config['industry_adjustments'][$industry])) {
            $industryAdj = $this->config['industry_adjustments'][$industry]['term_adjustment'];
            if ($industryAdj != 0) {
                $termAdjustment += $industryAdj;
                $adjustments[] = [
                    'type' => 'industry',
                    'description' => ucfirst(str_replace('_', ' ', $industry)),
                    'value' => $industryAdj,
                ];
            }
        }

        // Volatility adjustment
        if (isset($this->config['volatility_adjustments'][$volatilityLevel])) {
            $volAdj = $this->config['volatility_adjustments'][$volatilityLevel]['term_adjustment'];
            if ($volAdj != 0) {
                $termAdjustment += $volAdj;
                $adjustments[] = [
                    'type' => 'volatility',
                    'description' => ucfirst($volatilityLevel) . ' volatility',
                    'value' => $volAdj,
                ];
            }
        }

        // Calculate adjusted max term
        $adjustedMaxTerm = max(2, $maxTerm + $termAdjustment);

        // Final term is minimum of requested and adjusted max
        $finalTerm = min($requestedTerm, $adjustedMaxTerm);
        $finalTerm = max(2, $finalTerm); // Minimum 2 months

        return [
            'requested_term' => $requestedTerm,
            'tier_max_term' => $maxTerm,
            'adjustments' => $adjustments,
            'total_adjustment' => $termAdjustment,
            'adjusted_max_term' => $adjustedMaxTerm,
            'final_term' => $finalTerm,
        ];
    }

    /**
     * Calculate maximum funding based on capacity
     */
    protected function calculateMaxFunding(
        float $remainingDailyCapacity,
        int $termMonths,
        float $factorRate
    ): array {
        $totalBusinessDays = $termMonths * $this->businessDaysPerMonth;
        $maxPayback = $remainingDailyCapacity * $totalBusinessDays;
        $maxAmount = $maxPayback / $factorRate;

        // Apply maximum funding cap
        $maxFunding = $this->config['maximum_funding_amount'] ?? 500000;
        $maxAmount = min($maxAmount, $maxFunding);

        return [
            'remaining_daily_capacity' => round($remainingDailyCapacity, 2),
            'term_business_days' => (int) round($totalBusinessDays),
            'factor_rate' => $factorRate,
            'max_payback' => round($maxPayback, 2),
            'max_amount' => round($maxAmount, 2),
        ];
    }

    /**
     * Calculate approval percentage based on various factors
     */
    protected function calculateApprovalPercentage(
        array $tier,
        int $position,
        int $creditScore,
        string $volatilityLevel
    ): array {
        $baseApproval = $tier['approval_percentage'];
        $adjustments = [];

        // Position modifier
        $positionMod = $this->config['stacking_adjustments']['position_adjustments'][$position]['approval_modifier'] ?? 1.0;
        if ($positionMod != 1.0) {
            $adjustments[] = [
                'type' => 'position',
                'description' => "Position $position",
                'value' => ($positionMod - 1.0),
            ];
        }

        // Credit score boost
        $creditAdj = $this->getCreditScoreAdjustment($creditScore);
        if ($creditAdj['approval_boost'] != 0) {
            $adjustments[] = [
                'type' => 'credit_score',
                'description' => "{$creditAdj['tier']} credit",
                'value' => $creditAdj['approval_boost'],
            ];
        }

        // Volatility boost
        if (isset($this->config['volatility_adjustments'][$volatilityLevel])) {
            $volBoost = $this->config['volatility_adjustments'][$volatilityLevel]['approval_boost'];
            if ($volBoost != 0) {
                $adjustments[] = [
                    'type' => 'volatility',
                    'description' => ucfirst($volatilityLevel) . ' volatility',
                    'value' => $volBoost,
                ];
            }
        }

        // Calculate final percentage
        $totalBoost = array_sum(array_column($adjustments, 'value'));
        $finalPercentage = $baseApproval * $positionMod + $totalBoost;
        $finalPercentage = max(0.1, min(1.0, $finalPercentage)); // 10% - 100%

        return [
            'base_approval' => $baseApproval,
            'adjustments' => $adjustments,
            'final_percentage' => round($finalPercentage, 4),
        ];
    }

    /**
     * Calculate holdback percentage
     */
    protected function calculateHoldback(int $riskScore, int $position, ?string $industry): array
    {
        $holdbackConfig = $this->config['holdback'] ?? [];
        $baseHoldback = $holdbackConfig['base_percentage'] ?? 0.10;
        $adjustments = [];

        // Risk-based adjustment
        $riskLevel = $riskScore >= 70 ? 'low' : ($riskScore >= 40 ? 'medium' : ($riskScore >= 20 ? 'high' : 'very_high'));
        $riskAdj = $holdbackConfig['risk_adjustment'][$riskLevel] ?? 0;
        if ($riskAdj != 0) {
            $adjustments[] = [
                'type' => 'risk',
                'description' => ucfirst($riskLevel) . ' risk',
                'value' => $riskAdj,
            ];
        }

        // Stacking addition
        if ($position > 1) {
            $stackingAdd = ($position - 1) * ($holdbackConfig['stacking_addition_per_position'] ?? 0.02);
            $adjustments[] = [
                'type' => 'stacking',
                'description' => "Position $position",
                'value' => $stackingAdd,
            ];
        }

        // Calculate final holdback
        $totalAdjustment = array_sum(array_column($adjustments, 'value'));
        $finalHoldback = $baseHoldback + $totalAdjustment;

        // Clamp to valid range
        $minHoldback = $holdbackConfig['minimum'] ?? 0.08;
        $maxHoldback = $holdbackConfig['maximum'] ?? 0.25;
        $finalHoldback = max($minHoldback, min($maxHoldback, $finalHoldback));

        return [
            'base_holdback' => $baseHoldback,
            'adjustments' => $adjustments,
            'total_adjustment' => round($totalAdjustment, 4),
            'final_holdback' => round($finalHoldback, 4),
        ];
    }

    /**
     * Build human-readable explanation of the offer
     */
    protected function buildExplanation(
        array $offer,
        array $withholdBreakdown,
        string $status,
        float $requestedAmount
    ): string {
        $lines = [];

        if ($status === self::STATUS_APPROVED) {
            $lines[] = sprintf(
                'APPROVED: $%s at %.2f factor for %d months.',
                number_format($offer['funding_amount'], 2),
                $offer['factor_rate'],
                $offer['term_months']
            );
        } else {
            $lines[] = sprintf(
                'APPROVED (REDUCED): $%s of $%s requested (%.0f%%).',
                number_format($offer['funding_amount'], 2),
                number_format($requestedAmount, 2),
                ($offer['funding_amount'] / $requestedAmount) * 100
            );
        }

        $lines[] = sprintf(
            'Daily payment: $%s (%.1f%% of daily revenue).',
            number_format($offer['daily_payment'], 2),
            $withholdBreakdown['new_withhold_percent']
        );

        $lines[] = sprintf(
            'Total withhold after this position: %.1f%% of 20.0%% maximum.',
            $withholdBreakdown['total_withhold_percent']
        );

        $lines[] = sprintf(
            'Remaining capacity: $%s/day (%.1f%%).',
            number_format($withholdBreakdown['remaining_capacity_after'], 2),
            $withholdBreakdown['remaining_percent_after']
        );

        return implode(' ', $lines);
    }

    /**
     * Quick capacity check without full offer calculation
     */
    public function checkCapacity(float $monthlyTrueRevenue, float $existingDailyPayment, ?string $industry = null): array
    {
        return $this->calculateCapacity($monthlyTrueRevenue, $existingDailyPayment, $industry);
    }

    /**
     * Calculate multiple offer scenarios
     */
    public function calculateScenarios(array $baseInput, array $scenarios): array
    {
        $results = [];

        foreach ($scenarios as $name => $overrides) {
            $input = array_merge($baseInput, $overrides);
            $results[$name] = $this->calculateOffer($input);
        }

        return $results;
    }

    /**
     * Validate offer terms server-side
     */
    public function validateOfferTerms(array $offer): array
    {
        $validation = $this->config['validation'] ?? [];
        $errors = [];

        // Validate factor rate
        if (isset($offer['factor_rate'])) {
            $min = $validation['factor_rate']['min'] ?? 1.10;
            $max = $validation['factor_rate']['max'] ?? 1.75;
            if ($offer['factor_rate'] < $min || $offer['factor_rate'] > $max) {
                $errors[] = sprintf('Factor rate %.4f outside valid range (%.2f - %.2f)', $offer['factor_rate'], $min, $max);
            }
        }

        // Validate term
        if (isset($offer['term_months'])) {
            $min = $validation['term_months']['min'] ?? 2;
            $max = $validation['term_months']['max'] ?? 18;
            if ($offer['term_months'] < $min || $offer['term_months'] > $max) {
                $errors[] = sprintf('Term %d months outside valid range (%d - %d)', $offer['term_months'], $min, $max);
            }
        }

        // Validate daily payment
        if (isset($offer['daily_payment'])) {
            $min = $validation['daily_payment']['min'] ?? 50;
            $max = $validation['daily_payment']['max'] ?? 50000;
            if ($offer['daily_payment'] < $min || $offer['daily_payment'] > $max) {
                $errors[] = sprintf('Daily payment $%.2f outside valid range ($%s - $%s)', $offer['daily_payment'], number_format($min), number_format($max));
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
