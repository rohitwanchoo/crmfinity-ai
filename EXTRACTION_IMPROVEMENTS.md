# Bank Statement Analysis - Determinism Improvements

## Problem Summary
The analysis of "Merchant_November Bank - 2026-01-28T1634" was producing inconsistent results:
- **Run 1**: 120 transactions, Debits: $21,919.16
- **Run 2**: 121 transactions, Debits: $25,401.62
- **Run 3**: 120 transactions, Debits: $21,919.16

**Root Cause**: A $3,482.46 mortgage payment ("Newrez-Shellpoin ACH Pmt") was randomly missing from 2 out of 3 runs due to OpenAI API non-determinism.

---

## Implementation Details

### 1. ✅ Auto-Retry with Statement Total Validation

**Location**: `storage/app/scripts/bank_statement_extractor.py` (lines 1067-1220)

**How It Works**:
1. **Extracts up to 3 times** per PDF analysis
2. **Scores each attempt** (0-100) based on how closely it matches statement totals
3. **Automatically selects the best result** with the highest quality score
4. **Early exit** if quality score ≥ 95% and error < $10

**Quality Scoring Algorithm**:
```python
score = 100
if expected_credits:
    credit_error_pct = (credit_difference / expected_credits) * 100
    score -= min(50, credit_error_pct)  # Max 50 point penalty

if expected_debits:
    debit_error_pct = (debit_difference / expected_debits) * 100
    score -= min(50, debit_error_pct)  # Max 50 point penalty
```

**Retry Conditions**:
- Always runs at least 1 attempt
- Retries if error > $500 (significant mismatch)
- Stops early if score ≥ 95% and error < $10
- Maximum 3 attempts to balance accuracy vs. cost

**Result Metadata** (new fields):
```json
{
  "metadata": {
    "extraction_attempt": 2,
    "total_attempts": 2,
    "best_quality_score": 98.5,
    "retry_summary": [
      {
        "attempt": 1,
        "quality_score": 85.3,
        "transaction_count": 120,
        "debit_total": 21919.16
      },
      {
        "attempt": 2,
        "quality_score": 98.5,
        "transaction_count": 121,
        "debit_total": 25401.62
      }
    ]
  },
  "validation": {
    "quality_score": 98.5
  }
}
```

---

### 2. ✅ Enhanced Model Determinism

**Changes Made**:

#### A. Added `seed=42` Parameter (lines 751, 789)
```python
response = client.chat.completions.create(
    model=model,
    messages=[...],
    temperature=0,
    seed=42,  # NEW: Improves determinism
    response_format={"type": "json_object"}
)
```

**Impact**: The `seed` parameter helps OpenAI's API produce more consistent results across runs.

#### B. Strengthened System Prompt (lines 586-710)
**Key Improvements**:

1. **Zero Tolerance Header**:
   ```
   ⚠️ CRITICAL - ZERO TOLERANCE FOR MISSING TRANSACTIONS:
   - Your extraction will be VALIDATED against statement totals
   - Missing even ONE transaction = EXTRACTION FAILURE
   ```

2. **Large Transaction Focus**:
   ```
   LARGE TRANSACTIONS - EXTRA VIGILANT:
   - Transactions over $500 are CRITICAL - never miss these
   - Mortgage payments, loan payments, insurance payments are typically $1,000-$5,000
   - Look for patterns: "ACH PMT", "Payment", "Loan", "Mortgage", "Insurance"
   ```

3. **Methodical Extraction Process**:
   ```
   EXTRACTION METHODOLOGY (FOLLOW THIS ORDER):
   1. First, scan the ENTIRE document to identify all transaction sections
   2. Note section headers: "DEPOSITS", "CREDITS", "WITHDRAWALS", "DEBITS", "CHECKS"
   3. Extract transactions section by section
   4. For each section, extract EVERY line that contains a transaction
   5. Pay special attention to multi-column tables and large amounts
   ```

4. **Verification Checklist**:
   ```
   VERIFICATION CHECKLIST (Before returning results):
   ✓ Did you extract ALL sections from the statement?
   ✓ Did you check for large transactions (>$500)?
   ✓ Did you read multi-column tables completely across each row?
   ✓ Did you extract ALL ACH payments, mortgage/loan payments?
   ✓ Count your transactions - does it seem complete?
   ```

5. **Explicit ACH Payment Instructions**:
   - Added "ACH Pmt", "ACH Debit", "ACH Payment" as explicit DEBIT indicators
   - Added clarification: "Bank/Lender name + Payment" = DEBIT (loan payment)

---

### 3. ✅ Configuration Updates

**Location**: `config/services.php`

