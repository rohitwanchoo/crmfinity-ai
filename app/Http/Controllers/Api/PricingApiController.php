<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DynamicMCACalculator;
use App\Services\TrueRevenueEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * PricingApiController - MCA Pricing and Offer Calculation API
 *
 * Provides endpoints for:
 * - Calculating MCA offers with 20% withhold cap enforcement
 * - Checking merchant capacity for additional positions
 * - Validating offer terms
 * - Generating offer scenarios
 */
#[OA\Tag(
    name: 'MCA Pricing',
    description: 'Endpoints for MCA offer calculation and pricing'
)]
class PricingApiController extends Controller
{
    protected DynamicMCACalculator $calculator;
    protected TrueRevenueEngine $revenueEngine;

    public function __construct()
    {
        $this->calculator = new DynamicMCACalculator();
        $this->revenueEngine = new TrueRevenueEngine();
    }

    /**
     * Calculate MCA offer with full breakdown
     */
    #[OA\Post(
        path: '/api/v1/pricing/calculate',
        summary: 'Calculate MCA offer',
        description: 'Calculate a complete MCA offer with 20% withhold cap enforcement, factor rate adjustments, and full math breakdown.',
        tags: ['MCA Pricing'],
        security: [['sanctumAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['monthly_true_revenue', 'requested_amount'],
                properties: [
                    new OA\Property(property: 'monthly_true_revenue', type: 'number', example: 100000),
                    new OA\Property(property: 'existing_daily_payment', type: 'number', example: 500),
                    new OA\Property(property: 'requested_amount', type: 'number', example: 50000),
                    new OA\Property(property: 'position', type: 'integer', example: 2),
                    new OA\Property(property: 'term_months', type: 'integer', example: 6),
                    new OA\Property(property: 'factor_rate', type: 'number', example: 1.35),
                    new OA\Property(property: 'industry', type: 'string', example: 'restaurant'),
                    new OA\Property(property: 'credit_score', type: 'integer', example: 680),
                    new OA\Property(property: 'risk_score', type: 'integer', example: 65),
                    new OA\Property(property: 'volatility_level', type: 'string', enum: ['low', 'medium', 'high']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Offer calculated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_true_revenue' => 'required|numeric|min:1000',
            'existing_daily_payment' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:1000',
            'position' => 'nullable|integer|min:1|max:4',
            'term_months' => 'nullable|integer|min:2|max:18',
            'factor_rate' => 'nullable|numeric|min:1.10|max:1.75',
            'industry' => 'nullable|string|max:50',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'risk_score' => 'nullable|integer|min:0|max:100',
            'volatility_level' => 'nullable|string|in:low,medium,high',
        ]);

        $result = $this->calculator->calculateOffer($validated);

        return response()->json([
            'success' => $result['can_fund'],
            'status' => $result['status'],
            'data' => $result,
        ]);
    }

    /**
     * Check merchant capacity for additional MCA position
     */
    #[OA\Post(
        path: '/api/v1/pricing/capacity',
        summary: 'Check merchant capacity',
        description: 'Check remaining capacity for additional MCA positions based on 20% withhold cap.',
        tags: ['MCA Pricing'],
        security: [['sanctumAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['monthly_true_revenue'],
                properties: [
                    new OA\Property(property: 'monthly_true_revenue', type: 'number', example: 100000),
                    new OA\Property(property: 'existing_daily_payment', type: 'number', example: 500),
                    new OA\Property(property: 'industry', type: 'string', example: 'restaurant'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Capacity check successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function checkCapacity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_true_revenue' => 'required|numeric|min:0',
            'existing_daily_payment' => 'nullable|numeric|min:0',
            'industry' => 'nullable|string|max:50',
        ]);

        $capacity = $this->calculator->checkCapacity(
            (float) $validated['monthly_true_revenue'],
            (float) ($validated['existing_daily_payment'] ?? 0),
            $validated['industry'] ?? null
        );

        // Calculate max funding at various terms
        $fundingScenarios = [];
        $factorRates = [1.25, 1.35, 1.45];
        $terms = [3, 6, 9, 12];
        $businessDays = config('mca_pricing.business_days_per_month', 21.67);

        foreach ($terms as $term) {
            foreach ($factorRates as $rate) {
                if ($capacity['remaining_daily_capacity'] > 0) {
                    $totalDays = $term * $businessDays;
                    $maxPayback = $capacity['remaining_daily_capacity'] * $totalDays;
                    $maxFunding = $maxPayback / $rate;

                    $fundingScenarios[] = [
                        'term_months' => $term,
                        'factor_rate' => $rate,
                        'max_funding' => round($maxFunding, 2),
                        'daily_payment' => round($capacity['remaining_daily_capacity'], 2),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'capacity' => $capacity,
                'funding_scenarios' => $fundingScenarios,
                'recommendation' => $capacity['at_capacity']
                    ? 'Merchant is at maximum capacity. Consider buyout of existing positions.'
                    : sprintf('Merchant can support up to $%.2f/day additional payment (%.1f%% remaining).',
                        $capacity['remaining_daily_capacity'],
                        $capacity['remaining_withhold_percent']
                    ),
            ],
        ]);
    }

    /**
     * Validate offer terms server-side
     */
    #[OA\Post(
        path: '/api/v1/pricing/validate',
        summary: 'Validate offer terms',
        description: 'Server-side validation of offer terms to ensure they meet business rules.',
        tags: ['MCA Pricing'],
        security: [['sanctumAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'funding_amount', type: 'number', example: 50000),
                    new OA\Property(property: 'factor_rate', type: 'number', example: 1.35),
                    new OA\Property(property: 'term_months', type: 'integer', example: 6),
                    new OA\Property(property: 'daily_payment', type: 'number', example: 500),
                    new OA\Property(property: 'monthly_true_revenue', type: 'number', example: 100000),
                    new OA\Property(property: 'existing_daily_payment', type: 'number', example: 400),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Validation result',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'valid', type: 'boolean'),
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string')),
                    ]
                )
            ),
        ]
    )]
    public function validate(Request $request): JsonResponse
    {
        $offer = $request->validate([
            'funding_amount' => 'nullable|numeric|min:0',
            'factor_rate' => 'nullable|numeric',
            'term_months' => 'nullable|integer',
            'daily_payment' => 'nullable|numeric',
            'monthly_true_revenue' => 'nullable|numeric',
            'existing_daily_payment' => 'nullable|numeric',
        ]);

        $errors = [];

        // Validate basic offer terms
        $termValidation = $this->calculator->validateOfferTerms($offer);
        $errors = array_merge($errors, $termValidation['errors']);

        // Validate withhold cap if revenue provided
        if (isset($offer['monthly_true_revenue']) && isset($offer['daily_payment'])) {
            $businessDays = config('mca_pricing.business_days_per_month', 21.67);
            $dailyRevenue = $offer['monthly_true_revenue'] / $businessDays;
            $existingPayment = $offer['existing_daily_payment'] ?? 0;
            $totalPayment = $existingPayment + $offer['daily_payment'];

            $maxWithhold = config('mca_pricing.max_withhold_percentage', 0.20);
            $maxDailyPayment = $dailyRevenue * $maxWithhold;

            if ($totalPayment > $maxDailyPayment) {
                $currentPercent = ($totalPayment / $dailyRevenue) * 100;
                $errors[] = sprintf(
                    'Total daily payment $%.2f exceeds 20%% withhold cap (currently %.1f%% of daily revenue)',
                    $totalPayment,
                    $currentPercent
                );
            }
        }

        // Validate math consistency
        if (isset($offer['funding_amount']) && isset($offer['factor_rate']) && isset($offer['term_months']) && isset($offer['daily_payment'])) {
            $businessDays = config('mca_pricing.business_days_per_month', 21.67);
            $expectedPayback = $offer['funding_amount'] * $offer['factor_rate'];
            $expectedDaily = $expectedPayback / ($offer['term_months'] * $businessDays);

            // Allow 1% tolerance for rounding
            if (abs($expectedDaily - $offer['daily_payment']) / $expectedDaily > 0.01) {
                $errors[] = sprintf(
                    'Daily payment $%.2f is inconsistent with terms (expected $%.2f)',
                    $offer['daily_payment'],
                    $expectedDaily
                );
            }
        }

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Generate multiple offer scenarios
     */
    #[OA\Post(
        path: '/api/v1/pricing/scenarios',
        summary: 'Generate offer scenarios',
        description: 'Generate multiple offer scenarios with different terms for comparison.',
        tags: ['MCA Pricing'],
        security: [['sanctumAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['monthly_true_revenue', 'requested_amount'],
                properties: [
                    new OA\Property(property: 'monthly_true_revenue', type: 'number', example: 100000),
                    new OA\Property(property: 'existing_daily_payment', type: 'number', example: 500),
                    new OA\Property(property: 'requested_amount', type: 'number', example: 50000),
                    new OA\Property(property: 'position', type: 'integer', example: 2),
                    new OA\Property(property: 'industry', type: 'string', example: 'restaurant'),
                    new OA\Property(property: 'credit_score', type: 'integer', example: 680),
                    new OA\Property(property: 'risk_score', type: 'integer', example: 65),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Scenarios generated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'scenarios', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function scenarios(Request $request): JsonResponse
    {
        $baseInput = $request->validate([
            'monthly_true_revenue' => 'required|numeric|min:1000',
            'existing_daily_payment' => 'nullable|numeric|min:0',
            'requested_amount' => 'required|numeric|min:1000',
            'position' => 'nullable|integer|min:1|max:4',
            'industry' => 'nullable|string|max:50',
            'credit_score' => 'nullable|integer|min:300|max:850',
            'risk_score' => 'nullable|integer|min:0|max:100',
        ]);

        // Generate scenarios with different terms and rates
        $scenarios = [
            'conservative_short' => [
                'term_months' => 4,
                'factor_rate' => 1.45,
            ],
            'standard_medium' => [
                'term_months' => 6,
                'factor_rate' => 1.35,
            ],
            'aggressive_long' => [
                'term_months' => 9,
                'factor_rate' => 1.25,
            ],
        ];

        $results = $this->calculator->calculateScenarios($baseInput, $scenarios);

        // Format for easy comparison
        $comparison = [];
        foreach ($results as $name => $result) {
            if ($result['can_fund'] && $result['offer']) {
                $comparison[$name] = [
                    'can_fund' => true,
                    'funding_amount' => $result['offer']['funding_amount'],
                    'factor_rate' => $result['offer']['factor_rate'],
                    'term_months' => $result['offer']['term_months'],
                    'daily_payment' => $result['offer']['daily_payment'],
                    'payback_amount' => $result['offer']['payback_amount'],
                    'cost_of_capital' => $result['offer']['cost_of_capital'],
                    'withhold_percent' => $result['offer']['withhold_breakdown']['new_withhold_percent'],
                ];
            } else {
                $comparison[$name] = [
                    'can_fund' => false,
                    'reason' => $result['decline_reason'] ?? $result['explanation'] ?? 'Unable to calculate',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'scenarios' => $comparison,
            'detailed_results' => $results,
            'recommendation' => $this->getScenarioRecommendation($comparison),
        ]);
    }

    /**
     * Get industry list with risk levels
     */
    #[OA\Get(
        path: '/api/v1/pricing/industries',
        summary: 'Get industry list',
        description: 'Get list of industries with their risk levels and adjustments.',
        tags: ['MCA Pricing'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Industry list retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'industries', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
        ]
    )]
    public function industries(): JsonResponse
    {
        $industries = config('mca_pricing.industry_adjustments', []);

        $formatted = [];
        foreach ($industries as $key => $data) {
            $formatted[] = [
                'key' => $key,
                'name' => ucwords(str_replace('_', ' ', $key)),
                'risk_level' => $data['risk_level'],
                'factor_adjustment' => $data['factor_adjustment'],
                'term_adjustment' => $data['term_adjustment'],
                'max_withhold' => $data['max_withhold_override'] ?? config('mca_pricing.max_withhold_percentage', 0.20),
                'notes' => $data['notes'] ?? null,
            ];
        }

        // Sort by risk level
        usort($formatted, function ($a, $b) {
            $order = ['low' => 1, 'medium' => 2, 'medium_high' => 3, 'high' => 4, 'very_high' => 5];
            return ($order[$a['risk_level']] ?? 3) <=> ($order[$b['risk_level']] ?? 3);
        });

        return response()->json([
            'success' => true,
            'industries' => $formatted,
        ]);
    }

    /**
     * Get pricing configuration (public values only)
     */
    #[OA\Get(
        path: '/api/v1/pricing/config',
        summary: 'Get pricing configuration',
        description: 'Get public pricing configuration values for UI display.',
        tags: ['MCA Pricing'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Configuration retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'config', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config' => [
                'max_withhold_percentage' => config('mca_pricing.max_withhold_percentage', 0.20) * 100,
                'min_withhold_percentage' => config('mca_pricing.min_withhold_percentage', 0.05) * 100,
                'business_days_per_month' => config('mca_pricing.business_days_per_month', 21.67),
                'minimum_funding_amount' => config('mca_pricing.minimum_funding_amount', 5000),
                'maximum_funding_amount' => config('mca_pricing.maximum_funding_amount', 500000),
                'max_positions' => config('mca_pricing.max_positions', 4),
                'factor_rate_range' => [
                    'min' => config('mca_pricing.validation.factor_rate.min', 1.10),
                    'max' => config('mca_pricing.validation.factor_rate.max', 1.75),
                ],
                'term_range' => [
                    'min' => config('mca_pricing.validation.term_months.min', 2),
                    'max' => config('mca_pricing.validation.term_months.max', 18),
                ],
                'factor_rate_tiers' => config('mca_pricing.factor_rate_tiers', []),
            ],
        ]);
    }

    /**
     * Get recommendation for best scenario
     */
    protected function getScenarioRecommendation(array $comparison): string
    {
        $fundable = array_filter($comparison, fn($s) => $s['can_fund'] ?? false);

        if (empty($fundable)) {
            return 'No scenarios are currently fundable. Consider reducing the requested amount or waiting for existing positions to pay down.';
        }

        // Find best by funding amount
        $bestFunding = '';
        $maxFunding = 0;
        foreach ($fundable as $name => $scenario) {
            if ($scenario['funding_amount'] > $maxFunding) {
                $maxFunding = $scenario['funding_amount'];
                $bestFunding = $name;
            }
        }

        // Find best by lowest cost
        $bestCost = '';
        $minCost = PHP_INT_MAX;
        foreach ($fundable as $name => $scenario) {
            if ($scenario['cost_of_capital'] < $minCost) {
                $minCost = $scenario['cost_of_capital'];
                $bestCost = $name;
            }
        }

        if ($bestFunding === $bestCost) {
            return sprintf(
                'Recommended: %s - Best balance of funding amount ($%s) and cost ($%s).',
                ucwords(str_replace('_', ' ', $bestFunding)),
                number_format($maxFunding, 2),
                number_format($minCost, 2)
            );
        }

        return sprintf(
            'For maximum funding: %s ($%s). For lowest cost: %s ($%s cost).',
            ucwords(str_replace('_', ' ', $bestFunding)),
            number_format($maxFunding, 2),
            ucwords(str_replace('_', ' ', $bestCost)),
            number_format($minCost, 2)
        );
    }
}
