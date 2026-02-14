# CRMFinity Bank Statement Analyzer API Documentation

**Base URL:** `https://ai.crmfinity.com/api/v1`
**Interactive Docs:** `https://ai.crmfinity.com/api/documentation`

---

## Table of Contents
1. [Authentication](#authentication)
2. [Quick Start](#quick-start)
3. [API Endpoints](#api-endpoints)
4. [Code Examples](#code-examples)
5. [Response Formats](#response-formats)
6. [Error Handling](#error-handling)

---

## Authentication

The API uses Laravel Sanctum token-based authentication.

### Step 1: Login to Get Access Token

**Endpoint:** `POST /api/v1/auth/login`

**Request:**
```bash
curl -X POST https://ai.crmfinity.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "your-email@example.com",
    "password": "your-password"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz"
  }
}
```

**Save the token** - You'll use it in all subsequent requests.

---

## Quick Start

### Analyze a Bank Statement in 3 Steps

#### 1. Login
```bash
TOKEN=$(curl -s -X POST https://ai.crmfinity.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"your@email.com","password":"password"}' \
  | jq -r '.data.token')
```

#### 2. Upload & Analyze (Async)
```bash
RESPONSE=$(curl -X POST https://ai.crmfinity.com/api/v1/bank-statement/analyze \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "statements[]=@/path/to/statement.pdf" \
  -F "model=claude-opus-4-6")

SESSION_ID=$(echo $RESPONSE | jq -r '.sessions[0].session_id')
```

#### 3. Poll for Results
Files are processed asynchronously in parallel. Poll the session endpoint until processing completes:
```bash
curl -X GET https://ai.crmfinity.com/api/v1/bank-statement/sessions/$SESSION_ID \
  -H "Authorization: Bearer $TOKEN"
```

Results include:
- All transactions with classifications
- Monthly summaries
- MCA (Merchant Cash Advance) detection
- Negative days calculation
- True revenue calculation

---

## API Endpoints

### Authentication Endpoints

#### POST /api/v1/auth/login
Login and obtain access token.

**Request Body:**
```json
{
  "email": "string (required)",
  "password": "string (required)"
}
```

#### GET /api/v1/auth/user
Get current authenticated user information.

**Headers:**
```
Authorization: Bearer {token}
```

#### POST /api/v1/auth/logout
Logout and revoke current token.

#### POST /api/v1/auth/logout-all
Logout from all devices (revoke all tokens).

---

### Bank Statement Analysis Endpoints

#### POST /api/v1/bank-statement/analyze
Upload and analyze bank statement(s) asynchronously.

**Processing Model:** Asynchronous with parallel processing
- Files are queued immediately and processed in parallel by worker processes
- Returns session IDs instantly (HTTP 202 Accepted)
- Use session endpoints to poll for results once processing completes
- Processing typically takes 1-3 minutes depending on statement complexity

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
Accept: application/json
```

**Request Body (multipart/form-data):**
- `statements[]` - PDF file(s) to analyze (max 20MB each)
- `model` - AI model to use (optional)
  - `claude-haiku-4-5` (default, fastest, cost-effective)
  - `claude-sonnet-4-5` (balanced speed and accuracy)
  - `claude-opus-4-6` (most accurate, slower)

**Response (HTTP 202 Accepted):**
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

**Next Steps:**
1. Save the `session_id` values from the response
2. Poll `GET /api/v1/bank-statement/sessions/{sessionId}` to check processing status
3. Once complete, fetch detailed results using session endpoints below

---

#### GET /api/v1/bank-statement/sessions
Get list of all analysis sessions.

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20)
- `sort` - Sort field (created_at, filename)
- `order` - Sort order (asc, desc)

**Response:**
```json
{
  "success": true,
  "data": {
    "sessions": [
      {
        "session_id": "abc-123",
        "filename": "statement.pdf",
        "total_transactions": 150,
        "total_credits": 50000.00,
        "total_debits": 45000.00,
        "created_at": "2026-02-09T20:00:00Z"
      }
    ],
    "pagination": {
      "total": 50,
      "per_page": 20,
      "current_page": 1,
      "last_page": 3
    }
  }
}
```

---

#### GET /api/v1/bank-statement/sessions/{sessionId}
Get detailed information about a specific analysis session.

**Response:**
```json
{
  "success": true,
  "data": {
    "session": {
      "session_id": "abc-123",
      "filename": "statement.pdf",
      "bank_name": "Wells Fargo",
      "total_transactions": 150,
      "beginning_balance": 20000.00,
      "ending_balance": 25000.00,
      "analysis_type": "claude",
      "model_used": "claude-opus-4-6",
      "created_at": "2026-02-09T20:00:00Z"
    }
  }
}
```

---

#### GET /api/v1/bank-statement/sessions/{sessionId}/transactions
Get all transactions for a specific session.

**Query Parameters:**
- `type` - Filter by type (credit, debit)
- `category` - Filter by category
- `is_mca_payment` - Filter MCA payments (true, false)

**Response:**
```json
{
  "success": true,
  "data": {
    "transactions": [
      {
        "id": 1,
        "date": "2026-01-15",
        "description": "DEPOSIT - CHECK #1234",
        "amount": 5000.00,
        "type": "credit",
        "category": "revenue",
        "ending_balance": 25000.00,
        "is_mca_payment": false,
        "mca_lender": null
      }
    ]
  }
}
```

---

#### GET /api/v1/bank-statement/sessions/{sessionId}/summary
Get summary statistics for a session.

**Response:**
```json
{
  "success": true,
  "data": {
    "summary": {
      "total_transactions": 150,
      "credit_count": 75,
      "debit_count": 75,
      "credit_total": 50000.00,
      "debit_total": 45000.00,
      "net_balance": 5000.00,
      "average_transaction": 633.33,
      "largest_credit": 10000.00,
      "largest_debit": 5000.00
    }
  }
}
```

---

#### GET /api/v1/bank-statement/sessions/{sessionId}/monthly
Get monthly breakdown and true revenue calculation.

**Response:**
```json
{
  "success": true,
  "data": {
    "monthly_data": {
      "months": [
        {
          "month_name": "January 2026",
          "month_key": "2026-01",
          "deposits": 50000.00,
          "adjustments": 5000.00,
          "true_revenue": 45000.00,
          "debits": 45000.00,
          "deposit_count": 75,
          "debit_count": 75,
          "negative_days": 0,
          "nsf_count": 0,
          "average_daily": 1500.00,
          "days_in_month": 31
        }
      ],
      "totals": {
        "deposits": 50000.00,
        "true_revenue": 45000.00,
        "debits": 45000.00
      }
    }
  }
}
```

---

#### GET /api/v1/bank-statement/sessions/{sessionId}/mca-analysis
Get MCA (Merchant Cash Advance) analysis.

**Response:**
```json
{
  "success": true,
  "data": {
    "mca_analysis": {
      "total_mca_count": 2,
      "total_mca_payments": 120,
      "total_mca_amount": 15000.00,
      "lenders": [
        {
          "lender_id": "ondeck",
          "lender_name": "OnDeck Capital",
          "payment_count": 60,
          "total_amount": 8000.00,
          "avg_payment": 133.33,
          "frequency": "daily",
          "first_payment_date": "2026-01-01",
          "last_payment_date": "2026-01-31"
        }
      ]
    }
  }
}
```

---

#### GET /api/v1/bank-statement/sessions/{sessionId}/download
Download transactions as CSV file.

**Response:** CSV file download

---

#### DELETE /api/v1/bank-statement/sessions/{sessionId}
Delete an analysis session and all its transactions.

**Response:**
```json
{
  "success": true,
  "message": "Session deleted successfully"
}
```

---

### Transaction Correction Endpoints

#### POST /api/v1/bank-statement/transactions/{transactionId}/toggle-type
Toggle transaction type between credit and debit.

**Request Body:**
```json
{
  "current_type": "credit"
}
```

---

#### POST /api/v1/bank-statement/transactions/{transactionId}/toggle-revenue
Toggle transaction revenue classification.

**Request Body:**
```json
{
  "description": "Transfer from Savings",
  "current_classification": "true_revenue",
  "is_mca_funding": false
}
```

---

#### POST /api/v1/bank-statement/transactions/{transactionId}/toggle-mca
Mark/unmark transaction as MCA payment.

**Request Body:**
```json
{
  "description": "OnDeck Payment",
  "is_mca": true,
  "mca_lender_id": "ondeck",
  "mca_lender_name": "OnDeck Capital"
}
```

---

### Reference Data Endpoints

#### GET /api/v1/bank-statement/mca-lenders
Get list of known MCA lenders.

**Response:**
```json
{
  "success": true,
  "data": {
    "lenders": [
      {
        "id": "ondeck",
        "name": "OnDeck Capital"
      },
      {
        "id": "kabbage",
        "name": "Kabbage"
      }
    ]
  }
}
```

---

#### GET /api/v1/bank-statement/stats
Get overall system statistics.

**Response:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_sessions": 1250,
      "total_transactions": 185000,
      "total_credits": 125000000.00,
      "total_debits": 118000000.00
    }
  }
}
```

---

## Code Examples

### Python Example

```python
import requests
import os

# Base URL
BASE_URL = "https://ai.crmfinity.com/api/v1"

# Step 1: Login
def login(email, password):
    response = requests.post(
        f"{BASE_URL}/auth/login",
        json={"email": email, "password": password},
        headers={"Accept": "application/json"}
    )
    return response.json()['data']['token']

# Step 2: Upload for Analysis (async)
def upload_statement(token, pdf_path, model='claude-haiku-4-5'):
    with open(pdf_path, 'rb') as f:
        files = {'statements[]': f}
        data = {'model': model}
        headers = {
            'Authorization': f'Bearer {token}',
            'Accept': 'application/json'
        }

        response = requests.post(
            f"{BASE_URL}/bank-statement/analyze",
            files=files,
            data=data,
            headers=headers
        )
        return response.json()

# Step 3: Poll for completion
def wait_for_completion(token, session_id, timeout=300):
    import time
    start = time.time()
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json'
    }

    while time.time() - start < timeout:
        response = requests.get(
            f"{BASE_URL}/bank-statement/sessions/{session_id}",
            headers=headers
        )
        data = response.json()

        # Check if processing is complete (session exists with transactions)
        if data.get('success') and data.get('data', {}).get('session'):
            session = data['data']['session']
            if session.get('total_transactions', 0) > 0:
                return session

        time.sleep(5)  # Poll every 5 seconds

    raise TimeoutError(f"Processing did not complete within {timeout} seconds")

# Usage
if __name__ == "__main__":
    # Login
    token = login("your@email.com", "your-password")
    print(f"Token: {token[:20]}...")

    # Upload for analysis
    result = upload_statement(token, "statement.pdf")
    session_id = result['sessions'][0]['session_id']
    batch_id = result['batch_id']
    print(f"Uploaded! Session ID: {session_id}")
    print(f"Batch ID: {batch_id}")
    print(f"Status: {result['message']}")

    # Wait for processing to complete
    print("Waiting for processing...")
    session = wait_for_completion(token, session_id)
    print(f"Complete! Transactions: {session['total_transactions']}")
    print(f"Bank: {session.get('bank_name', 'Unknown')}")
```

---

### PHP Example

```php
<?php

$baseUrl = 'https://ai.crmfinity.com/api/v1';

// Step 1: Login
function login($email, $password) {
    global $baseUrl;

    $ch = curl_init("$baseUrl/auth/login");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['data']['token'];
}

// Step 2: Analyze Statement
function analyzeStatement($token, $pdfPath) {
    global $baseUrl;

    $ch = curl_init("$baseUrl/bank-statement/analyze");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'statements[]' => new CURLFile($pdfPath),
        'model' => 'claude-opus-4-6'
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Usage
$token = login('your@email.com', 'your-password');
$result = analyzeStatement($token, 'statement.pdf');

echo "Session ID: " . $result['data']['results'][0]['session_id'] . "\n";
echo "Transactions: " . $result['data']['results'][0]['summary']['total_transactions'] . "\n";
```

---

### JavaScript/Node.js Example

```javascript
const axios = require('axios');
const FormData = require('form-data');
const fs = require('fs');

const BASE_URL = 'https://ai.crmfinity.com/api/v1';

// Step 1: Login
async function login(email, password) {
  const response = await axios.post(`${BASE_URL}/auth/login`, {
    email,
    password
  }, {
    headers: { 'Accept': 'application/json' }
  });
  return response.data.data.token;
}

// Step 2: Analyze Statement
async function analyzeStatement(token, pdfPath) {
  const form = new FormData();
  form.append('statements[]', fs.createReadStream(pdfPath));
  form.append('model', 'claude-opus-4-6');

  const response = await axios.post(
    `${BASE_URL}/bank-statement/analyze`,
    form,
    {
      headers: {
        ...form.getHeaders(),
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    }
  );
  return response.data;
}

// Usage
(async () => {
  try {
    const token = await login('your@email.com', 'your-password');
    console.log('Token:', token.substring(0, 20) + '...');

    const result = await analyzeStatement(token, 'statement.pdf');
    const sessionId = result.data.results[0].session_id;
    console.log('Session ID:', sessionId);
    console.log('Transactions:', result.data.results[0].summary.total_transactions);
  } catch (error) {
    console.error('Error:', error.response?.data || error.message);
  }
})();
```

---

### cURL Example (Complete Workflow)

```bash
#!/bin/bash

BASE_URL="https://ai.crmfinity.com/api/v1"

# 1. Login
echo "Logging in..."
TOKEN=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"your@email.com","password":"your-password"}' \
  | jq -r '.data.token')

echo "Token obtained: ${TOKEN:0:20}..."

# 2. Upload for Analysis (async)
echo "Uploading statement for analysis..."
RESULT=$(curl -s -X POST "$BASE_URL/bank-statement/analyze" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F "statements[]=@statement.pdf" \
  -F "model=claude-haiku-4-5")

SESSION_ID=$(echo $RESULT | jq -r '.sessions[0].session_id')
BATCH_ID=$(echo $RESULT | jq -r '.batch_id')
echo "Session ID: $SESSION_ID"
echo "Batch ID: $BATCH_ID"

# 2b. Wait for processing to complete
echo "Waiting for processing to complete..."
while true; do
  STATUS=$(curl -s -X GET "$BASE_URL/bank-statement/sessions/$SESSION_ID" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json")

  TOTAL=$(echo $STATUS | jq -r '.data.session.total_transactions // 0')
  if [ "$TOTAL" -gt 0 ]; then
    echo "Processing complete! Found $TOTAL transactions"
    break
  fi

  echo "Still processing... (waiting 5 seconds)"
  sleep 5
done

# 3. Get transactions
echo "Fetching transactions..."
curl -s -X GET "$BASE_URL/bank-statement/sessions/$SESSION_ID/transactions" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  | jq '.data.transactions[:5]'

# 4. Get monthly data
echo "Fetching monthly data..."
curl -s -X GET "$BASE_URL/bank-statement/sessions/$SESSION_ID/monthly" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  | jq '.data.monthly_data'

# 5. Download CSV
echo "Downloading CSV..."
curl -X GET "$BASE_URL/bank-statement/sessions/$SESSION_ID/download" \
  -H "Authorization: Bearer $TOKEN" \
  -o "transactions_${SESSION_ID}.csv"

echo "Done!"
```

---

## Response Formats

### Success Response
```json
{
  "success": true,
  "data": {
    // Response data here
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 202 | Accepted (async processing started) |
| 400 | Bad Request |
| 401 | Unauthorized (invalid/missing token) |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests (rate limit) |
| 500 | Server Error |

### Common Errors

#### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```
**Solution:** Check your token, login again if expired.

#### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "statements": ["The statements field is required."]
  }
}
```
**Solution:** Check request parameters match requirements.

#### 500 Server Error
```json
{
  "message": "Server Error",
  "error": "Detailed error message"
}
```
**Solution:** Contact support with the session ID.

---

## Rate Limits

- **Authentication:** 5 requests per minute
- **Analysis:** 10 requests per minute
- **Data Retrieval:** 60 requests per minute

When rate limited, you'll receive a 429 status code.

---

## Interactive API Documentation

Visit the interactive Swagger UI at:
**https://ai.crmfinity.com/api/documentation**

Features:
- Try out API calls directly
- See request/response examples
- Download OpenAPI spec
- Test authentication flow

---

## Support

For API support:
- **Email:** support@crmfinity.com
- **Documentation:** https://ai.crmfinity.com/api/documentation
- **Status:** https://status.crmfinity.com

---

## Changelog

### v1.1.0 (2026-02-11)
**Breaking Changes:**
- `/bank-statement/analyze` endpoint now processes asynchronously
  - Returns HTTP 202 (Accepted) instead of 200
  - Response format changed: returns session IDs instead of full results
  - Clients must poll session endpoints to retrieve results
- Migrated from OpenAI to Claude (Anthropic) API
  - Model parameter changed: `claude-opus-4-6`, `claude-sonnet-4-5`, `claude-haiku-4-5`
  - Default model is now `claude-haiku-4-5`
- Added parallel processing support (4 workers)
  - Multiple statements uploaded together process in parallel
  - Significant performance improvement (75% faster)
- Added `batch_id` field to track related uploads

**New Features:**
- Statements uploaded together share a `batch_id`
- Processing happens in parallel for faster results
- Improved performance: ~3 minutes for 4 statements vs 12 minutes sequential

**Migration Guide:**
```python
# OLD (v1.0.0)
result = analyze_statement(token, "file.pdf")
transactions = result['data']['results'][0]['transactions']

# NEW (v1.1.0)
upload_result = upload_statement(token, "file.pdf")
session_id = upload_result['sessions'][0]['session_id']
# Poll for completion
session = wait_for_completion(token, session_id)
# Then fetch transactions
transactions = get_transactions(token, session_id)
```

### v1.0.0 (2026-02-09)
- Initial API release
- Bank statement analysis endpoints
- Authentication with Sanctum
- MCA detection
- True revenue calculation
- Transaction corrections
- Learned patterns

---

*Last Updated: February 11, 2026*
