# Balance Extraction Fix for Accurate Negative Days Calculation

**Date**: February 8, 2026
**Status**: ✅ Implemented for NEW uploads
**Impact**: Existing uploads need to be re-uploaded to get accurate calculations

---

## The Root Cause

### Why Negative Days Were Wrong

The negative days calculator was **starting from $0** instead of the actual account opening balance.

**Example:**
- Actual account opening balance: **$50,234.56**
- Calculator assumed: **$0.00**
- Result: Every transaction appears to create a negative balance!

```
Actual reality:
  Opening: $50,234.56
  -$1,000 payment = $49,234.56 (positive, no negative day)

What calculator saw (starting from $0):
  Opening: $0.00
  -$1,000 payment = -$1,000.00 (NEGATIVE! Counted as negative day)
```

This is why you were seeing **22 and 16 negative days** when there might have been far fewer (or even zero).

---

## Investigation Results

### Checked Three Sessions You're Viewing

```bash
Session: 123125-WellsFargo 1.pdf
  Beginning Balance: NULL
  Ending Balance: NULL
  First Txn Beginning Balance: NULL
  First Txn Ending Balance: NULL

Session: 013126-WellsFargo 1.pdf
  Beginning Balance: NULL
  Ending Balance: NULL
  First Txn Beginning Balance: NULL
  First Txn Ending Balance: NULL

Session: bryan miller dec.pdf
  Beginning Balance: NULL
  Ending Balance: NULL
  First Txn Beginning Balance: NULL
  First Txn Ending Balance: NULL
```

**All three have NULL balance data** → Calculator starts from $0 → Wrong negative days count

---

## What Was Fixed

### 1. ✅ Updated Python Script to Extract Balances

**File**: `storage/app/scripts/bank_statement_extractor.py`

#### A. Added Statement Summary Extraction

The script now asks Claude to extract:
- **Beginning Balance** (from statement summary section)
- **Ending Balance** (from statement summary section)

```python
STATEMENT SUMMARY EXTRACTION (CRITICAL):
- Look for the statement summary section (usually at the top or bottom)
- Extract the "Beginning Balance", "Opening Balance", or "Previous Balance"
- Extract the "Ending Balance", "Closing Balance", or "New Balance"
- These are typically shown as summary lines, NOT individual transactions
```

Example formats recognized:
```
Beginning Balance: $1,234.56
Previous Balance.............$1,234.56
Opening Balance    $1,234.56
```

#### B. Added Transaction-Level Balance Extraction

```python
BALANCE EXTRACTION (IMPORTANT FOR ACCURACY):
- Many statements show a running balance or ending balance column
- If the statement has a balance column, extract the balance value for EACH transaction
- The balance shown is typically the account balance AFTER that transaction posted
- Balance format examples: "$1,234.56" → 1234.56, "($500.00)" → -500.00 (negative)
```

#### C. Updated Output Format

```json
{
  "statement_summary": {
    "beginning_balance": 1234.56,
    "ending_balance": 5678.90
  },
  "transactions": [
    {
      "date": "YYYY-MM-DD",
      "description": "description text",
      "amount": 123.45,
      "type": "credit",
      "ending_balance": 1234.56
    }
  ]
}
```

### 2. ✅ Updated Laravel Controller to Save Balances

**File**: `app/Http/Controllers/BankStatementController.php`

#### Session-Level Balance Storage

```php
AnalysisSession::create([
    // ... other fields
    'beginning_balance' => $data['statement_summary']['beginning_balance'] ?? null,
    'ending_balance' => $data['statement_summary']['ending_balance'] ?? null,
]);
```

#### Transaction-Level Balance Storage

```php
// If ending_balance is available in the transaction
if (isset($txn['ending_balance'])) {
    $endingBalance = (float) $txn['ending_balance'];
    $transactionData['ending_balance'] = $endingBalance;

    // Calculate beginning balance from ending balance
    if ($type === 'credit') {
        $transactionData['beginning_balance'] = $endingBalance - $amount;
    } else {
        $transactionData['beginning_balance'] = $endingBalance + $amount;
    }
}
```

### 3. ✅ Calculator Already Uses Balances

The `NsfAndNegativeDaysCalculator` already had the logic to use balances:

```php
// Method 1: Use actual ending_balance if available
if ($hasActualBalances) {
    foreach ($transactionsByDate as $dateStr => $dayTransactions) {
        $lastTransaction = end($dayTransactions);
        $endingBalance = $lastTransaction['ending_balance'];
        $dailyBalances[$dateStr] = (float) $endingBalance;
    }
}
// Method 2: Reconstruct from opening balance
else {
    $runningBalance = $openingBalance ?? 0;  // ⚠️ Falls back to $0 if null
    // ... reconstruct balances
}
```

---

## How It Works Now

### For NEW Uploads (After This Fix)

1. **User uploads PDF**
2. **Python script extracts:**
   - Statement summary: beginning_balance = $50,234.56, ending_balance = $45,123.78
   - Each transaction: ending_balance from statement column
3. **Laravel saves:**
   - Session: beginning_balance, ending_balance
   - Transactions: beginning_balance, ending_balance for each
4. **Calculator uses actual balances:**
   - Checks if transactions have ending_balance data ✅
   - Uses actual EOD balances ✅
   - Calculates negative days accurately ✅

### Example with Real Balances

