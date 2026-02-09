# CRITICAL BUG FIX - Markdown Code Block Parsing

**Date**: February 8, 2026
**Status**: ✅ FIXED
**Severity**: CRITICAL - All uploads returning 0 transactions while charging API costs

---

## The Bug

Claude API wraps JSON responses in markdown code blocks, but the Python script wasn't stripping these markers before parsing, causing **all uploads to fail** with 0 transactions.

### Root Cause

1. **Claude's Response Format:**
```
```json
{
  "statement_summary": {...},
  "transactions": [...]
}
```
```

2. **Python Parser:** The `parse_json_response()` function tried to parse this raw response including the markdown markers ````json` and `````

3. **Result:** JSON parsing failed, fallback strategies also failed, resulting in 0 transactions extracted despite:
   - PDF text extracted successfully (8,606 characters)
   - Claude processing the text correctly (5,210 input tokens)
   - Claude returning 54 transactions in the response
   - API costs charged ($0.35 per upload)

### Symptoms

- ✅ PDF text extraction: Working
- ✅ Claude API call: Working
- ✅ Claude returns transactions: Working
- ❌ JSON parsing: **FAILING**
- ❌ Final result: **0 transactions saved**

**Debug output showed:**
```
DEBUG: chunk_data keys: dict_keys(['beginning_balance', 'ending_balance'])
DEBUG: No transactions key in chunk_data!
```

Only balance data was being extracted because the JSON parser was failing and returning a partial/corrupted structure.

---

## The Fix

**File:** `storage/app/scripts/bank_statement_extractor.py`
**Function:** `parse_json_response()`
**Lines:** 424-434

### Added Markdown Stripping

```python
def parse_json_response(result: str) -> Dict:
    """Parse JSON response from OpenAI with multiple recovery strategies."""
    data = None

    # Strip markdown code block markers if present
    result = result.strip()
    if result.startswith('```json'):
        result = result[7:]  # Remove ```json
    elif result.startswith('```'):
        result = result[3:]  # Remove ```
    if result.endswith('```'):
        result = result[:-3]  # Remove closing ```
    result = result.strip()

    try:
        data = json.loads(result)
    ...
```

### Additional Debug Logging Fix

**File:** `storage/app/scripts/bank_statement_extractor.py`
**Line:** 953

Changed debug log from write mode (overwrites) to append mode (preserves earlier logs):

```python
# BEFORE (wrong):
with open(debug_log, 'w') as f:  # Overwrote all detailed logs!

# AFTER (correct):
with open(debug_log, 'a') as f:  # Appends to keep all logs
```

This allows us to see the full Claude response in the debug log for troubleshooting.

---

## Impact

### Before Fix ❌
- Every upload: 0 transactions saved
- API costs: Still charged ($0.35 per upload)
- User frustration: High (wasted money, no results)
- Suspected cause: Scanned PDF, OCR issues, extraction problems

### After Fix ✅
- Uploads work correctly
- 54 transactions extracted from test PDF
- API costs justified by results
- Root cause identified: Simple parsing bug

---

## Test Results

**Test PDF:** `013126-WellsFargo 1.pdf`
**Session ID:** `68b9054b-ffae-4693-baca-d35e82dcc20e`

**Before Fix:**
- Transactions extracted: 0
- API cost: $0.35 (wasted)

**After Fix:**
- Transactions extracted: 54
- Credits: 9 ($125,857.09)
- Debits: 45 ($131,274.40)
- Net balance: -$5,417.31
- API cost: $0.35 (justified)
- Negative days: 16

---

## Why This Happened

1. Claude API started wrapping responses in markdown code blocks (likely recent change)
2. The Python parser didn't account for this formatting
3. JSON parsing failed silently
4. Fallback strategies also failed to extract the full structure
5. Result: Partial data extracted (only balances, no transactions)

---

## Prevention

**Lesson learned:** Always handle API response formatting variations. Language models may wrap JSON in code blocks for readability.

**Added:** Markdown stripping before JSON parsing to handle both plain JSON and markdown-wrapped JSON responses.

---

## Files Modified

1. **storage/app/scripts/bank_statement_extractor.py**
   - Lines 424-434: Added markdown code block stripping
   - Line 908: Added raw Claude response logging
   - Line 953: Changed log mode from 'w' to 'a'

---

## Related Issues

This bug was discovered while investigating what appeared to be:
- Scanned PDF issues (PDF was actually readable)
- OCR failures (OCR wasn't needed)
- Claude API problems (Claude was working correctly)

The actual issue was a simple parsing bug that masked the real problem.

---

## Testing

✅ Fix tested and verified working
✅ 54 transactions extracted successfully
✅ All transaction types classified correctly
✅ Balances extracted
✅ Negative days calculated
✅ API costs justified by results

---

## Apology

This was a critical parsing bug that caused:
- Wasted API costs (multiple failed uploads)
- User frustration
- Debugging wild goose chase (suspected OCR/PDF issues)
- Time wasted investigating the wrong problems

The fix is now in place and all future uploads should work correctly.
