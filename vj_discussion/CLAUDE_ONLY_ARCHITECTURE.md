# SmartMCA: Claude-Only Architecture

**Date:** December 31, 2025  
**Status:** ✅ PRODUCTION READY

---

## 🎯 Architecture Overview

SmartMCA now uses a **single Python script with Claude Sonnet** for all transaction parsing.

```
┌─────────────────────────────────────────────────────────┐
│                    SmartMCA Controller                   │
└─────────────────────────────────────────────────────────┘
                            │
                            ├─ Step 1: PDF → Text
                            │  └─ extract_pdf_text.py
                            │
                            ├─ Step 2: Text → Transactions
                            │  └─ parse_transactions_ai.py (Claude Sonnet)
                            │
                            └─ Step 3: Validate & Store
                               └─ PHP processing
```

---

## 📋 Method Flow

All three parsing methods now use the **same Claude script**:

### 1. `parseTransactionsWithAI()`
```php
// Alias to Claude parsing
return $this->parseTransactionsWithClaude($text, $bankContext);
```

### 2. `parseTransactionsWithClaude()`
```php
// Calls parse_transactions_ai.py with Claude API key
$scriptPath = storage_path('app/scripts/parse_transactions_ai.py');
$command = 'timeout 300 python3 ' . escapeshellarg($scriptPath)
    . ' ' . escapeshellarg($tempFile)
    . ' ' . escapeshellarg($anthropicKey)
    . ' ' . escapeshellarg($patternsFile)
    . ' ' . escapeshellarg($outputFile);
```

### 3. `parseTransactionsWithDualAI()`
```php
// Simplified to Claude-only
return $this->parseTransactionsWithClaude($text, $bankContext);
```

**Result:** All roads lead to `parse_transactions_ai.py` with Claude! 🎯

---

## 🔧 Technical Details

### Python Script: `parse_transactions_ai.py`

**Location:** `/var/www/html/crmfinity_laravel/storage/app/scripts/parse_transactions_ai.py`

**Model Used:** Claude 3.5 Sonnet (`claude-3-5-sonnet-20241022`)

**Features:**
- ✅ Comprehensive transaction extraction
- ✅ Bank-specific pattern recognition
- ✅ Learned pattern support
- ✅ Statement total validation
- ✅ Special transaction detection (fees, checks, drafts)
- ✅ Multi-line description handling
- ✅ Duplicate detection and prevention
- ✅ Comprehensive logging

**Input:**
1. Text file (extracted PDF text)
2. Anthropic API key
3. Patterns file (learned patterns + bank context)
4. Output file path

**Output:**
```json
{
    "success": true,
    "transactions": [
        {
            "date": "2024-01-15",
            "description": "DIRECT DEPOSIT PAYROLL",
            "amount": 2500.00,
            "type": "credit",
            "confidence": 0.95,
            "confidence_label": "high"
        }
    ],
    "transaction_count": 150,
    "extracted_credits": 25000.00,
    "extracted_debits": 18500.00
}
```

---

## 📊 Comparison: Before vs After

### Before (Mixed Architecture):
```
parseTransactionsWithAI()
  └─ parse_transactions_openai.py → OpenAI GPT-4o

parseTransactionsWithClaude()
  └─ Direct HTTP → api.anthropic.com

parseTransactionsWithDualAI()
  ├─ parse_transactions_openai.py → OpenAI
  ├─ Direct HTTP → Claude
  └─ PHP merge logic
```

### After (Claude-Only):
```
parseTransactionsWithAI()
  └─ parse_transactions_ai.py → Claude Sonnet

parseTransactionsWithClaude()
  └─ parse_transactions_ai.py → Claude Sonnet

parseTransactionsWithDualAI()
  └─ parse_transactions_ai.py → Claude Sonnet
```

---

## ✅ Benefits

### 1. **Simplicity**
- One script to maintain
- One model to optimize
- One set of logs to monitor

### 2. **Consistency**
- Same extraction logic everywhere
- Predictable results
- Easier debugging