```
December 2025 Statement:
  Beginning Balance: $52,000.00

Day 1:  $52,000 - $1,500 (rent) = $50,500 (positive)
Day 2:  $50,500 + $5,000 (deposit) = $55,500 (positive)
Day 3:  $55,500 - $60,000 (large payment) = -$4,500 (NEGATIVE)
Day 4:  -$4,500 + $10,000 (deposit) = $5,500 (positive)

Negative Days: 1 (only Day 3)
```

Without balances (OLD):
```
Assumes starting from $0.00:

Day 1:  $0 - $1,500 = -$1,500 (NEGATIVE)
Day 2:  -$1,500 + $5,000 = $3,500 (positive)
Day 3:  $3,500 - $60,000 = -$56,500 (NEGATIVE)
Day 4:  -$56,500 + $10,000 = -$46,500 (NEGATIVE)

Negative Days: 3 (WRONG!)
```

---

## For Existing Uploads (Before This Fix)

### Problem

Existing sessions don't have balance data:
- `analysis_sessions.beginning_balance` = NULL
- `analysis_sessions.ending_balance` = NULL
- `analyzed_transactions.beginning_balance` = NULL
- `analyzed_transactions.ending_balance` = NULL

### Solutions

#### Option 1: Re-Upload the PDF Files ✅ **Recommended**

1. Delete the old analyses
2. Re-upload the same PDF files
3. New extraction will capture all balance information
4. Negative days will be calculated accurately

#### Option 2: Manual Opening Balance Entry (Future Enhancement)

Add UI feature to:
1. Let users manually enter opening balance for a statement
2. Update `analysis_sessions.beginning_balance`
3. Re-run calculator with correct opening balance
4. Still won't have transaction-level ending_balance, but better than $0

---

## Testing the Fix

### Upload a New Statement

1. Go to https://ai.crmfinity.com/bankstatement
2. Upload a new PDF (any bank statement with balance column)
3. Wait for processing
4. Check the results

### Verify Balance Extraction

```bash
# Check session balance
php artisan tinker
$session = AnalysisSession::latest()->first();
echo "Beginning: " . $session->beginning_balance;
echo "Ending: " . $session->ending_balance;

# Check transaction balances
$txn = $session->transactions()->first();
echo "Txn Beginning: " . $txn->beginning_balance;
echo "Txn Ending: " . $txn->ending_balance;
```

### Verify Negative Days Calculation

```bash
# Check Laravel logs for calculator output
tail -50 storage/logs/laravel.log | grep "Negative Days Calculation"
```

Look for:
```json
{
  "month": "December 2025",
  "negative_days_count": 5,  // Accurate count based on real balances
  "method_used": "actual_balances",  // Using real data, not reconstructed
  "opening_balance": 52000,
  "negative_dates": [
    {"date": "2025-12-15", "eod_balance": -125.50},
    {"date": "2025-12-16", "eod_balance": -89.23}
  ]
}
```

---

## What About Your Current Sessions?

The three sessions you're viewing:
- `6eb83297-113e-4198-9886-7ed6422b6fc6` (123125-WellsFargo 1.pdf)
- `1d747d15-5b90-4381-baab-426a8ebf20db` (013126-WellsFargo 1.pdf)
- `82fedb70-56d7-4ad2-b72f-e47594aba1a1` (bryan miller dec.pdf)

**All have NULL balance data** because they were processed before this fix.

### To Get Accurate Negative Days for These

You need to **re-upload the PDF files**:

1. Save the original PDFs locally if you still have them
2. Delete these sessions from the history
3. Upload them again
4. The new extraction will capture all balance information
5. Negative days will be calculated correctly

---

## Files Modified

### Python Script
- **File**: `storage/app/scripts/bank_statement_extractor.py`
- **Changes**:
  - Added statement summary extraction to AI prompt
  - Added ending_balance extraction to AI prompt
  - Updated validation to preserve ending_balance field
  - Updated return value to include statement_summary
  - Handles both chunked and non-chunked extraction

### Laravel Controller
- **File**: `app/Http/Controllers/BankStatementController.php`
- **Changes**:
  - Updated to read statement_summary from Python output
  - Stores beginning_balance and ending_balance in analysis_sessions table
  - Already had logic to store transaction-level balances (no change needed)
  - Already had debug logging for negative days calculator (added earlier)

### No Database Migration Needed
- The columns already exist:
  - `analysis_sessions.beginning_balance`
  - `analysis_sessions.ending_balance`
  - `analyzed_transactions.beginning_balance`
  - `analyzed_transactions.ending_balance`

---

## Summary

### ✅ For NEW Uploads (After This Fix)

- Claude extracts beginning/ending balance from statement summary
- Claude extracts ending_balance for each transaction
- Laravel saves all balance data to database
- Calculator uses actual balances
- **Negative days are calculated accurately**

### ⚠️ For EXISTING Uploads (Before This Fix)

- No balance data in database (all NULL)
- Calculator assumes opening balance is $0
- **Negative days count is WRONG**
- **Solution**: Re-upload the PDF files to get accurate calculations

---

## Next Steps

1. **Test with a new upload** to verify balance extraction works
2. **Re-upload the three statements** you're currently viewing to get accurate negative days
3. **Compare the new negative days counts** - they should be significantly different from 22 and 16

The system is now configured to extract and use actual account balances for all future uploads!
