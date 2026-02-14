# API Async Migration Summary

## What Changed

The `/api/v1/bank-statement/analyze` endpoint has been migrated from **synchronous** to **asynchronous** processing with parallel queue workers.

## Changes Made

### 1. API Controller (`app/Http/Controllers/Api/BankStatementApiController.php`)

#### Before (Synchronous):
- Files processed one at a time in foreach loop
- `shell_exec()` blocked until each Python script completed
- Returned full results immediately (HTTP 200)
- Used OpenAI API (GPT-4)
- Response time: 2-3 minutes per file (12 minutes for 4 files)

#### After (Asynchronous):
- Files saved and jobs dispatched to queue immediately
- Returns session IDs instantly (HTTP 202 Accepted)
- Uses Claude API (Anthropic)
- 4 parallel workers process statements simultaneously
- Response time: Returns instantly, processing takes ~3 minutes for 4 files

### 2. Response Format Change

#### Before:
```json
{
  "success": true,
  "results": [
    {
      "filename": "statement.pdf",
      "session_id": "abc-123",
      "success": true,
      "summary": { ... },
      "transactions": [ ... ],
      "monthly_data": { ... },
      "mca_analysis": { ... }
    }
  ]
}
```

#### After:
```json
{
  "success": true,
  "message": "2 statements queued for parallel processing",
  "batch_id": "BATCH-ABC123DEF4567890",
  "sessions": [
    {
      "session_id": "abc-123-def-456",
      "filename": "statement1.pdf",
      "status": "queued"
    },
    {
      "session_id": "xyz-789-ghi-012",
      "filename": "statement2.pdf",
      "status": "queued"
    }
  ]
}
```

### 3. Model Changes

**Before:**
- `gpt-4o` (default)
- `gpt-4o-mini`

**After:**
- `claude-haiku-4-5` (default, fastest, cost-effective)
- `claude-sonnet-4-5` (balanced)
- `claude-opus-4-6` (most accurate)

### 4. OpenAPI Documentation Updated

- Changed summary: "Analyze bank statement PDF(s) **asynchronously**"
- Updated description to explain queue-based processing
- Changed response status: 202 (Accepted) instead of 200 (OK)
- Updated model enum to Claude models
- Updated response schema to show session IDs

### 5. API Documentation Updated

**File:** `API_DOCUMENTATION.md`

Updates include:
- Quick Start section: Shows async upload + polling workflow
- Analyze endpoint: Explains async processing model
- Code examples (Python, PHP, JavaScript, cURL): All updated to show polling pattern
- HTTP status codes: Added 202 (Accepted)
- Changelog: Documented breaking changes in v1.1.0

## How Clients Should Use the New API

### Step 1: Upload Files (Returns Immediately)
```bash
curl -X POST https://ai.crmfinity.com/api/v1/bank-statement/analyze \
  -H "Authorization: Bearer $TOKEN" \
  -F "statements[]=@statement.pdf" \
  -F "model=claude-haiku-4-5"
```

**Response (HTTP 202):**
```json
{
  "success": true,
  "message": "1 statement queued for processing",
  "batch_id": "BATCH-XYZ789",
  "sessions": [
    {
      "session_id": "abc-123",
      "filename": "statement.pdf",
      "status": "queued"
    }
  ]
}
```

### Step 2: Poll for Completion
```bash
curl -X GET https://ai.crmfinity.com/api/v1/bank-statement/sessions/abc-123 \
  -H "Authorization: Bearer $TOKEN"
```

**While Processing:**
```json
{
  "success": true,
  "data": {
    "session": null  // Or minimal session data without transactions
  }
}
```

**When Complete:**
```json
{
  "success": true,
  "data": {
    "session": {
      "session_id": "abc-123",
      "filename": "statement.pdf",
      "total_transactions": 150,
      "total_credits": 50000.00,
      "total_debits": 45000.00,
      ...
    }
  }
}
```

### Step 3: Fetch Full Results
Use existing session endpoints:
- `GET /api/v1/bank-statement/sessions/{sessionId}/transactions`
- `GET /api/v1/bank-statement/sessions/{sessionId}/monthly`
- `GET /api/v1/bank-statement/sessions/{sessionId}/mca-analysis`

## Benefits

1. **Instant Response**: API returns immediately instead of waiting 2-3 minutes
2. **Parallel Processing**: Multiple files process simultaneously (4 workers)
3. **Performance**: 75% faster (3 minutes for 4 files vs 12 minutes sequential)
4. **Scalability**: Can handle multiple concurrent uploads without blocking
5. **Better UX**: Clients can show progress indicators and handle errors gracefully

## Breaking Changes

⚠️ **Important**: This is a breaking change for existing API clients.

Clients must be updated to:
1. Handle HTTP 202 response instead of 200
2. Extract session IDs from new response format
3. Implement polling logic to check for completion
4. Update model parameter values (claude models instead of gpt models)

## Testing

The changes have been tested with:
- ✅ 4 parallel queue workers running via Supervisor
- ✅ Web UI uploading 4 statements simultaneously
- ✅ All jobs dispatched in parallel and processed within 3 minutes
- ✅ Batch ID grouping working correctly
- ✅ API routes verified with `php artisan route:list`

## Files Modified

1. `app/Http/Controllers/Api/BankStatementApiController.php`
   - Added ProcessBankStatement import
   - Replaced synchronous processing with job dispatch
   - Updated model validation (Claude models)
   - Changed response format and HTTP status
   - Updated OpenAPI documentation attributes

2. `API_DOCUMENTATION.md`
   - Updated Quick Start guide
   - Updated analyze endpoint documentation
   - Updated all code examples (Python, PHP, JavaScript, cURL)
   - Added HTTP 202 status code
   - Added v1.1.0 changelog with migration guide

## Queue Workers Status

All 4 workers are running:
```
crmfinity-queue:crmfinity-queue_00   RUNNING   uptime 0:52:35
crmfinity-queue:crmfinity-queue_01   RUNNING   uptime 0:52:35
crmfinity-queue:crmfinity-queue_02   RUNNING   uptime 0:52:35
crmfinity-queue:crmfinity-queue_03   RUNNING   uptime 0:52:35
```

## Next Steps

1. **Update API Clients**: Any existing integrations must be updated to use the new async pattern
2. **Monitor Queue**: Watch `storage/logs/queue-worker.log` for processing status
3. **Test Swagger UI**: Verify interactive documentation at `/api/documentation`
4. **Consider Rate Limits**: May need to adjust with async processing

---

**Completed:** February 11, 2026
**Migration Type:** Breaking Change (v1.0.0 → v1.1.0)
