# Security Audit Report - CRMFinity Laravel Application
**Date:** February 9, 2026
**Auditor:** Claude (Automated Security Scan)
**Severity Levels:** 🔴 Critical | 🟠 High | 🟡 Medium | 🟢 Low

---

## Executive Summary

This security audit identified **11 security vulnerabilities** across various severity levels. The most critical issues involve sensitive file permissions, debug mode in production, and potential file upload vulnerabilities.

**Critical Issues:** 2
**High Priority:** 3
**Medium Priority:** 4
**Low Priority:** 2

---

## 🔴 CRITICAL VULNERABILITIES

### 1. Exposed .env File with Wrong Permissions
**Severity:** CRITICAL
**File:** `.env`
**Current Permissions:** `-rwxr-xr-x` (755)

**Issue:**
```bash
-rwxr-xr-x   1 www-data www-data    2497 Feb  8 08:46 .env
```

The .env file contains sensitive credentials (database passwords, API keys, secrets) and is:
- Readable by all users (world-readable)
- Has execute permissions (unnecessary)
- Could be accessed via misconfigured web server

**Risk:**
- Database credentials exposure
- API keys (OpenAI, Anthropic, Plaid, Hubspot) exposure
- APP_KEY exposure (session hijacking)
- Complete system compromise

**Fix:**
```bash
chmod 600 .env
chown www-data:www-data .env
```

Add to `.htaccess` or Apache config:
```apache
<Files .env>
    Require all denied
</Files>
```

---

### 2. Debug Mode Enabled in Production
**Severity:** CRITICAL
**File:** `.env`
**Setting:** `APP_DEBUG=true`

**Issue:**
Debug mode is enabled, which exposes:
- Full stack traces with file paths
- Database queries and bindings
- Environment variables
- Framework internals

**Risk:**
- Information disclosure
- Attacker can map application structure
- Credentials visible in error messages
- SQL injection insights

**Fix:**
```env
APP_DEBUG=false
APP_ENV=production
```

---

## 🟠 HIGH PRIORITY VULNERABILITIES

### 3. Command Injection Risk via shell_exec()
**Severity:** HIGH
**Files:** Multiple controllers
**Occurrences:** 11 instances

**Locations:**
- `app/Http/Controllers/BankStatementController.php:114`
- `app/Services/BankAnalysisService.php:228, 298`
- `app/Http/Controllers/SmartMcaController.php:74, 1406, 1527`
- `app/Http/Controllers/ApplicationController.php:1252, 1471`
- `app/Http/Controllers/Api/BankStatementApiController.php:219`
- `app/Http/Controllers/UnderwritingController.php:71`
- `app/Console/Commands/ReprocessBankStatements.php:148`

**Current Implementation** (Example from BankStatementController.php):
```php
$command = sprintf(
    'python3 %s %s %s %s %s 2>%s',
    escapeshellarg($scriptPath),
    escapeshellarg($savedPath),
    escapeshellarg($apiKey),
    escapeshellarg($model),
    escapeshellarg($correctionsJson),
    escapeshellarg($stderrFile)
);
$output = shell_exec($command);
```

**Status:** ✅ Currently using `escapeshellarg()` - GOOD!

**Recommendations:**
1. Add validation for file paths before escapeshellarg()
2. Use `proc_open()` with explicit argument arrays instead of shell_exec()
3. Implement timeout mechanisms
4. Add command logging for audit trail

**Better Alternative:**
```php
$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open([
    'python3',
    $scriptPath,
    $savedPath,
    $apiKey,
    $model,
    $correctionsJson
], $descriptorspec, $pipes);
```

---

### 4. File Upload Security Issues
**Severity:** HIGH
**Files:** Multiple controllers

**Issues Found:**
1. **Uses getClientOriginalName()** without sanitization
2. **move() instead of store()** - bypasses Laravel's security
3. No file type validation beyond MIME
4. No file size validation in code (only in validation rules)

