<?php

namespace Tests\Feature;

use App\Services\DynamicMCACalculator;
use Tests\TestCase;

class DynamicMCACalculatorTest extends TestCase
{
    protected DynamicMCACalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DynamicMCACalculator();
    }

    // ==========================================
    // 20% HARD CAP ENFORCEMENT TESTS
    // ==========================================

    public function test_never_exceeds_20_percent_withhold_for_first_position(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 50000,
            'requested_amount' => 100000, // Request way more than possible
            'existing_daily_payment' => 0,
            'existing_positions' => 0,
            'credit_score' => 700,
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        $this->assertNotNull($result);
        $this->assertTrue($result['can_fund']);

        $dailyRevenue = 50000 / 21.67;
        $maxDaily = $dailyRevenue * 0.20;

        // Allow small rounding tolerance (0.01%)
        $this->assertLessThanOrEqual(
            $maxDaily + 0.05,
            $result['offer']['daily_payment'],
            'Daily payment should never exceed 20% of daily revenue'
        );

        $actualWithhold = $result['offer']['daily_payment'] / $dailyRevenue;
        $this->assertLessThanOrEqual(0.2001, $actualWithhold, 'Withhold percentage must be <= 20%');
    }

    public function test_respects_remaining_capacity_for_second_position(): void
    {
        $monthlyRevenue = 60000;
        $existingDaily = 400; // Existing MCA payment

        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => $monthlyRevenue,
            'requested_amount' => 50000,
            'existing_daily_payment' => $existingDaily,
            'position' => 2,
            'credit_score' => 680,
            'industry' => 'retail',
            'time_in_business_months' => 36,
        ]);

        $dailyRevenue = $monthlyRevenue / 21.67;
        $maxTotalWithhold = $dailyRevenue * 0.20;
        $remainingCapacity = $maxTotalWithhold - $existingDaily;

        $this->assertLessThanOrEqual(
            $remainingCapacity + 0.01, // Small tolerance for rounding
            $result['offer']['daily_payment'],
            'New position payment should not exceed remaining capacity'
        );

        $totalWithhold = ($existingDaily + $result['offer']['daily_payment']) / $dailyRevenue;
        $this->assertLessThanOrEqual(0.201, $totalWithhold, 'Total withhold must not exceed 20%');
    }

    public function test_declines_when_at_maximum_capacity(): void
    {
        $monthlyRevenue = 40000;
        $dailyRevenue = $monthlyRevenue / 21.67;
        $existingDaily = $dailyRevenue * 0.20; // Already at 20% capacity

        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => $monthlyRevenue,
            'requested_amount' => 10000,
            'existing_daily_payment' => $existingDaily,
            'position' => 2,
            'credit_score' => 720,
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        $this->assertFalse($result['can_fund'], 'Should decline when at maximum capacity');
        $this->assertEquals(DynamicMCACalculator::STATUS_DECLINED, $result['status']);
        $this->assertEquals(DynamicMCACalculator::DECLINE_AT_CAPACITY, $result['decline_reason']);
    }

    public function test_declines_when_exceeding_max_positions(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 100000,
            'requested_amount' => 20000,
            'existing_daily_payment' => 500,
            'position' => 5, // Exceeds max (4 positions)
            'credit_score' => 750,
            'industry' => 'retail',
            'time_in_business_months' => 60,
        ]);

        $this->assertFalse($result['can_fund'], 'Should decline when exceeding maximum positions');
        $this->assertEquals(DynamicMCACalculator::STATUS_DECLINED, $result['status']);
        $this->assertEquals(DynamicMCACalculator::DECLINE_TOO_MANY_POSITIONS, $result['decline_reason']);
    }

    // ==========================================
    // INDUSTRY ADJUSTMENT TESTS
    // ==========================================

    public function test_restaurant_gets_higher_factor_rate(): void
    {
        $baseInput = [
            'monthly_true_revenue' => 80000,
            'requested_amount' => 30000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 680,
            'time_in_business_months' => 36,
        ];

        $retailResult = $this->calculator->calculateOffer(array_merge($baseInput, ['industry' => 'retail']));
        $restaurantResult = $this->calculator->calculateOffer(array_merge($baseInput, ['industry' => 'restaurant']));

        $this->assertTrue($retailResult['can_fund']);
        $this->assertTrue($restaurantResult['can_fund']);

        $this->assertGreaterThan(
            $retailResult['offer']['factor_rate'],
            $restaurantResult['offer']['factor_rate'],
            'Restaurant should have higher factor rate than retail due to higher risk'
        );
    }

    public function test_healthcare_gets_better_terms(): void
    {
        $baseInput = [
            'monthly_true_revenue' => 100000,
            'requested_amount' => 40000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 700,
            'time_in_business_months' => 48,
        ];

        $retailResult = $this->calculator->calculateOffer(array_merge($baseInput, ['industry' => 'retail']));
        $healthcareResult = $this->calculator->calculateOffer(array_merge($baseInput, ['industry' => 'healthcare']));

        $this->assertTrue($retailResult['can_fund']);
        $this->assertTrue($healthcareResult['can_fund']);

        $this->assertLessThan(
            $retailResult['offer']['factor_rate'],
            $healthcareResult['offer']['factor_rate'],
            'Healthcare should have lower factor rate than retail due to lower risk'
        );
    }

    public function test_trucking_is_high_risk(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 70000,
            'requested_amount' => 25000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 680,
            'industry' => 'trucking',
            'time_in_business_months' => 24,
        ]);

        $this->assertTrue($result['can_fund']);
        // Trucking should have factor adjustment of +0.10 per config
        $this->assertGreaterThanOrEqual(1.25, $result['offer']['factor_rate']);
    }

    // ==========================================
    // CREDIT SCORE ADJUSTMENT TESTS
    // ==========================================

    public function test_excellent_credit_improves_factor_rate(): void
    {
        $baseInput = [
            'monthly_true_revenue' => 60000,
            'requested_amount' => 25000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'industry' => 'retail',
            'time_in_business_months' => 36,
        ];

        $poorCreditResult = $this->calculator->calculateOffer(array_merge($baseInput, ['credit_score' => 550]));
        $excellentCreditResult = $this->calculator->calculateOffer(array_merge($baseInput, ['credit_score' => 780]));

        $this->assertTrue($poorCreditResult['can_fund']);
        $this->assertTrue($excellentCreditResult['can_fund']);

        $this->assertLessThan(
            $poorCreditResult['offer']['factor_rate'],
            $excellentCreditResult['offer']['factor_rate'],
            'Excellent credit should result in lower factor rate'
        );
    }

    public function test_poor_credit_increases_factor_rate(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 50000,
            'requested_amount' => 20000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 520, // Very poor
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        $this->assertTrue($result['can_fund']);
        // Very poor credit adds +0.10 to factor rate
        $this->assertGreaterThanOrEqual(1.30, $result['offer']['factor_rate']);
    }

    // ==========================================
    // POSITION STACKING TESTS
    // ==========================================

    public function test_second_position_has_higher_factor_rate(): void
    {
        $baseInput = [
            'monthly_true_revenue' => 80000,
            'requested_amount' => 20000,
            'existing_daily_payment' => 200,
            'credit_score' => 700,
            'industry' => 'retail',
            'time_in_business_months' => 36,
        ];

        $firstPosition = $this->calculator->calculateOffer(array_merge($baseInput, ['position' => 1]));
        $secondPosition = $this->calculator->calculateOffer(array_merge($baseInput, ['position' => 2]));

        $this->assertTrue($firstPosition['can_fund']);
        $this->assertTrue($secondPosition['can_fund']);

        $this->assertGreaterThan(
            $firstPosition['offer']['factor_rate'],
            $secondPosition['offer']['factor_rate'],
            'Second position should have higher factor rate'
        );
    }

    public function test_third_position_has_reduced_approval_amount(): void
    {
        $baseInput = [
            'monthly_true_revenue' => 100000,
            'requested_amount' => 30000,
            'existing_daily_payment' => 300,
            'credit_score' => 720,
            'industry' => 'retail',
            'time_in_business_months' => 48,
        ];

        $firstPosition = $this->calculator->calculateOffer(array_merge($baseInput, ['position' => 1]));
        $thirdPosition = $this->calculator->calculateOffer(array_merge($baseInput, ['position' => 3]));

        $this->assertTrue($firstPosition['can_fund']);
        if ($thirdPosition['can_fund']) {
            // Third position approval modifier is 0.70 per config
            $this->assertLessThan(
                $firstPosition['offer']['funding_amount'],
                $thirdPosition['offer']['funding_amount'],
                'Third position should approve lower amount'
            );
        }
    }

    // ==========================================
    // CAPACITY CHECK TESTS
    // ==========================================

    public function test_capacity_calculation_accuracy(): void
    {
        $monthlyRevenue = 50000;
        $existingDaily = 200;

        $capacity = $this->calculator->checkCapacity($monthlyRevenue, $existingDaily, 'retail');

        $dailyRevenue = $monthlyRevenue / 21.67;
        $maxWithhold = $dailyRevenue * 0.20;
        $expectedRemaining = $maxWithhold - $existingDaily;

        $this->assertEqualsWithDelta(
            $expectedRemaining,
            $capacity['remaining_daily_capacity'],
            0.01,
            'Remaining capacity calculation should be accurate'
        );

        $expectedUtilization = ($existingDaily / $maxWithhold) * 100;
        $actualUtilization = ($capacity['current_withhold_percent'] / $capacity['max_withhold_percent']) * 100;
        $this->assertEqualsWithDelta(
            $expectedUtilization,
            $actualUtilization,
            1.0,
            'Utilization percentage should be accurate'
        );
    }

    public function test_capacity_shows_at_limit(): void
    {
        $monthlyRevenue = 40000;
        $dailyRevenue = $monthlyRevenue / 21.67;
        $maxWithhold = $dailyRevenue * 0.20;

        $capacity = $this->calculator->checkCapacity($monthlyRevenue, $maxWithhold, 'retail');

        $this->assertTrue($capacity['at_capacity']);
        $this->assertEqualsWithDelta(0, $capacity['remaining_daily_capacity'], 0.01);
    }

    // ==========================================
    // OFFER CALCULATION TESTS
    // ==========================================

    public function test_daily_payment_uses_business_days(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 60000,
            'requested_amount' => 20000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 700,
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        $this->assertTrue($result['can_fund']);

        // Verify the math uses 21.67 business days
        $paybackAmount = $result['offer']['payback_amount'];
        $termMonths = $result['offer']['term_months'];
        $expectedDailyIfBusinessDays = $paybackAmount / ($termMonths * 21.67);

        $this->assertEqualsWithDelta(
            $expectedDailyIfBusinessDays,
            $result['offer']['daily_payment'],
            0.01,
            'Daily payment should use 21.67 business days per month'
        );
    }

    public function test_payback_amount_equals_funding_times_factor(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 70000,
            'requested_amount' => 25000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 720,
            'industry' => 'retail',
            'time_in_business_months' => 36,
        ]);

        $this->assertTrue($result['can_fund']);

        $expectedPayback = $result['offer']['funding_amount'] * $result['offer']['factor_rate'];

        $this->assertEqualsWithDelta(
            $expectedPayback,
            $result['offer']['payback_amount'],
            0.01,
            'Payback = Funding x Factor Rate'
        );
    }

    public function test_offer_includes_math_breakdown(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 50000,
            'requested_amount' => 20000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 680,
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        $this->assertTrue($result['can_fund']);
        $this->assertArrayHasKey('math_breakdown', $result);
        $this->assertArrayHasKey('step_1_revenue', $result['math_breakdown']);
        $this->assertArrayHasKey('daily_true_revenue', $result['math_breakdown']['step_1_revenue']);
        $this->assertArrayHasKey('step_2_capacity', $result['math_breakdown']);
    }

    // ==========================================
    // SCENARIO GENERATION TESTS
    // ==========================================

    public function test_generates_multiple_scenarios(): void
    {
        $baseInput = [
            'monthly_true_revenue' => 60000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 700,
            'industry' => 'retail',
            'time_in_business_months' => 36,
        ];

        $scenarios = [
            'small' => ['requested_amount' => 15000],
            'medium' => ['requested_amount' => 25000],
            'large' => ['requested_amount' => 40000],
        ];

        $results = $this->calculator->calculateScenarios($baseInput, $scenarios);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('small', $results);
        $this->assertArrayHasKey('medium', $results);
        $this->assertArrayHasKey('large', $results);

        // All should be fundable
        foreach ($results as $name => $result) {
            $this->assertTrue($result['can_fund'], "Scenario '$name' should be fundable");
        }
    }

    // ==========================================
    // VALIDATION TESTS
    // ==========================================

    public function test_validates_factor_rate_bounds(): void
    {
        $validation = $this->calculator->validateOfferTerms([
            'funding_amount' => 20000,
            'factor_rate' => 1.05, // Below minimum 1.10
            'term_months' => 6,
            'daily_payment' => 500,
            'holdback_percentage' => 0.15,
        ]);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
        $foundFactorError = false;
        foreach ($validation['errors'] as $error) {
            if (stripos($error, 'factor rate') !== false) {
                $foundFactorError = true;
                break;
            }
        }
        $this->assertTrue($foundFactorError, 'Should have a factor rate error');
    }

    public function test_validates_term_bounds(): void
    {
        $validation = $this->calculator->validateOfferTerms([
            'funding_amount' => 20000,
            'factor_rate' => 1.25,
            'term_months' => 24, // Above maximum 18
            'daily_payment' => 500,
            'holdback_percentage' => 0.15,
        ]);

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
        $foundTermError = false;
        foreach ($validation['errors'] as $error) {
            if (stripos($error, 'term') !== false) {
                $foundTermError = true;
                break;
            }
        }
        $this->assertTrue($foundTermError, 'Should have a term error');
    }

    public function test_validates_valid_offer(): void
    {
        $validation = $this->calculator->validateOfferTerms([
            'funding_amount' => 20000,
            'factor_rate' => 1.25, // Valid
            'term_months' => 6, // Valid
            'daily_payment' => 500, // Valid
            'holdback_percentage' => 0.15, // Valid
        ]);

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    // ==========================================
    // REAL-WORLD SCENARIO TESTS
    // ==========================================

    public function test_real_world_second_position_restaurant(): void
    {
        // Restaurant with existing MCA, applying for 2nd position
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 85000,
            'requested_amount' => 25000,
            'existing_daily_payment' => 350, // Existing 1st position
            'position' => 2,
            'credit_score' => 660,
            'industry' => 'restaurant',
            'time_in_business_months' => 30,
        ]);

        $this->assertTrue($result['can_fund']);

        // Verify 20% cap is respected with existing position
        $dailyRevenue = 85000 / 21.67;
        $totalWithhold = ($result['offer']['daily_payment'] + 350) / $dailyRevenue;

        $this->assertLessThanOrEqual(0.201, $totalWithhold, 'Total withhold must stay under 20%');

        // Restaurant should have higher factor rate adjustment
        $this->assertGreaterThanOrEqual(1.25, $result['offer']['factor_rate']);
    }

    public function test_real_world_third_position_high_revenue(): void
    {
        // High-revenue merchant seeking 3rd position
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 200000,
            'requested_amount' => 40000,
            'existing_daily_payment' => 1000, // Two existing positions
            'position' => 3,
            'credit_score' => 720,
            'industry' => 'healthcare',
            'time_in_business_months' => 60,
        ]);

        // Should still have capacity at this revenue level
        $dailyRevenue = 200000 / 21.67;
        $maxDaily = $dailyRevenue * 0.20;
        $remainingCapacity = $maxDaily - 1000;

        if ($remainingCapacity > 100) {
            $this->assertTrue($result['can_fund'], 'Should approve with remaining capacity');
            $this->assertLessThanOrEqual($remainingCapacity + 0.01, $result['offer']['daily_payment']);
        }
    }

    public function test_real_world_first_position_excellent_merchant(): void
    {
        // Premium merchant - excellent credit, healthcare, long history
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 150000,
            'requested_amount' => 75000,
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 780,
            'industry' => 'healthcare',
            'time_in_business_months' => 96,
            'risk_score' => 85, // High risk score (low risk merchant)
        ]);

        $this->assertTrue($result['can_fund']);

        // Should get premium terms (tier 1)
        $this->assertLessThanOrEqual(1.25, $result['offer']['factor_rate']);
    }

    public function test_minimum_funding_enforced(): void
    {
        // Very low revenue resulting in funding below minimum
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 8000, // Very low revenue
            'requested_amount' => 3000, // Below minimum
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 680,
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        // Should either be declined or meet minimum
        if ($result['can_fund']) {
            $minFunding = config('mca_pricing.minimum_funding_amount', 5000);
            $this->assertGreaterThanOrEqual($minFunding, $result['offer']['funding_amount']);
        } else {
            $this->assertEquals(DynamicMCACalculator::DECLINE_BELOW_MINIMUM, $result['decline_reason']);
        }
    }

    public function test_maximum_funding_enforced(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 1000000, // Very high revenue
            'requested_amount' => 1000000, // Way above max
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 780,
            'industry' => 'healthcare',
            'time_in_business_months' => 120,
            'risk_score' => 90,
        ]);

        $this->assertTrue($result['can_fund']);
        $maxFunding = config('mca_pricing.maximum_funding_amount', 500000);
        $this->assertLessThanOrEqual($maxFunding, $result['offer']['funding_amount']);
    }

    public function test_status_approved_reduced_when_less_than_requested(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 30000,
            'requested_amount' => 50000, // Request more than capacity allows
            'existing_daily_payment' => 0,
            'position' => 1,
            'credit_score' => 680,
            'industry' => 'retail',
            'time_in_business_months' => 24,
        ]);

        $this->assertTrue($result['can_fund']);
        // If funded less than requested, status should be approved_reduced
        if ($result['offer']['funding_amount'] < 50000 * 0.99) {
            $this->assertEquals(DynamicMCACalculator::STATUS_APPROVED_REDUCED, $result['status']);
        }
    }

    public function test_withhold_breakdown_included_in_offer(): void
    {
        $result = $this->calculator->calculateOffer([
            'monthly_true_revenue' => 60000,
            'requested_amount' => 25000,
            'existing_daily_payment' => 200,
            'position' => 2,
            'credit_score' => 700,
            'industry' => 'retail',
            'time_in_business_months' => 36,
        ]);

        $this->assertTrue($result['can_fund']);
        $this->assertArrayHasKey('withhold_breakdown', $result['offer']);

        $breakdown = $result['offer']['withhold_breakdown'];
        $this->assertArrayHasKey('existing_daily_payment', $breakdown);
        $this->assertArrayHasKey('new_daily_payment', $breakdown);
        $this->assertArrayHasKey('total_daily_payment', $breakdown);
        $this->assertArrayHasKey('total_withhold_percent', $breakdown);
        $this->assertArrayHasKey('remaining_capacity_after', $breakdown);
    }
}