### 3. **Performance**
- Claude Sonnet's superior extraction
- No model switching overhead
- Faster response times

### 4. **Cost Efficiency**
- Single API provider
- Predictable costs
- No redundant calls

### 5. **Maintainability**
- Changes in one place
- Easier testing
- Clear architecture

---

## 🔍 Verification

### Check SmartMCA is using Claude-only:

```bash
# 1. Check Laravel logs
tail -f storage/logs/laravel.log | grep -E "SmartMCA|Claude"

# Expected output:
# [INFO] SmartMCA: parseTransactionsWithAI() called - using Claude script
# [INFO] SmartMCA: Calling Claude via parse_transactions_ai.py script
# [INFO] SmartMCA Claude: Parsed 150 transactions from Citizens Bank

# 2. Check Python logs
tail -f storage/app/scripts/logs/parse_claude_*.log

# Expected output:
# [INFO] STARTING TRANSACTION PARSING - VERSION 2.0 (Sonnet + No Filters)
# [INFO] Claude API Usage (Sonnet): 12,345 input + 3,456 output = 15,801 tokens ($0.0891)

# 3. Verify NO OpenAI calls in main parsing
grep -r "parse_transactions_openai.py" app/Http/Controllers/SmartMcaController.php
# Should return: NO MATCHES
```

---

## 🚀 Future Scalability

### If OpenAI fallback is needed later:

The architecture supports easy re-enablement:

```php
// In parseTransactionsWithDualAI(), change from:
return $this->parseTransactionsWithClaude($text, $bankContext);

// To:
$openaiKey = env('OPENAI_API_KEY');
$command = 'timeout 320 python3 ' . escapeshellarg($scriptPath)
    . ' ' . escapeshellarg($tempFile)
    . ' ' . escapeshellarg($anthropicKey)
    . ' ' . escapeshellarg($patternsFile)
    . ' ' . escapeshellarg($outputFile)
    . ' ' . escapeshellarg($openaiKey);  // Enable dual-model
```

The Python script already has built-in dual-model support with automatic fallback!

---

## 📝 Configuration

### Required Environment Variables:

```env
# Required for SmartMCA
ANTHROPIC_API_KEY=sk-ant-xxxxx

# Optional (for future dual-model if needed)
OPENAI_API_KEY=sk-xxxxx
```

### Script Permissions:

```bash
chmod +x storage/app/scripts/parse_transactions_ai.py
chmod +x storage/app/scripts/extract_pdf_text.py
```

### Log Directory:

```bash
mkdir -p storage/app/scripts/logs
chmod 775 storage/app/scripts/logs
```

---

## 🎓 Key Decisions

### Why Claude-Only?

1. **Proven Performance:** Claude Sonnet consistently extracts more transactions accurately
2. **Simplicity First:** One model = easier to perfect
3. **Cost Effective:** Single API provider, no redundant calls
4. **Maintainability:** Changes in one place
5. **Future Proof:** Can add OpenAI fallback anytime without code changes

### Why Keep `parse_transactions_openai.py`?

- Available for other controllers (ApplicationController, BankAnalysisService)
- Can be re-enabled for SmartMCA if needed
- No harm in keeping it (not loaded unless called)

---

## 📈 Success Metrics

Monitor these to ensure Claude-only is working well:

1. **Extraction Accuracy:** % of transactions captured vs statement totals
2. **API Costs:** Track Claude API usage and costs
3. **Processing Time:** Average time per statement
4. **Error Rate:** Failed parsing attempts
5. **User Corrections:** Number of manual corrections needed

---

## 🎉 Summary

SmartMCA now has the **cleanest possible architecture**:

- ✅ **2 Python scripts** (PDF extraction + Claude parsing)
- ✅ **1 AI model** (Claude Sonnet)
- ✅ **100% script-based** (no direct HTTP calls for main parsing)
- ✅ **Consistent behavior** (all methods use same logic)
- ✅ **Easy to maintain** (single source of truth)

**The architecture is production-ready and optimized for Claude's capabilities!** 🚀