```php
'openai' => [
    'key' => env('OPENAI_API_KEY'),
    'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),  // NEW
    'enable_retry' => env('OPENAI_ENABLE_RETRY', true),        // NEW
],
```

**Location**: `app/Http/Controllers/BankStatementController.php` (line 60-62)

```php
$defaultModel = config('services.openai.default_model', 'gpt-4o');
$model = $request->input('model', $defaultModel);
```

**Optional .env Configuration**:
```bash
# Set custom default model (optional, defaults to gpt-4o)
OPENAI_DEFAULT_MODEL=gpt-4o

# Disable retry if needed (optional, defaults to true)
OPENAI_ENABLE_RETRY=true
```

---

## Expected Improvements

### Before Implementation
- **Consistency**: ~66% (2 out of 3 runs matched)
- **Missing Transaction Rate**: 1 critical transaction missed per 120 (0.8%)
- **Dollar Impact**: $3,482.46 discrepancy (14% error on debits)
- **User Trust**: Low - cannot rely on results

### After Implementation
- **Consistency**: ~95%+ (best of 3 attempts selected)
- **Missing Transaction Rate**: <0.1% (caught by validation)
- **Dollar Impact**: <$10 average error (0.04% error)
- **User Trust**: High - validated results with quality scores

---

## Cost Impact

### Additional API Costs
- **Average case**: 1-2 attempts (no change to 50% increase)
- **Worst case**: 3 attempts (100% increase)
- **Typical scenario**:
  - Small statements (<5 pages): 1 attempt (95%+ quality on first try)
  - Large statements (>10 pages): 2 attempts average
  - Complex/poor quality PDFs: 3 attempts

### Cost Examples (gpt-4o model)
- **Small statement** (3 pages, ~300 transactions):
  - Before: $0.15 per analysis
  - After: $0.15-$0.30 per analysis (1-2 attempts)

- **Large statement** (15 pages, ~600 transactions):
  - Before: $0.60 per analysis
  - After: $0.60-$1.20 per analysis (1-2 attempts)

**ROI Justification**: The small cost increase (0-100%) eliminates manual review time ($20-50/hr) required to catch and fix missing transactions.

---

## Testing the Improvements

### Test the Same File Again
```bash
# Re-analyze the Merchant_November file
# The system should now:
# 1. Try up to 3 extractions
# 2. Detect the $3,482 Newrez payment consistently
# 3. Return a quality score of 95%+
# 4. Show retry_summary if multiple attempts were needed
```

### Monitor Quality Scores
Check the `quality_score` field in results:
- **95-100**: Excellent match, high confidence
- **85-94**: Good match, minor discrepancies
- **70-84**: Acceptable match, review recommended
- **<70**: Poor match, manual review required

---

## Monitoring & Alerts

### Add to Results View
Display the quality score prominently:
```php
@if(isset($result['validation']['quality_score']))
    <div class="quality-indicator {{ $result['validation']['quality_score'] >= 95 ? 'success' : 'warning' }}">
        Quality Score: {{ $result['validation']['quality_score'] }}%
        @if(isset($result['metadata']['total_attempts']) && $result['metadata']['total_attempts'] > 1)
            (Best of {{ $result['metadata']['total_attempts'] }} attempts)
        @endif
    </div>
@endif
```

### Log Low-Quality Extractions
Add to controller after line 189:
```php
if (isset($data['validation']['quality_score']) && $data['validation']['quality_score'] < 85) {
    Log::warning('Low quality extraction', [
        'file' => $filename,
        'session_id' => $sessionId,
        'quality_score' => $data['validation']['quality_score'],
        'warnings' => $data['validation']['warnings']
    ]);
}
```

---

## Next Steps

1. **Test**: Re-analyze the Merchant_November file to verify consistency
2. **Monitor**: Track quality scores over the next 50 analyses
3. **Adjust**: If quality scores are consistently 99%+, consider reducing max retries to 2
4. **Report**: Add quality score to the session history view for transparency

---

## Technical Notes

### Why Retry Works
- LLM outputs have inherent randomness even at temperature=0
- Multiple attempts with validation scoring acts as an "ensemble" approach
- Missing large transactions (>$500) now triggers automatic retry
- Best result selection ensures consistency without manual intervention

### Performance Impact
- Average latency increase: 0-100% (depends on retry count)
- Cache-friendly: Same PDF text reused across retries
- Early exit optimization: Stops at first good result (95%+ score)

### Limitations
- Cannot fix issues with the PDF text itself (OCR errors, etc.)
- Still requires statement totals in PDF header for validation
- Quality score only as good as the expected totals accuracy
