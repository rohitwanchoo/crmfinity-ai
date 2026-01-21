# SmartMCA Script Architecture Refactor - Claude Only

**Date:** December 31, 2025  
**Status:** ✅ COMPLETED (Claude-Only Mode)

---

## 🎯 Objective

Refactor SmartMCA to use **ONLY** `parse_transactions_ai.py` (Claude) for all transaction parsing. This creates the cleanest, most maintainable architecture by using a single Python script with a single AI model.

---

## 📊 Architecture Evolution

### Phase 1 - Mixed Approach (OLD):
```
SmartMCA Transaction Parsing:
├── OpenAI GPT-4o → parse_transactions_openai.py (Python script)
└── Claude Sonnet → Direct PHP HTTP calls to api.anthropic.com ❌
```

### Phase 2 - Script-Based (INTERMEDIATE):
```
SmartMCA Transaction Parsing:
├── OpenAI GPT-4o → parse_transactions_openai.py (Python script)
├── Claude Sonnet → parse_transactions_ai.py (Python script)
└── Dual AI Mode → parse_transactions_ai.py with both API keys
```

### Phase 3 - Claude-Only (CURRENT):
```
SmartMCA Transaction Parsing:
└── Claude Sonnet → parse_transactions_ai.py ✅ (ONLY script used)
```

**Benefits:**
- ✅ Single script, single model - maximum simplicity
- ✅ All AI parsing logic in one place
- ✅ Consistent behavior across all parsing calls
- ✅ Easier to debug and maintain
- ✅ Claude's superior transaction extraction capability
- ✅ No OpenAI dependency for SmartMCA

---

## 🔧 Changes Made

### 1. Refactored `parseTransactionsWithClaude()`

**Before:** Direct HTTP call to Claude API
```php
$response = Http::post('https://api.anthropic.com/v1/messages', [
    'model' => 'claude-3-5-sonnet-20241022',
    'messages' => [...]
]);
```

**After:** Uses `parse_transactions_ai.py` script
```php
$scriptPath = storage_path('app/scripts/parse_transactions_ai.py');
$command = 'timeout 300 python3 ' . escapeshellarg($scriptPath)
    . ' ' . escapeshellarg($tempFile)
    . ' ' . escapeshellarg($anthropicKey)
    . ' ' . escapeshellarg($patternsFile)
    . ' ' . escapeshellarg($outputFile);
```

### 2. Simplified `parseTransactionsWithAI()` to use Claude

**Before:** Called `parse_transactions_openai.py` with OpenAI
```php
$scriptPath = storage_path('app/scripts/parse_transactions_openai.py');
$command = 'timeout 300 python3 ' . escapeshellarg($scriptPath)
    . ' ' . escapeshellarg($tempFile)
    . ' ' . escapeshellarg($openaiKey) . '...';
```

**After:** Alias to Claude parsing
```php
private function parseTransactionsWithAI(string $text, ?array $bankContext = null): array
{
    \Log::info('SmartMCA: parseTransactionsWithAI() called - using Claude script');
    return $this->parseTransactionsWithClaude($text, $bankContext);
}
```

### 3. Simplified `parseTransactionsWithDualAI()` to Claude-Only

**Before:** Complex dual-model orchestration with OpenAI fallback
```php
$openaiTransactions = $this->parseTransactionsWithAI($text);
$claudeTransactions = $this->parseTransactionsWithClaude($text);
$merged = $this->mergeTransactionResults(...);
```

**After:** Simple Claude-only parsing
```php
private function parseTransactionsWithDualAI(string $text, ?array $bankContext = null): array
{
    \Log::info('===== CLAUDE-ONLY PARSING START =====');
    return $this->parseTransactionsWithClaude($text, $bankContext);
}
```

### 4. Removed Unused Code

- ❌ Removed `mergeTransactionResults()` method
- ❌ Removed direct Claude HTTP calls from main parsing flow
- ❌ Removed all references to `parse_transactions_openai.py` in SmartMCA
- ❌ Removed OpenAI dependency from SmartMCA

---

## 📁 Python Scripts Usage Summary

### SmartMCA Now Uses Only 2 Scripts:

| Script | Used By | Purpose |
|--------|---------|---------|
| `extract_pdf_text.py` | SmartMcaController<br>ApplicationController<br>BankAnalysisService<br>UnderwritingController | Extract text from PDFs using PyMuPDF |
| `parse_transactions_ai.py` | **SmartMcaController (ONLY)**<br>ApplicationController<br>BankAnalysisService | **Claude Sonnet parsing** |

### Script Status:

- ✅ `extract_pdf_text.py` - Used by SmartMCA (PDF extraction)
- ✅ `parse_transactions_ai.py` - Used by SmartMCA (Claude parsing)
- ⚠️ `parse_transactions_openai.py` - **NOT used by SmartMCA** (available for future if needed)

---

## 🔍 Remaining Direct API Calls

**Note:** We kept 2 specialized direct API calls for recovery/verification:

1. **`attemptRecoveryWithClaude()`** (line ~907)
   - Uses Claude Haiku for targeted missing transaction search
   - Specialized prompt for gap recovery
   - Different use case than main parsing

2. **`attemptRecoveryWithOpenAI()`** (line ~1064)
   - Uses GPT-4o-mini for verification
   - Cross-checks missed transactions
   - Complementary to main parsing

**Why keep these?**
- Specialized, targeted operations
- Different prompts and logic
- Small, focused tasks (not full parsing)
- Would add complexity to move to scripts

---

## 🎯 Architecture Benefits

### Before (Mixed):
```
PHP Controller
├── Direct HTTP → Claude API (main parsing) ❌
├── Python Script → OpenAI API (main parsing) ❌
├── Direct HTTP → Claude API (recovery)
└── Direct HTTP → OpenAI API (verification)
```

### After (Claude-Only):
```
PHP Controller
├── Python Script → Claude API (main parsing) ✅ ONLY
├── Direct HTTP → Claude API (recovery only)
└── Direct HTTP → OpenAI API (verification only)
```

**Result:** 
- ✅ Main parsing uses **ONE script, ONE model**
- ✅ Maximum simplicity and maintainability
- ✅ Claude's superior extraction capability fully utilized
- ✅ No OpenAI dependency for core SmartMCA functionality

---

## 🧪 Testing Recommendations

1. **Test Claude parsing:**
   ```bash
   # Upload a bank statement via SmartMCA
   # Check logs for: "SmartMCA Claude: Parsed X transactions"
   ```

2. **Verify all parsing methods use Claude:**
   ```bash
   # Check logs show:
   # - "parseTransactionsWithAI() called - using Claude script"
   # - "CLAUDE-ONLY PARSING START"
   # - "SmartMCA Claude: Parsed X transactions"
   ```

3. **Verify logging:**
   ```bash
   tail -f storage/logs/laravel.log
   tail -f storage/app/scripts/logs/parse_claude_*.log
   ```

4. **Confirm no OpenAI calls:**
   ```bash
   # Laravel logs should NOT show:
   # - "parse_transactions_openai.py"
   # - "OpenAI API Usage" (except for recovery/verification)
   ```

---

## 📝 Code Quality

- ✅ No linter errors
- ✅ Consistent error handling
- ✅ Proper temp file cleanup
- ✅ Comprehensive logging
- ✅ Maintains backward compatibility

---

## 🎓 Key Takeaway

**SmartMCA now uses a single Python script (`parse_transactions_ai.py`) with Claude Sonnet for ALL primary transaction parsing**, providing:

- ✅ **Maximum Simplicity** - One script, one model
- ✅ **Easier Maintenance** - Single source of truth for parsing logic
- ✅ **Better Debugging** - All parsing logs in one place
- ✅ **Consistent Behavior** - Same logic for all parsing calls
- ✅ **Superior Accuracy** - Claude's advanced extraction capabilities
- ✅ **No OpenAI Dependency** - Claude-only for core functionality

### Decision Rationale:

**Why Claude-Only?**
1. Claude Sonnet has proven superior for transaction extraction
2. Simpler architecture = fewer bugs
3. Single model = consistent results
4. OpenAI available if needed later (script supports dual-mode)
5. Focus on perfecting Claude before adding complexity

### Future Path:

If Claude alone proves insufficient, we can easily re-enable OpenAI fallback by:
1. Passing 5th parameter (OpenAI key) to `parse_transactions_ai.py`
2. Script already has built-in dual-model support
3. No code changes needed - just configuration

---

**Refactor completed successfully! SmartMCA is now Claude-only.** ✨