**Example from TrainingController.php:**
```php
foreach ($request->file('statement_pdfs') as $index => $file) {
    $filename = 'statement_' . ($index + 1) . '.pdf';
    $file->move($uploadPath, $filename);  // ⚠️ Dangerous!
}
```

**Example from SmartMcaController.php:**
```php
$originalFilename = $file->getClientOriginalName();  // ⚠️ User-controlled
$filename = time() . '_' . $originalFilename;
$file->move($uploadPath, $filename);
```

**Risks:**
- Path traversal (../../../etc/passwd)
- Double extension attacks (file.php.pdf)
- MIME type spoofing
- Malicious file upload

**Recommended Fix:**
```php
// Validate file
$request->validate([
    'statements.*' => 'required|file|mimes:pdf|max:20480'
]);

// Use secure storage
foreach ($request->file('statements') as $index => $file) {
    // Generate secure filename
    $filename = Str::random(40) . '.pdf';

    // Use Laravel storage (not move)
    $path = $file->storeAs('statements', $filename, 'local');

    // Verify file is actually PDF
    if (mime_content_type(storage_path('app/' . $path)) !== 'application/pdf') {
        Storage::delete($path);
        throw new \Exception('Invalid file type');
    }
}
```

---

### 5. World-Readable Log Files
**Severity:** HIGH
**Files:** Multiple log files with 644 permissions

**Issue:**
Log files contain sensitive information and are world-readable:
```
/var/www/html/crmfinity_laravel_claude/storage/logs/*.log (mode 0644)
```

**Risks:**
- Exposure of API keys in logs
- User data leakage
- Application structure disclosure
- Error messages with sensitive data

**Fix:**
```bash
chmod 600 storage/logs/*.log
find storage/logs -type f -name "*.log" -exec chmod 600 {} \;

# Add to log rotation
echo "create 0600 www-data www-data" >> /etc/logrotate.d/laravel
```

---

## 🟡 MEDIUM PRIORITY VULNERABILITIES

### 6. Missing Rate Limiting on API Endpoints
**Severity:** MEDIUM
**Files:** `routes/api.php`

**Issue:**
API endpoints don't have rate limiting configured, allowing:
- Brute force attacks on `/api/v1/auth/login`
- API abuse on analysis endpoints
- DoS attacks

**Current:**
```php
Route::post('/login', [AuthController::class, 'login']);  // No rate limit
```

**Recommended Fix:**
```php
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/analyze', [BankStatementApiController::class, 'analyze']);
});
```

---

### 7. Potential Mass Assignment Vulnerabilities
**Severity:** MEDIUM
**Files:** All models with `$fillable` arrays

**Issue:**
While models have `$fillable` properties (good!), some have very permissive arrays.

**Example - User.php:**
```php
protected $fillable = [
    'name',
    'email',
    'password',
    // Many other fields...
];
```

**Risk:**
If request data isn't validated properly, attackers can set unintended fields.

**Recommendation:**
1. Use `$guarded = ['id', 'created_at', 'updated_at']` when appropriate
2. Always validate input before mass assignment
3. Consider using DTOs (Data Transfer Objects)
4. Review each model's fillable array

---

### 8. SQL Injection (Parameterized - LOW RISK)
**Severity:** MEDIUM (Currently Mitigated)
**Files:** Multiple controllers

**Status:** ✅ Currently SAFE - Using parameterized queries

**Examples:**
```php
// SAFE - Uses parameter binding
AnalyzedTransaction::whereRaw('LOWER(description) = ?', [strtolower($request->description)])

// SAFE - Uses parameter binding
RevenueClassification::whereRaw('LOWER(description_pattern) LIKE ?', ['%' . $pattern . '%'])
```

**Recommendation:**
- Continue using parameterized queries
- Add code review checklist to prevent direct concatenation
- Consider static analysis tools (PHPStan, Psalm)

---

