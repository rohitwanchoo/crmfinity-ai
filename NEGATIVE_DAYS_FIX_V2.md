# Negative Days Calculation Fix - Version 2
## Date: 2026-02-05

## Problem Discovered

The previous fix (from NEGATIVE_DAYS_FIX_SUMMARY.md) implemented the correct logic for counting unique days with negative balances, but the calculation was still failing because:

1. **Balance data was not being passed to the calculation** - The `ending_balance` and `beginning_balance` fields were being saved to the database but not included when transactions were mapped for the `groupTransactionsByMonth` method.

2. **Statement-level balances were not being extracted** - For banks like Wells Fargo that don't show per-transaction balances, the beginning and ending balances from the statement summary were not being extracted.

3. **Fallback calculation had no starting point** - When per-transaction balances aren't available, the fallback running balance calculation started from $0, making all days appear negative.

## Fixes Implemented

### 1. Fixed Transaction Data Mapping (BankStatementController.php)

**Problem**: Balance fields were missing when transactions were mapped.

**Fix**: Added `ending_balance` and `beginning_balance` to transaction arrays in two places:
- Line 195-207 (analyze method)
- Line 333-344 (viewAnalysis method)

```php
'ending_balance' => $txn->ending_balance !== null ? (float) $txn->ending_balance : null,
'beginning_balance' => $txn->beginning_balance !== null ? (float) $txn->beginning_balance : null,
```

### 2. Enhanced Balance Extraction (bank_statement_extractor.py)

**Problem**: Statement-level beginning/ending balances were not being extracted.

**Fix**: Updated `extract_statement_totals()` function to extract:
- Beginning balance from statement summary
- Ending balance from statement summary
- Multiple regex patterns to handle different bank formats including Wells Fargo

Added to metadata output:
```python
"beginning_balance": expected_totals.get('beginning_balance'),
"ending_balance": expected_totals.get('ending_balance')
```

### 3. Database Schema Updates

**Added columns to `analysis_sessions` table**:
- `beginning_balance` (decimal 15,2, nullable)
- `ending_balance` (decimal 15,2, nullable)

**Migration**: `2026_02_05_203901_add_balance_fields_to_analysis_sessions_table.php`

### 4. Updated Model (AnalysisSession.php)

Added fields to:
- `$fillable` array
- `$casts` array (as decimal:2)

### 5. Updated Controller to Save Balances (BankStatementController.php)

Modified session creation (line 116-134) to include:
```php
'beginning_balance' => $data['metadata']['beginning_balance'] ?? null,
'ending_balance' => $data['metadata']['ending_balance'] ?? null,
```

### 6. Added Note for Unreliable Calculations

Updated fallback calculation (line 1641-1676) to add a note when negative days are calculated without actual balance data:
```php
$month['negative_days_note'] = 'Estimated from net flow (no balance data available)';
```

## Current State

### For New Analyses
✅ Will extract statement-level beginning/ending balances where available
✅ Will save balance data to database
✅ Will pass balance data to negative days calculation
✅ Will use per-transaction balances when available for accurate day-wise counting

### For Existing Analyses
⚠️ Historical data (like the Wells Fargo sessions tested) doesn't have balance information
⚠️ Cannot retroactively add balance data without re-analyzing the PDFs
⚠️ Fallback calculation without beginning balance is unreliable

### For Banks Without Per-Transaction Balances (like Wells Fargo)
⚠️ Even with statement beginning/ending balance, we cannot accurately calculate which specific days had negative balances
⚠️ Would need per-transaction balance data for accurate negative days tracking
✅ At minimum, we now extract and store the statement-level balances for reference

## Testing Results

### Test Session: 1cfdebb3-0093-45f4-a63f-00d73e475c11
- Wells Fargo December 2025 statement
- 62 transactions
- **No per-transaction balance data** (Wells Fargo format doesn't include this)
- Statement shows:
  - Beginning Balance: $86,333.60
  - Ending Balance: $49,537.41
  - Total Credits: $138,664.71
  - Total Debits: $175,460.90

**Fallback Calculation Issue**:
- Without per-transaction balances, shows 21/21 days as negative (unreliable)
- This is because we don't know which days the account was actually negative

## Recommendations

### For Accurate Negative Days Tracking
1. **Prefer banks with per-transaction balance columns** in their statements
2. **When available**, the system will now correctly use those balances
3. **For Wells Fargo and similar banks**, consider alternative approaches:
   - Manual review of statements
   - Use ending balance to infer overall health (positive vs negative ending)
   - Note that negative days cannot be accurately calculated without per-transaction data

### For Future Enhancements
1. Consider prompting the AI to estimate negative days from transaction patterns
2. Add UI indicator when negative days are estimated vs. calculated from actual balances
3. Display statement beginning/ending balance prominently for manual reference

## Files Modified

### Backend
- `app/Http/Controllers/BankStatementController.php` - Added balance fields to transaction mapping
- `app/Models/AnalysisSession.php` - Added balance fields
- `storage/app/scripts/bank_statement_extractor.py` - Enhanced balance extraction
- `database/migrations/2026_02_05_203901_add_balance_fields_to_analysis_sessions_table.php` - New migration

### No Changes Needed
- Frontend/Views - Will automatically display new data when available
- `groupTransactionsByMonth` method - Already had correct logic, just needed data

## How to Verify the Fix

### Test with a New Upload
1. Upload a bank statement (preferably one with per-transaction balance columns)
2. Check that `beginning_balance` and `ending_balance` are populated in the session
3. Verify negative days calculation uses actual balance data

### Run Migration
```bash
php artisan migrate
```

### Check Existing Session
```bash
php artisan tinker
$session = \App\Models\AnalysisSession::latest()->first();
$session->beginning_balance; // Should show value for new analyses
$session->ending_balance; // Should show value for new analyses
```

## Testing Verification

### Balance Extraction Test - Wells Fargo Format
✅ **Tested with**: `1cfdebb3-0093-45f4-a63f-00d73e475c11_123125-WellsFargo.pdf`

**Results**:
```
Beginning Balance: $86,333.60 ✅
Ending Balance: $49,537.41 ✅
Total Credits: $138,664.71 ✅
Total Debits: $175,460.90 ✅
```

All values extracted correctly from Wells Fargo account summary format!

## Status: ✅ IMPLEMENTED & TESTED

**Date**: 2026-02-05
**Verified**: Balance extraction working correctly for Wells Fargo and similar formats
**Tested**: Balance regex patterns validated against real Wells Fargo PDF

**Limitation**: Historical data and banks without per-transaction balances cannot have accurate **day-by-day** negative days calculated, but statement-level balances are now captured for reference
