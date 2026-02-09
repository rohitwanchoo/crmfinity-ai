<?php

namespace Tests\Feature;

use App\Services\TrueRevenueEngine;
use Tests\TestCase;

/**
 * TrueRevenueCalculationTest - Comprehensive tests for True Revenue calculations
 *
 * These tests verify that the TrueRevenueEngine correctly classifies bank
 * transactions and calculates True Revenue for MCA underwriting.
 */
class TrueRevenueCalculationTest extends TestCase
{
    protected TrueRevenueEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new TrueRevenueEngine();
    }

    // =========================================================================
    // CARD PROCESSOR SETTLEMENT TESTS
    // =========================================================================

    /** @test */
    public function it_includes_square_deposits_as_revenue()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'SQUARE INC PAYOUT', 'amount' => 3000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'SQ *TRANSFER', 'amount' => 2000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(10000, $result['true_revenue']);
        $this->assertEquals(0, $result['excluded_amount']);
    }

    /** @test */
    public function it_includes_stripe_settlements_as_revenue()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'STRIPE TRANSFER', 'amount' => 8000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'STRIPE PAYOUT', 'amount' => 4000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(12000, $result['true_revenue']);
    }

    /** @test */
    public function it_includes_shopify_payouts_as_revenue()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SHOPIFY PAYOUT', 'amount' => 15000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'SHOPIFY DEPOSIT', 'amount' => 10000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(25000, $result['true_revenue']);
    }

    /** @test */
    public function it_includes_various_card_processors_as_revenue()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'CLOVER DEPOSIT', 'amount' => 3000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'TOAST PAYOUT', 'amount' => 4000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'HEARTLAND DEPOSIT', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-01-18', 'description' => 'WORLDPAY ACH', 'amount' => 6000, 'type' => 'credit'],
            ['date' => '2024-01-19', 'description' => 'ELAVON SETTLEMENT', 'amount' => 7000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(25000, $result['true_revenue']);
    }

    // =========================================================================
    // MCA FUNDING EXCLUSION TESTS
    // =========================================================================

    /** @test */
    public function it_excludes_ondeck_funding()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'ONDECK CAPITAL FUNDING', 'amount' => 50000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'ON DECK ADVANCE', 'amount' => 25000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(75000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_kabbage_funding()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'KABBAGE INC ADVANCE', 'amount' => 30000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(30000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_clearco_funding()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'CLEARCO DEPOSIT', 'amount' => 45000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'CLEARBANC FUNDING', 'amount' => 35000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(80000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_libertas_funding()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'LIBERTAS FUNDING LLC', 'amount' => 40000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(40000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_all_major_mca_funders()
    {
        $transactions = [
            ['date' => '2024-01-01', 'description' => 'FUNDBOX ADVANCE', 'amount' => 10000, 'type' => 'credit'],
            ['date' => '2024-01-02', 'description' => 'BLUEVINE FUNDING', 'amount' => 15000, 'type' => 'credit'],
            ['date' => '2024-01-03', 'description' => 'CREDIBLY INC', 'amount' => 20000, 'type' => 'credit'],
            ['date' => '2024-01-04', 'description' => 'KAPITUS ADVANCE', 'amount' => 25000, 'type' => 'credit'],
            ['date' => '2024-01-05', 'description' => 'RAPID FINANCE FUNDING', 'amount' => 30000, 'type' => 'credit'],
            ['date' => '2024-01-06', 'description' => 'CAN CAPITAL DEPOSIT', 'amount' => 35000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(135000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_platform_capital_programs()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SQUARE CAPITAL ADVANCE', 'amount' => 20000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'PAYPAL WORKING CAPITAL', 'amount' => 25000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'SHOPIFY CAPITAL FUNDING', 'amount' => 30000, 'type' => 'credit'],
            ['date' => '2024-01-18', 'description' => 'STRIPE CAPITAL ADVANCE', 'amount' => 15000, 'type' => 'credit'],
            ['date' => '2024-01-19', 'description' => 'AMAZON LENDING LLC', 'amount' => 35000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(125000, $result['excluded_amount']);
    }

    // =========================================================================
    // OWNER INJECTION EXCLUSION TESTS
    // =========================================================================

    /** @test */
    public function it_excludes_owner_contributions()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'OWNER CONTRIBUTION', 'amount' => 10000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'OWNER DEPOSIT', 'amount' => 15000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'OWNER LOAN', 'amount' => 20000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(45000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_shareholder_contributions()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SHAREHOLDER CONTRIBUTION', 'amount' => 25000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'SHAREHOLDER LOAN', 'amount' => 30000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(55000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_capital_contributions()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'CAPITAL CONTRIBUTION', 'amount' => 50000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'MEMBER CONTRIBUTION', 'amount' => 25000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'EQUITY CONTRIBUTION', 'amount' => 75000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(150000, $result['excluded_amount']);
    }

    // =========================================================================
    // TAX REFUND EXCLUSION TESTS
    // =========================================================================

    /** @test */
    public function it_excludes_irs_refunds()
    {
        $transactions = [
            ['date' => '2024-04-15', 'description' => 'IRS TREAS 310 TAX REF', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-04-20', 'description' => 'TREASURY 310 REFUND', 'amount' => 3000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(8000, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_state_tax_refunds()
    {
        $transactions = [
            ['date' => '2024-04-15', 'description' => 'STATE TAX REFUND', 'amount' => 1000, 'type' => 'credit'],
            ['date' => '2024-04-20', 'description' => 'FRANCHISE TAX REFUND', 'amount' => 500, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(1500, $result['excluded_amount']);
    }

    // =========================================================================
    // TRANSFER EXCLUSION TESTS
    // =========================================================================

    /** @test */
    public function it_excludes_internal_account_transfers()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'TRANSFER FROM SAVINGS *1234', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'TRANSFER FROM CHK *5678', 'amount' => 3000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'INTERNAL TRANSFER', 'amount' => 2000, 'type' => 'credit'],
            ['date' => '2024-01-18', 'description' => 'ACCOUNT TRANSFER', 'amount' => 4000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(14000, $result['excluded_amount']);
    }

    /** @test */
    public function it_includes_zelle_received_from_customers()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'ZELLE FROM JOHN SMITH', 'amount' => 500, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'ZELLE CREDIT FROM ABC CORP', 'amount' => 1000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'ZELLE PAYMENT FROM CUSTOMER', 'amount' => 750, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(2250, $result['true_revenue']);
        $this->assertEquals(0, $result['excluded_amount']);
    }

    // =========================================================================
    // REVERSAL AND REFUND EXCLUSION TESTS
    // =========================================================================

    /** @test */
    public function it_excludes_chargeback_reversals()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'CHARGEBACK REVERSAL', 'amount' => 200, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'DISPUTE CREDIT', 'amount' => 150, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'PROVISIONAL CREDIT', 'amount' => 300, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(650, $result['excluded_amount']);
    }

    /** @test */
    public function it_excludes_fee_reversals()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'NSF FEE REVERSAL', 'amount' => 35, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'OD FEE REVERSAL', 'amount' => 35, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'FEE REFUND', 'amount' => 25, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(0, $result['true_revenue']);
        $this->assertEquals(95, $result['excluded_amount']);
    }

    // =========================================================================
    // MIXED TRANSACTION TESTS
    // =========================================================================

    /** @test */
    public function it_correctly_handles_mixed_transactions()
    {
        $transactions = [
            // Revenue (should be included)
            ['date' => '2024-01-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'STRIPE TRANSFER', 'amount' => 3000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'ZELLE FROM CUSTOMER', 'amount' => 500, 'type' => 'credit'],

            // Excluded (should not be included)
            ['date' => '2024-01-18', 'description' => 'ONDECK FUNDING', 'amount' => 25000, 'type' => 'credit'],
            ['date' => '2024-01-19', 'description' => 'TRANSFER FROM SAVINGS', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-01-20', 'description' => 'OWNER CONTRIBUTION', 'amount' => 10000, 'type' => 'credit'],

            // Debits (should be ignored)
            ['date' => '2024-01-21', 'description' => 'CHECK #1234', 'amount' => 2000, 'type' => 'debit'],
            ['date' => '2024-01-22', 'description' => 'ONDECK DAILY PAYMENT', 'amount' => 500, 'type' => 'debit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(8500, $result['true_revenue']); // Square + Stripe + Zelle
        $this->assertEquals(40000, $result['excluded_amount']); // OnDeck + Transfer + Owner
        $this->assertEquals(6, $result['counts']['total']); // Only credits counted
    }

    /** @test */
    public function it_calculates_correct_revenue_ratio()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 8000, 'type' => 'credit'],
            ['date' => '2024-01-16', 'description' => 'ONDECK FUNDING', 'amount' => 2000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        $this->assertEquals(8000, $result['true_revenue']);
        $this->assertEquals(2000, $result['excluded_amount']);
        $this->assertEquals(80.00, $result['revenue_ratio']); // 8000 / 10000 = 80%
    }

    // =========================================================================
    // MONTHLY BREAKDOWN TESTS
    // =========================================================================

    /** @test */
    public function it_calculates_daily_revenue_using_business_days()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 21670, 'type' => 'credit'],
        ];

        $result = $this->engine->getMonthlyBreakdown($transactions);

        $this->assertCount(1, $result);
        $this->assertEquals(21670, $result[0]['true_revenue']);
        $this->assertEquals(1000, $result[0]['daily_true_revenue']); // 21670 / 21.67 = 1000
    }

    /** @test */
    public function it_groups_transactions_by_month()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 10000, 'type' => 'credit'],
            ['date' => '2024-01-20', 'description' => 'STRIPE TRANSFER', 'amount' => 5000, 'type' => 'credit'],
            ['date' => '2024-02-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 12000, 'type' => 'credit'],
            ['date' => '2024-02-20', 'description' => 'ONDECK FUNDING', 'amount' => 25000, 'type' => 'credit'], // Excluded
        ];

        $result = $this->engine->getMonthlyBreakdown($transactions);

        $this->assertCount(2, $result);

        // January
        $this->assertEquals('2024-01', $result[0]['month_key']);
        $this->assertEquals(15000, $result[0]['true_revenue']);
        $this->assertEquals(0, $result[0]['excluded']);

        // February
        $this->assertEquals('2024-02', $result[1]['month_key']);
        $this->assertEquals(12000, $result[1]['true_revenue']);
        $this->assertEquals(25000, $result[1]['excluded']);
    }

    // =========================================================================
    // HEURISTIC TESTS
    // =========================================================================

    /** @test */
    public function it_flags_large_deposits_for_review()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'DEPOSIT FROM UNKNOWN', 'amount' => 75000, 'type' => 'credit'],
        ];

        $result = $this->engine->classify($transactions[0]);

        $this->assertEquals('needs_review', $result['classification']);
        $this->assertStringContainsString('Large deposit', $result['reason']);
    }

    /** @test */
    public function it_flags_suspicious_loan_amounts_for_review()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'UNKNOWN DEPOSIT', 'amount' => 25000, 'type' => 'credit'],
        ];

        $result = $this->engine->classify($transactions[0]);

        $this->assertEquals('needs_review', $result['classification']);
    }

    // =========================================================================
    // MCA PAYMENT DETECTION TESTS
    // =========================================================================

    /** @test */
    public function it_detects_mca_payments_in_debits()
    {
        $transactions = [
            ['date' => '2024-01-15', 'description' => 'ONDECK PAYMENT', 'amount' => 500, 'type' => 'debit'],
            ['date' => '2024-01-16', 'description' => 'ONDECK PAYMENT', 'amount' => 500, 'type' => 'debit'],
            ['date' => '2024-01-17', 'description' => 'KABBAGE DAILY', 'amount' => 300, 'type' => 'debit'],
            ['date' => '2024-01-18', 'description' => 'KABBAGE DAILY', 'amount' => 300, 'type' => 'debit'],
        ];

        $result = $this->engine->detectMcaPayments($transactions);

        $this->assertEquals(2, $result['active_positions']); // OnDeck + Kabbage
        $this->assertGreaterThan(0, $result['total_daily_payment']);
    }

    // =========================================================================
    // MCA CAPACITY CALCULATION TESTS
    // =========================================================================

    /** @test */
    public function it_calculates_mca_capacity_correctly()
    {
        $monthlyRevenue = 100000;
        $existingDailyPayment = 500; // Already paying $500/day

        $result = $this->engine->calculateMcaCapacity($monthlyRevenue, $existingDailyPayment);

        // Daily revenue = 100000 / 21.67 = 4615.14
        $this->assertEqualsWithDelta(4615.14, $result['daily_true_revenue'], 1);

        // Max daily payment (20%) = 4615.14 * 0.20 = 923.03
        $this->assertEqualsWithDelta(923.03, $result['max_daily_payment'], 1);

        // Remaining capacity = 923.03 - 500 = 423.03
        $this->assertEqualsWithDelta(423.03, $result['remaining_daily_capacity'], 1);

        // Current withhold = 500 / 4615.14 = 10.83%
        $this->assertEqualsWithDelta(10.83, $result['current_withhold_percent'], 0.5);

        $this->assertFalse($result['at_capacity']);
        $this->assertTrue($result['can_take_position']);
    }

    /** @test */
    public function it_detects_merchant_at_capacity()
    {
        $monthlyRevenue = 100000;
        $existingDailyPayment = 1000; // Already paying $1000/day (>20%)

        $result = $this->engine->calculateMcaCapacity($monthlyRevenue, $existingDailyPayment);

        $this->assertTrue($result['at_capacity']);
        $this->assertFalse($result['can_take_position']);
        $this->assertEquals(0, $result['remaining_daily_capacity']);
    }

    // =========================================================================
    // REAL WORLD EXAMPLE TESTS
    // =========================================================================

    /** @test */
    public function real_world_example_100k_monthly_revenue()
    {
        // Real-world example: $100,000/month merchant with existing MCA
        $transactions = [
            // Week 1 - Card settlements
            ['date' => '2024-01-02', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 12000, 'type' => 'credit'],
            ['date' => '2024-01-03', 'description' => 'STRIPE TRANSFER', 'amount' => 8000, 'type' => 'credit'],
            ['date' => '2024-01-05', 'description' => 'TRANSFER FROM SAVINGS', 'amount' => 5000, 'type' => 'credit'], // EXCLUDE

            // Week 2
            ['date' => '2024-01-08', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 15000, 'type' => 'credit'],
            ['date' => '2024-01-10', 'description' => 'ONDECK CAPITAL', 'amount' => 25000, 'type' => 'credit'], // EXCLUDE - MCA
            ['date' => '2024-01-12', 'description' => 'STRIPE TRANSFER', 'amount' => 10000, 'type' => 'credit'],

            // Week 3
            ['date' => '2024-01-15', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 18000, 'type' => 'credit'],
            ['date' => '2024-01-17', 'description' => 'ZELLE FROM ABC CUSTOMER', 'amount' => 2000, 'type' => 'credit'],

            // Week 4
            ['date' => '2024-01-22', 'description' => 'SQUARE INC DEPOSIT', 'amount' => 20000, 'type' => 'credit'],
            ['date' => '2024-01-24', 'description' => 'SHOPIFY PAYOUT', 'amount' => 15000, 'type' => 'credit'],
        ];

        $result = $this->engine->calculateTrueRevenue($transactions);

        // True Revenue should be: 12k + 8k + 15k + 10k + 18k + 2k + 20k + 15k = $100,000
        $this->assertEquals(100000, $result['true_revenue']);

        // Excluded should be: 5k (transfer) + 25k (MCA) = $30,000
        $this->assertEquals(30000, $result['excluded_amount']);

        // Revenue ratio: 100k / 130k = 76.92%
        $this->assertEquals(76.92, $result['revenue_ratio']);
    }

    /** @test */
    public function real_world_example_second_position_calculation()
    {
        // Merchant with $100k/month True Revenue
        // Existing MCA: $12,000/month ($554.55/day at 21.67 days)
        // Calculate capacity for 2nd position

        $monthlyTrueRevenue = 100000;
        $existingMcaMonthly = 12000;
        $existingDailyPayment = $existingMcaMonthly / 21.67; // ~$554/day

        $capacity = $this->engine->calculateMcaCapacity($monthlyTrueRevenue, $existingDailyPayment);

        // Daily True Revenue = 100000 / 21.67 = $4,615
        $this->assertEqualsWithDelta(4615, $capacity['daily_true_revenue'], 5);

        // Max Daily Payment (20%) = $923
        $this->assertEqualsWithDelta(923, $capacity['max_daily_payment'], 5);

        // Existing Withhold = 554 / 4615 = 12%
        $this->assertEqualsWithDelta(12, $capacity['current_withhold_percent'], 1);

        // Remaining Capacity = 923 - 554 = $369/day (~8%)
        $this->assertEqualsWithDelta(369, $capacity['remaining_daily_capacity'], 5);

        // Can take 2nd position
        $this->assertTrue($capacity['can_take_position']);

        // Calculate max 2nd position funding:
        // Max daily payment: $369
        // 6-month term: 6 * 21.67 = 130 payments
        // Max payback: $369 * 130 = $47,970
        // At 1.45 factor: $47,970 / 1.45 = $33,082 max funding
        $remainingDaily = $capacity['remaining_daily_capacity'];
        $termMonths = 6;
        $businessDays = $termMonths * 21.67;
        $factorRate = 1.45;
        $maxPayback = $remainingDaily * $businessDays;
        $maxFunding = $maxPayback / $factorRate;

        $this->assertEqualsWithDelta(33000, $maxFunding, 500); // ~$33k max 2nd position
    }

    // =========================================================================
    // VOLATILITY METRICS TESTS
    // =========================================================================

    /** @test */
    public function it_calculates_volatility_metrics()
    {
        $monthlyBreakdown = [
            ['month_key' => '2024-01', 'true_revenue' => 95000],
            ['month_key' => '2024-02', 'true_revenue' => 100000],
            ['month_key' => '2024-03', 'true_revenue' => 105000],
            ['month_key' => '2024-04', 'true_revenue' => 110000],
        ];

        $metrics = $this->engine->getVolatilityMetrics($monthlyBreakdown);

        $this->assertTrue($metrics['has_data']);
        $this->assertEquals(4, $metrics['months_analyzed']);
        $this->assertEquals(102500, $metrics['average_revenue']);
        $this->assertEquals(95000, $metrics['min_revenue']);
        $this->assertEquals(110000, $metrics['max_revenue']);
        $this->assertEquals('increasing', $metrics['trend']['direction']);
        $this->assertEquals('low', $metrics['volatility_level']); // CV < 15%
    }

    /** @test */
    public function it_detects_high_volatility()
    {
        $monthlyBreakdown = [
            ['month_key' => '2024-01', 'true_revenue' => 50000],
            ['month_key' => '2024-02', 'true_revenue' => 120000],
            ['month_key' => '2024-03', 'true_revenue' => 60000],
            ['month_key' => '2024-04', 'true_revenue' => 130000],
        ];

        $metrics = $this->engine->getVolatilityMetrics($monthlyBreakdown);

        $this->assertEquals('high', $metrics['volatility_level']); // CV > 30%
    }
}
