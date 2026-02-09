# Debugging Missing Transactions

**Problem:** Getting 110 transactions instead of 128
- Expected: 99 debits + 29 credits = 128 total
- Getting: 81 debits + 29 credits = 110 total
- **Missing: 18 debit transactions**

Credits are perfect (29/29) ✅  
Debits are missing 18 (81/99) ❌

---

## Step 1: Check the Extracted Text

First, let's see what the PDF extraction produced:

```bash
cd /var/www/html/crmfinity_laravel

# View the extracted text
cat storage/app/uploads/debug_extracted_text.txt
```

**Questions to answer:**
1. Are all 128 transactions visible in the extracted text?
2. Are debits and credits in separate sections?
3. Is the formatting consistent?

---

## Step 2: Check the Logs

Look at the processing logs to see what happened:

```bash
# View recent SmartMCA logs
grep "SmartMCA" storage/logs/laravel.log | tail -50

# Look for missing transaction warnings
grep "WARNING" storage/logs/laravel.log | tail -20

# Check for reconciliation info
grep "INFO:" storage/logs/laravel.log | tail -50
```

**Look for:**
- `Skipping balance line:` - Balance lines being filtered (expected)
- `Added X transactions missed by AI` - Reconciliation working
- `WARNING: Debit mismatch` - Total doesn't match statement
- `Extracted AZ withdrawal` / `Extracted Wire Transfer Fee` - Special patterns caught

---

## Step 3: Potential Causes of Missing Transactions

### Cause 1: AI Not Extracting All Transactions

**Check Claude's output:**
```bash
grep "Claude API (Sonnet):" storage/logs/laravel.log | tail -5
```

If token count is near 16,384, the response might be truncated.

**Solution:**
- Enable page-by-page processing for this statement
- Or increase token limit (already at 16K)

### Cause 2: Overly Aggressive Filtering

**Current filters in `parse_transactions_ai.py`:**

1. **Line 1088:** Skip amounts <= $0
   ```python
   if amount <= 0:
       continue
   ```

2. **Line 1110:** Skip empty descriptions
   ```python
   if not description:
       continue
   ```

3. **Line 1122:** Skip balance lines
   ```python
   if is_balance_line:
       print(f"INFO: Skipping balance line...")
       continue
   ```

4. **Line 1161:** Skip amounts < $1.00 or > $50,000 (in reconciliation)
   ```python
   if src_amt < 1.0 or src_amt > 50000:
       continue
   ```

**Problem:** #4 could filter out legitimate small transactions!

### Cause 3: Duplicate Detection Too Aggressive

**Line 1173:** Checks if amount+date combo already exists
```python
if (src_amt, src_date_prefix) in ai_amounts_dates:
    continue  # Skip as duplicate
```

**Problem:** If you have two transactions with the same amount on the same day, the second one gets filtered as a "duplicate"!

Example:
- 01/15/2025: Check #100 for $500
- 01/15/2025: Check #101 for $500
- **Second one gets skipped!**

---

## Step 4: Quick Fixes to Try

### Fix 1: Remove Small Amount Filter

**File:** `storage/app/scripts/parse_transactions_ai.py`  
**Line:** 1160-1162

```python
# BEFORE
if src_amt < 1.0 or src_amt > 50000:
    continue

# AFTER - Only skip extremely large amounts
if src_amt > 100000:  # $100K limit instead of $50K
    continue
# Remove the < 1.0 check - allow small transactions
```

### Fix 2: Improve Duplicate Detection

**Line:** 1173

```python
# BEFORE - Checks amount + date only
if (src_amt, src_date_prefix) in ai_amounts_dates:
    continue

# AFTER - Check amount + date + first 20 chars of description
src_desc_prefix = src_desc[:20].lower()
if (src_amt, src_date_prefix, src_desc_prefix) in ai_amounts_dates:
    continue
```

Also update where we add to the set (line 1205):
```python
# BEFORE
ai_amounts_dates.add((src_amt, normalized_date[5:10]))

# AFTER
desc_prefix = src_txn['description'][:20].lower()
ai_amounts_dates.add((src_amt, normalized_date[5:10], desc_prefix))
```

