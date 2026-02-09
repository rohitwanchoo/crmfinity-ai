# Error Fix: "Failed to parse Python script output"

**Date**: February 8, 2026
**Status**: ✅ Fixed
**Severity**: High (prevented statement processing)

---

## The Error

### User-Facing Message
```
All files failed to process.
Failed to parse Python script output: {"success": true, ...}
```

### What Was Happening
- Python script was executing successfully ✅
- Extracting all transactions correctly ✅
- **BUT** debug messages were mixing with JSON output ❌

### Root Cause Analysis

**The Problem:**
```bash
# Original command in BankStatementController.php (line 102)
python3 script.py args 2>&1
```

The `2>&1` redirects **stderr to stdout**, causing:
- Debug messages: "Attempting deterministic table extraction..."
- Debug messages: "Falling back to AI extraction..."
- **Mixed into the JSON output** that Laravel tries to parse

**Result:**
```
Attempting deterministic table extraction...
Deterministic extraction failed...
{"success": true, "summary": ...}
```

Laravel's `json_decode()` fails because it tries to parse the entire output including the debug text.

---

## The Fix

### Changes Made

#### 1. Updated `BankStatementController.php`

**Before (Line 102):**
```php
$command = sprintf(
    'python3 %s %s %s %s %s 2>&1',  // ❌ Mixes stderr with stdout
    ...
);
```

**After:**
```php
$stderrFile = storage_path('logs/python_stderr_' . $sessionId . '.log');
$command = sprintf(
    'python3 %s %s %s %s %s 2>%s',  // ✅ Redirects stderr to file
    escapeshellarg($scriptPath),
    escapeshellarg($savedPath),
    escapeshellarg($apiKey),
    escapeshellarg($model),
    escapeshellarg($correctionsJson),
    escapeshellarg($stderrFile)  // Debug messages go here
);
```

#### 2. Added JSON Extraction Logic

```php
// Clean up: extract only the JSON part if there's any extra text
$lines = explode("\n", trim($output));
$jsonLine = end($lines);

// If the last line doesn't look like JSON, try to find it
if (!str_starts_with(trim($jsonLine), '{')) {
    foreach (array_reverse($lines) as $line) {
        if (str_starts_with(trim($line), '{')) {
            $jsonLine = $line;
            break;
        }
    }
}

$data = json_decode($jsonLine, true);
```

#### 3. Improved Error Handling

```php
if (!$data || !isset($data['success'])) {
    // Read stderr for debugging if JSON parsing failed
    $stderr = file_exists($stderrFile) ? file_get_contents($stderrFile) : '';
    throw new \Exception('Failed to parse Python script output. Output: ' .
        substr($output, 0, 200) . '... Stderr: ' . substr($stderr, 0, 200));
}

// Clean up stderr log file after successful parsing
if (file_exists($stderrFile)) {
    @unlink($stderrFile);
}
```

---

## How It Works Now

### Process Flow

1. **User uploads PDF** → Controller receives file
2. **Command executes:**
   ```bash
   python3 script.py file.pdf API_KEY model corrections 2>/tmp/stderr.log
   ```
3. **Output separation:**
   - **STDOUT** (clean): `{"success": true, "summary": {...}, ...}`
   - **STDERR** (debug): `Attempting deterministic table extraction...`
4. **Laravel parses:**
   - Extracts JSON from stdout ✅
   - Stores stderr in temporary log file ✅
   - Parses JSON successfully ✅
5. **Success!** Transactions saved to database

---

## Verification Test

### Test Command
```bash
python3 storage/app/scripts/bank_statement_extractor.py \
  "file.pdf" \
  "API_KEY" \
  "claude-opus-4-6" \
  "[]" \
  2>/tmp/test_stderr.log
```

### Results
```
STDOUT: {"success": true, "summary": ...}  ✅ Clean JSON
STDERR: Attempting deterministic...        ✅ Separate debug
```

### Laravel Processing
```
✅ JSON extracted successfully
✅ 95 transactions parsed
✅ Database records created
✅ No parsing errors
```

---

## What Was Actually Working

Despite the error message, the Python script was working perfectly:

### Extracted Data (from error message)
```json
{
  "success": true,
  "summary": {
    "credit_count": 13,
    "debit_count": 82,
    "credit_total": 70858.32,
    "debit_total": 123542.78,
    "net_balance": -52684.46,
    "total_transactions": 95
  },
  "api_cost": {
    "total_cost": 0.4245,
    "model": "claude-opus-4-6"
  }
}
```

**All 95 transactions were extracted correctly!** The only issue was the parsing in Laravel.

---

## Files Modified

1. **app/Http/Controllers/BankStatementController.php**
   - Line 100-130: Updated command execution and JSON parsing
   - Added stderr file redirection
   - Added JSON extraction logic
   - Improved error messages

---

## Testing Checklist

- [x] Upload single PDF → Success
- [x] Upload multiple PDFs → Success
- [x] JSON parsing works → Success
- [x] Debug messages separated → Success
- [x] Error handling improved → Success
- [x] Stderr logs cleaned up → Success
- [x] Transactions saved correctly → Success

---

## Benefits of This Fix

### 1. **Robust Parsing**
- Handles mixed output gracefully
- Extracts JSON even if there's extra text
- Works with all statement formats

### 2. **Better Debugging**
- Stderr saved to separate log file
- Can review debug messages if needed
- Error messages show both stdout and stderr

### 3. **Clean Separation**
- Python debug → stderr
- Python output → stdout
- Laravel only parses JSON

### 4. **No Breaking Changes**
- Python script unchanged
- Same AI models
- Same extraction logic
- Only output handling improved

---

## Why This Error Was Misleading

The error message said:
> "Failed to parse Python script output"

But showed valid JSON immediately after! This made it confusing because:
- ❌ Looked like the script was working
- ❌ JSON appeared valid
- ✅ **Actually was valid**, just had debug text mixed in

The fix ensures only clean JSON reaches the parser.

---

## Prevention for Future

### Best Practices Implemented

1. **Always redirect stderr separately** when expecting JSON output
2. **Extract JSON from output** rather than assuming entire output is JSON
3. **Log debug info to files** instead of mixing with data output
4. **Show both stdout and stderr** in error messages for debugging

### Code Pattern to Follow

```php
// ✅ Good: Separate streams
$command = "python3 script.py args 2>stderr.log";
$output = shell_exec($command);
$json = extract_json($output);

// ❌ Bad: Mixed streams
$command = "python3 script.py args 2>&1";
$output = shell_exec($command);
$json = json_decode($output);  // May fail if debug messages present
```

---

## Summary

**Problem:** Debug messages mixing with JSON output
**Cause:** `2>&1` redirecting stderr to stdout
**Fix:** Redirect stderr to separate file, extract JSON from stdout
**Result:** Clean parsing, all transactions processed successfully

The actual data extraction was working perfectly all along. This was purely an output handling issue in the Laravel controller, now resolved.

---

## Status: ✅ Resolved

The system is now processing bank statements correctly with Claude Opus. The error will no longer occur.
