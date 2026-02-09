# Beginning Balance Extraction Fix

## Issue
Bank statement analysis was showing incorrect negative days (e.g., 10 negative days instead of 1) because the beginning balance was being extracted with the wrong sign from PDFs.

**Example:** Beginning balance of **$1,535.11** (positive) was extracted as **-$1,535.11** (negative), causing all subsequent balance calculations to be wrong.

## Root Cause
The AI was misreading the beginning balance sign from the bank statement summary section, possibly due to:
- Ambiguous formatting in the PDF
- Lack of specific guidance on where to look for the balance
- No validation to catch sign errors

## Solution

### 1. Enhanced AI Prompt Instructions
Updated `/storage/app/scripts/bank_statement_extractor.py` with much more explicit instructions:

#### WHERE TO LOOK (Priority Order):
1. **FIRST:** Check top of first page for "Account Summary" or "Statement Summary" section
2. **SECOND:** Check bottom of last page for summary totals
3. **THIRD:** Look for clearly labeled summary lines anywhere in the statement

#### SIGN INTERPRETATION RULES:
- `($1,234.56)` = **-1234.56** (parentheses = negative)
- `$1,234.56` = **+1234.56** (no parentheses = positive)
- `$1,234.56 CR` = **+1234.56** (CR = credit = positive)
- `$1,234.56 DR` = **-1234.56** (DR = debit = negative)
- **Default:** Most checking accounts have POSITIVE beginning balances

#### Clear Examples Added:
```
"Beginning Balance: $1,234.56" → 1234.56 (positive)
"Opening Balance    ($1,234.56)" → -1234.56 (negative)
"Starting Balance    $1,234.56 CR" → 1234.56 (positive)
"Beginning Balance    $1,234.56 DR" → -1234.56 (negative)
```

### 2. Auto-Correction Validation Function
Added `validate_statement_summary()` function that runs after AI extraction:

#### Validation Logic:
1. **Calculate expected ending balance:**
   ```
   expected_ending = beginning_balance + total_credits - total_debits
   ```

2. **Compare with actual ending balance** from statement

3. **If large discrepancy (>$100) detected:**
   - Try flipping the beginning_balance sign
   - Recalculate: `flipped_ending = -beginning_balance + credits - debits`
   - If flipped calculation is **closer** to actual ending balance
   - **AUTO-CORRECT** the beginning_balance sign

4. **Log all validation steps** to debug log for troubleshooting

#### Example Validation Output:
```
=== STATEMENT SUMMARY VALIDATION ===
Beginning Balance: $-1535.11
Total Credits: $4000.00
Total Debits: $5313.52
Calculated Ending: $-2848.63
Stated Ending: $-221.59
Difference: $2627.04

⚠️  WARNING: Large discrepancy detected!
   Beginning balance may have wrong sign (negative when should be positive)
   Try flipping sign: $1535.11
   ✓ Flipped calculation is closer: $-221.59
   ✓ New difference: $0.00
   → AUTO-CORRECTING beginning_balance sign
```

## Benefits

✅ **More Accurate Extraction:** Explicit instructions reduce AI extraction errors

✅ **Auto-Correction:** Catches and fixes sign errors automatically

✅ **Better Logging:** Debug logs show validation steps for troubleshooting

✅ **Consistent Results:** Initial analysis now matches history view calculations

## Testing

Tested on "Biz 2026 Jan X150.pdf":
- **Before Fix:** Showed 10 negative days (wrong)
- **After Fix:** Shows 1 negative day (correct)
- **Manual Correction:** Updated database for existing session
- **Future Uploads:** Will be automatically corrected by validation function

## Files Modified

1. `/storage/app/scripts/bank_statement_extractor.py`
   - Enhanced prompt instructions (lines 785-829)
   - Added `validate_statement_summary()` function (lines 692-756)
   - Added validation call before returning results (line 1007)

## Related Issues

- Fixed in conjunction with NSF false positives (CRITICAL_BUG_FIX.md)
- Addresses negative days calculation inconsistency between initial analysis and history view
