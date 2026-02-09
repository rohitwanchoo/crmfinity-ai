<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCategory extends Model
{
    protected $fillable = [
        'description_pattern',
        'category',
        'subcategory',
        'transaction_type',
        'usage_count',
        'user_id',
    ];

    /**
     * Standard transaction categories
     */
    public static function getStandardCategories(): array
    {
        return [
            // Transfer Categories
            'zelle' => ['label' => 'Zelle', 'type' => 'both', 'color' => 'purple'],
            'venmo' => ['label' => 'Venmo', 'type' => 'both', 'color' => 'blue'],
            'paypal' => ['label' => 'PayPal', 'type' => 'both', 'color' => 'indigo'],
            'cash_app' => ['label' => 'Cash App', 'type' => 'both', 'color' => 'green'],
            'wire_transfer' => ['label' => 'Wire Transfer', 'type' => 'both', 'color' => 'cyan'],
            'ach_transfer' => ['label' => 'ACH Transfer', 'type' => 'both', 'color' => 'teal'],
            'internal_transfer' => ['label' => 'Internal Transfer', 'type' => 'both', 'color' => 'gray'],

            // Payment Methods
            'check' => ['label' => 'Check', 'type' => 'debit', 'color' => 'amber'],
            'atm' => ['label' => 'ATM', 'type' => 'debit', 'color' => 'orange'],
            'debit_card' => ['label' => 'Debit Card', 'type' => 'debit', 'color' => 'red'],
            'credit_card_payment' => ['label' => 'Credit Card Payment', 'type' => 'debit', 'color' => 'pink'],

            // Expense Categories (Debits)
            'rent' => ['label' => 'Rent/Lease', 'type' => 'debit', 'color' => 'blue'],
            'utilities' => ['label' => 'Utilities', 'type' => 'debit', 'color' => 'yellow'],
            'insurance' => ['label' => 'Insurance', 'type' => 'debit', 'color' => 'purple'],
            'payroll' => ['label' => 'Payroll', 'type' => 'debit', 'color' => 'green'],
            'tax_payment' => ['label' => 'Tax Payment', 'type' => 'debit', 'color' => 'red'],
            'vendor_payment' => ['label' => 'Vendor Payment', 'type' => 'debit', 'color' => 'indigo'],
            'supplier_payment' => ['label' => 'Supplier Payment', 'type' => 'debit', 'color' => 'cyan'],
            'subscription' => ['label' => 'Subscription', 'type' => 'debit', 'color' => 'pink'],

            // Income Categories (Credits)
            'sales_revenue' => ['label' => 'Sales Revenue', 'type' => 'credit', 'color' => 'green'],
            'payment_received' => ['label' => 'Payment Received', 'type' => 'credit', 'color' => 'emerald'],
            'refund' => ['label' => 'Refund', 'type' => 'credit', 'color' => 'lime'],
            'interest_income' => ['label' => 'Interest Income', 'type' => 'credit', 'color' => 'teal'],
            'dividend' => ['label' => 'Dividend', 'type' => 'credit', 'color' => 'cyan'],

            // Bank/Fee Categories
            'bank_fee' => ['label' => 'Bank Fee', 'type' => 'debit', 'color' => 'red'],
            'nsf_fee' => ['label' => 'NSF/Overdraft Fee', 'type' => 'debit', 'color' => 'red'],
            'service_charge' => ['label' => 'Service Charge', 'type' => 'debit', 'color' => 'orange'],
            'returned_unpaid' => ['label' => 'Returned Unpaid', 'type' => 'returned', 'color' => 'orange'],

            // MCA Categories
            'mca_payment' => ['label' => 'MCA Payment', 'type' => 'debit', 'color' => 'red'],
            'mca_funding' => ['label' => 'MCA Funding', 'type' => 'credit', 'color' => 'orange'],

            // Other
            'loan_payment' => ['label' => 'Loan Payment', 'type' => 'debit', 'color' => 'purple'],
            'loan_disbursement' => ['label' => 'Loan Disbursement', 'type' => 'credit', 'color' => 'indigo'],
            'other' => ['label' => 'Other', 'type' => 'both', 'color' => 'gray'],
        ];
    }

    /**
     * Get default category patterns for auto-classification
     */
    public static function getDefaultPatterns(): array
    {
        return [
            // Zelle
            ['pattern' => 'zelle', 'category' => 'zelle'],
            ['pattern' => 'zlle', 'category' => 'zelle'],
            ['pattern' => 'p2p zelle', 'category' => 'zelle'],

            // Venmo
            ['pattern' => 'venmo', 'category' => 'venmo'],
            ['pattern' => 'vnmo', 'category' => 'venmo'],

            // PayPal
            ['pattern' => 'paypal', 'category' => 'paypal'],
            ['pattern' => 'pp*', 'category' => 'paypal'],
            ['pattern' => 'pypl', 'category' => 'paypal'],

            // Cash App
            ['pattern' => 'cash app', 'category' => 'cash_app'],
            ['pattern' => 'cashapp', 'category' => 'cash_app'],
            ['pattern' => 'square cash', 'category' => 'cash_app'],

            // Wire Transfer
            ['pattern' => 'wire transfer', 'category' => 'wire_transfer'],
            ['pattern' => 'wire out', 'category' => 'wire_transfer'],
            ['pattern' => 'wire in', 'category' => 'wire_transfer'],
            ['pattern' => 'incoming wire', 'category' => 'wire_transfer'],
            ['pattern' => 'outgoing wire', 'category' => 'wire_transfer'],

            // ACH
            ['pattern' => 'ach debit', 'category' => 'ach_transfer'],
            ['pattern' => 'ach credit', 'category' => 'ach_transfer'],
            ['pattern' => 'ach transfer', 'category' => 'ach_transfer'],
            ['pattern' => 'ach payment', 'category' => 'ach_transfer'],

            // Check
            ['pattern' => 'check #', 'category' => 'check'],
            ['pattern' => 'check no', 'category' => 'check'],
            ['pattern' => 'ck #', 'category' => 'check'],
            ['pattern' => 'chk', 'category' => 'check'],
            ['pattern' => 'paper check', 'category' => 'check'],

            // ATM
            ['pattern' => 'atm withdrawal', 'category' => 'atm'],
            ['pattern' => 'atm cash', 'category' => 'atm'],
            ['pattern' => 'atm deposit', 'category' => 'atm'],
            ['pattern' => 'cash withdrawal', 'category' => 'atm'],

            // Debit Card
            ['pattern' => 'debit card', 'category' => 'debit_card'],
            ['pattern' => 'pos debit', 'category' => 'debit_card'],
            ['pattern' => 'visa debit', 'category' => 'debit_card'],
            ['pattern' => 'mc debit', 'category' => 'debit_card'],

            // Rent
            ['pattern' => 'rent payment', 'category' => 'rent'],
            ['pattern' => 'lease payment', 'category' => 'rent'],
            ['pattern' => 'monthly rent', 'category' => 'rent'],
            ['pattern' => 'property rent', 'category' => 'rent'],

            // Utilities
            ['pattern' => 'electric', 'category' => 'utilities'],
            ['pattern' => 'water', 'category' => 'utilities'],
            ['pattern' => 'gas bill', 'category' => 'utilities'],
            ['pattern' => 'utility bill', 'category' => 'utilities'],
            ['pattern' => 'internet', 'category' => 'utilities'],
            ['pattern' => 'phone bill', 'category' => 'utilities'],

            // Insurance
            ['pattern' => 'insurance payment', 'category' => 'insurance'],
            ['pattern' => 'insurance premium', 'category' => 'insurance'],
            ['pattern' => 'liability insurance', 'category' => 'insurance'],
            ['pattern' => 'workers comp', 'category' => 'insurance'],

            // Payroll
            ['pattern' => 'payroll', 'category' => 'payroll'],
            ['pattern' => 'salary', 'category' => 'payroll'],
            ['pattern' => 'wages', 'category' => 'payroll'],
            ['pattern' => 'employee payment', 'category' => 'payroll'],

            // Tax
            ['pattern' => 'tax payment', 'category' => 'tax_payment'],
            ['pattern' => 'irs payment', 'category' => 'tax_payment'],
            ['pattern' => 'estimated tax', 'category' => 'tax_payment'],
            ['pattern' => 'sales tax', 'category' => 'tax_payment'],

            // Bank Fees
            ['pattern' => 'monthly fee', 'category' => 'bank_fee'],
            ['pattern' => 'maintenance fee', 'category' => 'bank_fee'],
            ['pattern' => 'service fee', 'category' => 'bank_fee'],
            ['pattern' => 'account fee', 'category' => 'service_charge'],

            // NSF
            ['pattern' => 'nsf fee', 'category' => 'nsf_fee'],
            ['pattern' => 'insufficient funds', 'category' => 'nsf_fee'],
            ['pattern' => 'overdraft fee', 'category' => 'nsf_fee'],
            ['pattern' => 'od fee', 'category' => 'nsf_fee'],

            // Returned/Unpaid Items
            ['pattern' => 'returned unpaid', 'category' => 'returned_unpaid'],
            ['pattern' => 'items returned unpaid', 'category' => 'returned_unpaid'],
            ['pattern' => 'item returned unpaid', 'category' => 'returned_unpaid'],
            ['pattern' => 'returned item', 'category' => 'returned_unpaid'],
            ['pattern' => 'returned check', 'category' => 'returned_unpaid'],
            ['pattern' => 'returned ach', 'category' => 'returned_unpaid'],
            ['pattern' => 'returned payment', 'category' => 'returned_unpaid'],
            ['pattern' => 'dishonored', 'category' => 'returned_unpaid'],

            // Interest
            ['pattern' => 'interest earned', 'category' => 'interest_income'],
            ['pattern' => 'interest credit', 'category' => 'interest_income'],
            ['pattern' => 'interest payment', 'category' => 'interest_income'],

            // Refund
            ['pattern' => 'refund', 'category' => 'refund'],
            ['pattern' => 'return', 'category' => 'refund'],
            ['pattern' => 'reversal', 'category' => 'refund'],
            ['pattern' => 'chargeback', 'category' => 'refund'],
        ];
    }

    /**
     * Normalize description pattern (similar to RevenueClassification)
     */
    public static function normalizePattern(string $description): string
    {
        $normalized = $description;

        // Remove dates
        $normalized = preg_replace('/\d{1,2}\/\d{1,2}(\/\d{2,4})?/', '', $normalized);
        $normalized = preg_replace('/\d{1,2}-\d{1,2}(-\d{2,4})?/', '', $normalized);

        // Replace long numbers with placeholder
        $normalized = preg_replace('/\d{6,}/', '#ID#', $normalized);

        // Remove dollar amounts
        $normalized = preg_replace('/\$[\d,]+\.?\d*/', '', $normalized);

        // Clean up whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);

        return strtolower($normalized);
    }

    /**
     * Get category for a transaction description using fuzzy matching
     */
    public static function getCategoryForDescription(string $description, string $transactionType): ?array
    {
        $normalized = self::normalizePattern($description);

        // Try learned patterns first
        $learned = self::where('description_pattern', $normalized)
            ->whereIn('transaction_type', [$transactionType, 'both'])
            ->orderBy('usage_count', 'desc')
            ->first();

        if ($learned) {
            return [
                'category' => $learned->category,
                'subcategory' => $learned->subcategory,
                'source' => 'learned',
            ];
        }

        // Try fuzzy matching with learned patterns
        $allLearned = self::whereIn('transaction_type', [$transactionType, 'both'])->get();

        foreach ($allLearned as $pattern) {
            if (self::isSimilarPattern($normalized, $pattern->description_pattern)) {
                return [
                    'category' => $pattern->category,
                    'subcategory' => $pattern->subcategory,
                    'source' => 'learned_fuzzy',
                ];
            }
        }

        // Try default patterns
        $defaultPatterns = self::getDefaultPatterns();
        foreach ($defaultPatterns as $default) {
            if (stripos($normalized, $default['pattern']) !== false) {
                return [
                    'category' => $default['category'],
                    'subcategory' => null,
                    'source' => 'default',
                ];
            }
        }

        return null;
    }

    /**
     * Check if two patterns are similar (fuzzy matching)
     */
    private static function isSimilarPattern(string $pattern1, string $pattern2): bool
    {
        // Exact match
        if ($pattern1 === $pattern2) {
            return true;
        }

        // One contains the other
        if (stripos($pattern1, $pattern2) !== false || stripos($pattern2, $pattern1) !== false) {
            return true;
        }

        // Word matching (60% threshold)
        $words1 = array_filter(explode(' ', $pattern1), fn($w) => strlen($w) > 3);
        $words2 = array_filter(explode(' ', $pattern2), fn($w) => strlen($w) > 3);

        if (count($words1) >= 2) {
            $matchCount = 0;
            foreach ($words1 as $word) {
                if (stripos($pattern2, $word) !== false) {
                    $matchCount++;
                }
            }
            if ($matchCount >= ceil(count($words1) * 0.6)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a category classification for learning
     */
    public static function recordCategory(
        string $description,
        string $category,
        ?string $subcategory,
        string $transactionType,
        ?int $userId = null
    ): void {
        $normalized = self::normalizePattern($description);

        $existing = self::where('description_pattern', $normalized)
            ->where('category', $category)
            ->first();

        if ($existing) {
            $existing->increment('usage_count');
            $existing->update([
                'subcategory' => $subcategory,
                'transaction_type' => $transactionType,
            ]);
        } else {
            self::create([
                'description_pattern' => $normalized,
                'category' => $category,
                'subcategory' => $subcategory,
                'transaction_type' => $transactionType,
                'usage_count' => 1,
                'user_id' => $userId,
            ]);
        }
    }
}
