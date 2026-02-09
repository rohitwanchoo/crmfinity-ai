# Column-Based Transaction Classification - February 7, 2026

## ✅ Classification Logic Updated

Changed transaction classification to prioritize **column placement** on the actual bank statement, not description keywords.

---

## 🎯 New Classification Priority

### **1. Column Placement (HIGHEST PRIORITY)**
- Transaction in "Debits" or "Withdrawals" column = **DEBIT**
- Transaction in "Credits" or "Deposits" column = **CREDIT**
- **Column placement is the authoritative source**
- Description keywords are completely ignored

### **2. Section Headers (if no clear columns)**
- Under "DEPOSITS & CREDITS" section = **CREDIT**
- Under "WITHDRAWALS & DEBITS" section = **DEBIT**

### **3. Balance Changes (fallback only)**
- Balance increases = **CREDIT**
- Balance decreases = **DEBIT**

### **4. NEVER Description Keywords**
- Words like "Debit", "Credit", "Deposit", "Withdrawal" in description are **NOT** used for classification
- Description is for identification only, not classification

---

## 📊 Examples

### Example 1: ACH Debit in Credits Column
- **Description:** "ACH Debit - Britecap Preauthpmt"
- **Appears in:** Credits column
- **Classification:** **CREDIT** ✅
- **Reason:** Column placement overrides description

### Example 2: Deposit in Debits Column
- **Description:** "Deposit Reversal"
- **Appears in:** Debits column
- **Classification:** **DEBIT** ✅
- **Reason:** Column placement overrides description

### Example 3: Payment with No Column
- **Description:** "Payment to Vendor"
- **Section:** Under "WITHDRAWALS & DEBITS"
- **Classification:** **DEBIT** ✅
- **Reason:** Section header used when no column

---

## 🔍 How It Works

The AI will now:

1. **First**, scan the statement for column headers:
   - "Debits", "Credits", "Deposits", "Withdrawals"
   - "Additions", "Subtractions", "Amount In", "Amount Out"

2. **Then**, identify which column each transaction amount appears in:
   - Amount in Debits column → DEBIT
   - Amount in Credits column → CREDIT

3. **Fallback** to section headers if no clear columns:
   - "DEPOSITS & CREDITS" section → CREDIT
   - "WITHDRAWALS & DEBITS" section → DEBIT

4. **Last resort** - use balance changes if columns/sections unclear

5. **Never** use description keywords for classification

---

## 📝 Files Modified

### `/storage/app/scripts/bank_statement_extractor.py`

**Lines 712-751:** Updated classification instructions
```
CREDIT vs DEBIT vs RETURNED CLASSIFICATION:

**MOST IMPORTANT - CLASSIFICATION PRIORITY ORDER:**

1. **COLUMN PLACEMENT (HIGHEST PRIORITY)**
   - If transaction appears in "Debit" or "Debits" or "Withdrawals" COLUMN = DEBIT
   - If transaction appears in "Credit" or "Credits" or "Deposits" COLUMN = CREDIT
   - Look for column headers in the table: typically "Debits", "Credits", "Deposits", "Withdrawals"
   - The column the transaction appears in is the AUTHORITATIVE source
   - **IGNORE description keywords** - a transaction with "ACH Debit" in description but in Credits column = CREDIT
```

**Lines 766-780:** Updated critical rules
```
CRITICAL RULES (IN ORDER OF PRIORITY):
1. **COLUMN PLACEMENT IS THE ULTIMATE TRUTH**:
   - If transaction is in Debits/Withdrawals COLUMN = DEBIT (regardless of description)
   - If transaction is in Credits/Deposits COLUMN = CREDIT (regardless of description)
   - Example: "ACH Debit - Britecap" in Credits column = CREDIT
   - Example: "Deposit Reversal" in Debits column = DEBIT
   - Column headers to look for: "Debits", "Credits", "Deposits", "Withdrawals", "Additions", "Subtractions"
```

**Lines 782-810:** Updated classification method
```
CLASSIFICATION METHOD - READ THE STATEMENT STRUCTURE:

**STEP 1: Identify the table structure**
- Look for column headers: "Date", "Description", "Debits", "Credits", "Balance"

**STEP 2: Classify based on column placement**
- Amount in "Debits" or "Withdrawals" column = DEBIT
- Amount in "Credits" or "Deposits" column = CREDIT

**ABSOLUTE PRIORITY ORDER:**
Column placement > Section header > Balance change > NEVER description keywords
```

---

## 💡 Impact

### What Changed:
- ❌ **OLD:** Classification based on description keywords + balance changes
- ✅ **NEW:** Classification based on column placement + section headers

### Why This Is Better:
- ✅ More accurate - uses bank's own classification
- ✅ Simpler - no complex logic needed
- ✅ Reliable - banks already categorized transactions correctly
- ✅ Consistent - same transaction type every time
- ✅ No confusion - description doesn't matter

### What This Means:
- "ACH Debit" transactions can be credits if they appear in Credits column
- "Deposit" transactions can be debits if they appear in Debits column
- The bank statement structure is now the single source of truth
- Description is only used for identification, never classification

---

## 🔄 Next Steps

To apply this new logic to existing statements:

1. **New Uploads:** Will automatically use column-based classification
2. **Existing Statements:** Need to be reprocessed to use new logic

### Reprocess Existing Statements:
```bash
# Reprocess specific session
php artisan bankstatement:reprocess --session=SESSION_ID

# Reprocess recent statements
php artisan bankstatement:reprocess --recent=5

# Reprocess all statements
php artisan bankstatement:reprocess --all
```

---

## ✅ Status

**Date:** February 7, 2026
**Status:** Complete ✅
**Files Modified:**
- `/storage/app/scripts/bank_statement_extractor.py`
- `/root/.claude/projects/-var-www-html-crmfinity-laravel/memory/MEMORY.md`

**Ready for:** New uploads and reprocessing of existing statements

---

## 📌 Key Takeaway

**Classification is now based on WHERE the transaction appears on the statement (column/section), NOT what the description says.**

This matches how humans naturally read bank statements and ensures accuracy by trusting the bank's own categorization.
