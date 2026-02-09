# Python Script Simplification - February 7, 2026

## Problem
The extraction prompt had become too long and complex with conflicting instructions, making it hard for the AI to correctly identify transaction types.

## Solution
Simplified the classification rules to be clear, concise, and unambiguous.

---

## Changes Made

### 1. Simplified Classification Rules
**Before:** Long, complex instructions with multiple examples and steps
**After:** 3 simple rules:
```
RULE #1: Column placement determines type. NOTHING ELSE.
RULE #2: If separate sections instead of columns, use section headers
RULE #3: DO NOT use description keywords
```

### 2. Removed Description-Based Rules
**Removed:**
- "Merch Dep" = credit
- "Merch Fee" = debit
- All description keyword lists

**Why:** These conflict with column-based classification

### 3. Simplified Critical Rules
**Before:** 7 detailed rules with examples
**After:** 6 concise rules
```
1. Column placement determines type
2. If no columns, use section headers
3. NEVER use description keywords
4. NEVER skip large transactions
5. NEVER skip PURCHASE/CHECKCARD transactions
6. "Items returned unpaid" = returned
```

### 4. Streamlined Classification Method
**Before:** Multi-step process with detailed examples and fallbacks
**After:** Simple 3-step process
```
Step 1: Identify table structure
Step 2: Classify based on position
Step 3: Ignore description
```

---

## Key Principles

### ✅ DO:
- Read column headers to identify Debits vs Credits columns
- Classify based on which column contains the amount
- Use section headers if no clear columns
- Ignore description text completely

### ❌ DON'T:
- Use description keywords for classification
- Make assumptions based on merchant names
- Apply special rules for specific companies
- Mix multiple classification methods

---

## Expected Result

With these simplified instructions, the AI should:
1. Look at the table structure
2. Identify which column is Debits and which is Credits
3. Classify each transaction based solely on column placement
4. Produce accurate results matching the PDF totals

---

## Testing

Reprocess session 120 to verify it now matches the PDF total of $125,857.09 in credits.

```bash
php artisan bankstatement:reprocess --session=471aa9de-90ab-4124-b374-8560e2052491
```

---

## Files Modified
- `/var/www/html/crmfinity_laravel/storage/app/scripts/bank_statement_extractor.py`

**Lines modified:**
- 729-751: Simplified classification rules
- 753-758: Simplified critical rules
- 782-799: Simplified classification method
- 723-727: Removed description-based section rules

---

## Status
✅ Script simplified and cleaned up
⏳ Ready for testing