### 9. Missing Content Security Policy (CSP)
**Severity:** MEDIUM
**Files:** No CSP headers configured

**Issue:**
No Content Security Policy headers to prevent XSS attacks.

**Recommended Fix:**
Add to `config/app.php` middleware or `.htaccess`:
```php
// In a middleware
response()->header('Content-Security-Policy',
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data: https:; " .
    "font-src 'self' data:; " .
    "connect-src 'self';"
);
```

---

## 🟢 LOW PRIORITY ISSUES

### 10. Missing Security Headers
**Severity:** LOW
**Missing Headers:**
- X-Frame-Options
- X-Content-Type-Options
- X-XSS-Protection
- Referrer-Policy
- Permissions-Policy

**Recommended Fix:**
Add to Apache config or middleware:
```apache
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
```

---

### 11. Exposed Git Directory
**Severity:** LOW
**Issue:** `.git` directory is present in web root

**Risk:**
If web server is misconfigured, `.git` directory could be accessible, exposing:
- Source code
- Commit history
- Configuration files
- Developer information

**Fix:**
Add to Apache config:
```apache
<DirectoryMatch "^/.*/\.git/">
    Require all denied
</DirectoryMatch>
```

Or move application outside webroot.

---

## ✅ GOOD SECURITY PRACTICES FOUND

1. ✅ **CSRF Protection:** All forms use `@csrf` tokens
2. ✅ **Authentication Middleware:** Routes properly protected with `auth` middleware
3. ✅ **API Authentication:** Using Laravel Sanctum (auth:sanctum)
4. ✅ **Password Hashing:** Using bcrypt (BCRYPT_ROUNDS=12)
5. ✅ **SQL Parameterization:** All raw queries use parameter binding
6. ✅ **Shell Escaping:** Using `escapeshellarg()` on all shell commands
7. ✅ **No XSS in Blade:** No unsafe `{!! !!}` output found
8. ✅ **Mass Assignment Protection:** Models have `$fillable` arrays
9. ✅ **Input Validation:** Request validation used throughout

---

## PRIORITY FIX CHECKLIST

### Immediate (Today):
- [ ] Fix .env permissions: `chmod 600 .env`
- [ ] Disable debug mode: `APP_DEBUG=false`
- [ ] Fix log file permissions: `chmod 600 storage/logs/*.log`

### This Week:
- [ ] Add rate limiting to API endpoints
- [ ] Implement secure file upload handling
- [ ] Add security headers
- [ ] Block .git and .env via web server config

### This Month:
- [ ] Replace shell_exec with proc_open
- [ ] Implement Content Security Policy
- [ ] Audit mass assignment vulnerabilities
- [ ] Set up security monitoring/logging

---

## TESTING RECOMMENDATIONS

1. **Penetration Testing:**
   - SQL injection testing
   - XSS testing
   - CSRF testing
   - File upload testing

2. **Tools to Use:**
   - OWASP ZAP
   - Burp Suite
   - SQLMap
   - Nikto

3. **Code Analysis:**
   - PHPStan (static analysis)
   - Psalm (type checking)
   - Security Checker (dependency vulnerabilities)

---

## MONITORING RECOMMENDATIONS

1. **Log Monitoring:**
   - Monitor for failed login attempts
   - Track API rate limit hits
   - Alert on shell command executions
   - Watch for file upload anomalies

2. **File Integrity:**
   - Monitor .env file changes
   - Track unexpected file modifications
   - Alert on new PHP files in upload directories

3. **Intrusion Detection:**
   - Set up fail2ban for repeated attack attempts
   - Monitor for SQL injection patterns in logs
   - Track unusual API usage patterns

---

## CONTACT & RESOURCES

- OWASP Top 10: https://owasp.org/www-project-top-ten/
- Laravel Security: https://laravel.com/docs/security
- PHP Security: https://www.php.net/manual/en/security.php

---

**Report Generated:** February 9, 2026
**Next Audit Recommended:** Monthly