### Fix 3: Add Detailed Logging

Add before line 1162:

```python
# Log what's being filtered
if src_amt < 1.0:
    print(f"DEBUG: Filtered small amount: ${src_amt} - {src_desc[:40]}", file=sys.stderr)
if src_amt > 50000:
    print(f"DEBUG: Filtered large amount: ${src_amt} - {src_desc[:40]}", file=sys.stderr)
```

---

## Step 5: Manual Verification

Use the bank statement PDF to manually check:

1. **Count debits in PDF:** Should be 99
2. **Count debits in extracted text:** Should be 99
3. **Count debits from AI:** Currently 81

**Gap Analysis:**
- If extracted text has all 99 → AI is missing them
- If extracted text has only 81 → PDF extraction problem
- If extracted text has 99 but AI returns 81 → Filtering problem

---

## Step 6: Test with Improved Code

Apply the fixes above and reprocess the statement:

```bash
# Clear caches
cd /var/www/html/crmfinity_laravel
php artisan cache:clear

# Re-upload the same statement
# Check logs for improvement
```

---

## Expected Root Causes (Ranked by Likelihood)

### 1. **Duplicate Detection Too Strict** (80% probability)
Multiple transactions with same amount on same day are being treated as duplicates.

**Evidence:** 18 missing = likely multiple checks or payments with same amount

**Fix:** Add description prefix to duplicate check

### 2. **Small Transaction Filter** (10% probability)
Transactions under $1.00 being filtered out.

**Evidence:** Unlikely to have 18 sub-$1 transactions

**Fix:** Remove < 1.0 filter

### 3. **AI Not Extracting** (5% probability)
Claude Sonnet missing transactions in first pass.

**Evidence:** Credits are perfect, suggests AI is working

**Fix:** Improve prompts or use ensemble

### 4. **PDF Extraction Issue** (5% probability)
Transactions not in extracted text.

**Evidence:** Layout preservation now enabled

**Fix:** Check debug_extracted_text.txt

---

## Immediate Action Plan

**Do this now:**

1. Check extracted text file
   ```bash
   cat storage/app/uploads/debug_extracted_text.txt | grep -i "check\|withdrawal" | wc -l
   ```

2. Count transactions in logs
   ```bash
   grep "Parsed.*transactions" storage/logs/laravel.log | tail -3
   ```

3. Apply Fix #2 (duplicate detection improvement)

4. Reprocess and compare

---

## Long-term Solution

**Comprehensive transaction validation:**

Add to `parse_transactions_ai.py` after line 1335:

```python
# Detailed reconciliation report
print("=" * 60, file=sys.stderr)
print("TRANSACTION RECONCILIATION REPORT", file=sys.stderr)
print("=" * 60, file=sys.stderr)
print(f"Expected from statement: {expected_debits} debits, {expected_credits} credits", file=sys.stderr)
print(f"Extracted by AI: {len(cleaned_transactions)} total", file=sys.stderr)
print(f"  - Credits: {claude_credits:.2f} ({len([t for t in cleaned_transactions if t['type']=='credit'])} count)", file=sys.stderr)
print(f"  - Debits: {claude_debits:.2f} ({len([t for t in cleaned_transactions if t['type']=='debit'])} count)", file=sys.stderr)
print(f"Missing: {expected_debits - claude_debits:.2f} debits", file=sys.stderr)
print("=" * 60, file=sys.stderr)
```

This will show exactly what's missing in every run.

---

## Next Steps

1. ✅ Check `storage/app/uploads/debug_extracted_text.txt`
2. ✅ Apply Fix #2 (improve duplicate detection)
3. ✅ Apply Fix #3 (add detailed logging)
4. ✅ Reprocess statement
5. ✅ Review logs for improvement
6. If still missing transactions → Apply Fix #1 (remove small amount filter)
7. If still missing → Investigate specific transaction patterns

---

**Need the actual fixes implemented?** Let me know and I'll apply them to the code immediately.

