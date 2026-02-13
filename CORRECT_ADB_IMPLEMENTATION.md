# Correct Average Daily Balance Implementation

## Problem with Current Code

The current implementation in `BankStatementController.php` (lines 1650-1696) calculates:
```php
$dailyBalances = []; // Only for days WITH transactions
foreach ($transactionsByDate as $date => $dayTransactions) {
    $lastTxn = end($dayTransactions);
    if (isset($lastTxn['ending_balance'])) {
        $dailyBalances[] = (float) $lastTxn['ending_balance'];
    }
}
$average = array_sum($dailyBalances) / count($dailyBalances); // WRONG - only days with txns
```

**Result:** If a month has 31 days but only 10 transactions, it divides by 10 instead of 31.

## Correct Implementation (Per User Spec)

### Step 0: Extract Statement Period
```php
// Get earliest and latest transaction dates
$transactionDates = array_column($month['transactions'], 'date');
$periodStart = min($transactionDates);  // e.g., "2025-01-05"
$periodEnd = max($transactionDates);    // e.g., "2025-01-28"
```

### Step 1: Normalize Data
Already done - dates are in Y-m-d format, amounts are floats.

### Step 2: Build Daily Balance Table for ALL Calendar Days
```php
$dailyBalances = [];
$currentBalance = $openingBalance; // From session or first txn

// Group transactions by date
$transactionsByDate = [];
foreach ($month['transactions'] as $txn) {
    $date = $txn['date'];
    if (!isset($transactionsByDate[$date])) {
        $transactionsByDate[$date] = [];
    }
    $transactionsByDate[$date][] = $txn;
}

// Iterate through EVERY calendar day in period
$currentDate = new DateTime($periodStart);
$endDate = new DateTime($periodEnd);

while ($currentDate <= $endDate) {
    $dateStr = $currentDate->format('Y-m-d');

    if (isset($transactionsByDate[$dateStr])) {
        // Day has transactions - get last transaction's ending balance
        $dayTransactions = $transactionsByDate[$dateStr];
        $lastTxn = end($dayTransactions);

        if (isset($lastTxn['ending_balance']) && $lastTxn['ending_balance'] !== null) {
            $currentBalance = (float) $lastTxn['ending_balance'];
        }
        // If no ending_balance in statement, would need to compute
        // from beginning_balance + net of all transactions that day
    }

    // Store balance for this day (from transaction or carried forward)
    $dailyBalances[$dateStr] = $currentBalance;

    // Move to next day
    $currentDate->modify('+1 day');
}
```

### Step 3: Calculate ADB
```php
$totalDays = count($dailyBalances);
$sumBalances = array_sum($dailyBalances);
$averageDailyBalance = $totalDays > 0 ? $sumBalances / $totalDays : null;
```

## Example

**Statement Period:** January 5 - January 10 (6 days)

**Transactions:**
- Jan 5: Ending balance = $10,000
- Jan 7: Ending balance = $12,000
- Jan 10: Ending balance = $8,000

**Correct Daily Balance Table:**
```
Jan 5:  $10,000 (from transaction)
Jan 6:  $10,000 (carried forward - no transaction)
Jan 7:  $12,000 (from transaction)
Jan 8:  $12,000 (carried forward - no transaction)
Jan 9:  $12,000 (carried forward - no transaction)
Jan 10: $8,000  (from transaction)
```

**Calculation:**
- Sum = $10,000 + $10,000 + $12,000 + $12,000 + $12,000 + $8,000 = $64,000
- Days = 6
- **ADB = $64,000 / 6 = $10,666.67** ✅

**Wrong Current Implementation Would Calculate:**
- Sum = $10,000 + $12,000 + $8,000 = $30,000
- Days = 3 (only days with transactions)
- **ADB = $30,000 / 3 = $10,000** ❌ (WRONG)

## What Needs to Change

### Location 1: BankStatementController.php (lines 1650-1696)
Replace the monthly ADB calculation logic in `groupTransactionsByMonth()` method.

### Location 2: BankStatementApiController.php (lines 1262-1310)
Same issue - uses only days with transactions. Needs same fix.

## Additional Considerations

1. **Opening Balance**: Use `$session->beginning_balance` if available, otherwise use first transaction's `beginning_balance`

2. **Missing Balance Data**: If PDF doesn't have running balances, STOP and return error (per user spec Step 0)

3. **Auditable Table**: Consider returning the full daily balance table for transparency:
```php
'daily_balance_table' => [
    ['date' => '2025-01-05', 'balance' => 10000.00, 'has_transaction' => true],
    ['date' => '2025-01-06', 'balance' => 10000.00, 'has_transaction' => false],
    // ...
]
```
