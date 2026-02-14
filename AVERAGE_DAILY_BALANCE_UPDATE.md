# Average Daily Balance Update

## Summary

Updated the system to prioritize using average daily balance values from bank statements when available, and only calculate them when not mentioned in the statements.

## Changes Made

### 1. Database Migration
**File**: `database/migrations/2026_02_13_181407_add_average_daily_balance_to_analysis_sessions_table.php`
- Added `average_daily_balance` column to `analysis_sessions` table
- Type: `decimal(15, 2)`
- Nullable: yes (only populated if extracted from statement)

### 2. Python Extraction Script
**File**: `storage/app/scripts/bank_statement_extractor.py`
- Updated AI prompt to extract `average_daily_balance` from bank statements if mentioned
- Added to `statement_summary` output:
  ```json
  {
    "statement_summary": {
      "beginning_balance": 1234.56,
      "ending_balance": 5678.90,
      "average_daily_balance": 3456.78  // NEW
    }
  }
  ```

### 3. ProcessBankStatement Job
**File**: `app/Jobs/ProcessBankStatement.php`
- Updated session creation to store `average_daily_balance` from extraction results
- Line added: `'average_daily_balance' => $data['statement_summary']['average_daily_balance'] ?? null`

### 4. AnalysisSession Model
**File**: `app/Models/AnalysisSession.php`
- Added `average_daily_balance` to `$fillable` array
- Added `average_daily_balance` to `$casts` array as `decimal:2`

### 5. BankStatementAnalyzerService
**File**: `app/Services/BankStatementAnalyzerService.php`
- Updated `analyzeBalances()` method to check metadata for stored average daily balance
- Logic:
  1. Check if `$metadata['average_daily_balance']` is set
  2. If yes, use that value
  3. If no, calculate from transactions (existing logic)

### 6. BankStatementController
**File**: `app/Http/Controllers/BankStatementController.php`
- Updated `groupTransactionsByMonth()` method
- Added check for session's `average_daily_balance` before calculating
- Logic:
  ```php
  if ($session && $session->average_daily_balance !== null) {
      // Use value from statement
      $month['average_daily_balance'] = (float) $session->average_daily_balance;
      $month['average_daily_balance_method'] = 'from_statement';
  } else {
      // Calculate from transaction balances (existing logic)
  }
  ```

## How It Works

### Flow for New Statements

1. **Upload**: User uploads bank statement PDF
2. **Extraction**: Python script extracts transactions and summary data
   - If statement mentions average daily balance → extracts it
   - If not mentioned → `average_daily_balance` is null
3. **Storage**: Job saves to database including `average_daily_balance` field
4. **Analysis**: When analyzing:
   - If `average_daily_balance` exists in DB → use it directly
   - If null → calculate from transaction data

### Benefits

1. **Accuracy**: Uses bank's official average daily balance when available
2. **Fallback**: Still calculates when not available (backwards compatible)
3. **Performance**: Skips calculation when not needed
4. **Transparency**: `average_daily_balance_method` field shows source:
   - `from_statement` - Used value from bank statement
   - `actual_balances` - Calculated from transaction ending balances
   - `no_balance_data` - No balance data available

## Testing

To verify the implementation:

1. **Test with statement that has ADB mentioned**:
   - Upload statement with average daily balance printed on it
   - Verify `average_daily_balance` is stored in database
   - Verify analysis uses this value (check `average_daily_balance_method: 'from_statement'`)

2. **Test without ADB mentioned**:
   - Upload statement without average daily balance
   - Verify `average_daily_balance` is null in database
   - Verify analysis calculates it (check `average_daily_balance_method: 'actual_balances'`)

## Migration Status

✅ Migration run successfully
✅ Column added to database
✅ All code changes implemented
