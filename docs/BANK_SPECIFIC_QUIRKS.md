# Bank-Specific Quirks & Patterns

## Overview

This document catalogs the unique formatting patterns, quirks, and classification rules for different bank statements. Understanding these patterns is critical for accurate transaction extraction and credit/debit classification.

## Major US Banks

### Chase Bank

**Format Characteristics:**
- Separate sections for "DEPOSITS AND ADDITIONS" and "WITHDRAWALS AND DEBITS"
- Clear section headers with column labels
- Date format: MM/DD (year implicit from statement period)
- Running balance shown after each transaction

**Credit Indicators:**
- Section: "DEPOSITS AND ADDITIONS"
- Keywords: `DIRECT DEP`, `ATM CREDIT`, `ZELLE FROM`, `MOBILE DEPOSIT`

**Debit Indicators:**
- Section: "WITHDRAWALS AND DEBITS" or "CHECKS PAID"
- Keywords: `POS DEBIT`, `ATM WITHDR`, `ZELLE TO`, `ONLINE PAYMENT`

**Common Pitfalls:**
- ACH transactions can appear in either section - rely on section header, not ACH keyword
- Wire transfers show as "WIRE TYPE:FED" without clear direction - check section

---

### Bank of America

**Format Characteristics:**
- Uses "Deposits and other credits" and "Withdrawals and other debits" headers
- Two-column layout for debits and credits on same rows
- Date format: MM/DD/YY
- Check images may interrupt transaction flow

**Credit Indicators:**
- Column: "Credits" (right column)
- Keywords: `ONLINE TRANSFER FROM`, `PAYROLL`, `DIRECT DEP`

**Debit Indicators:**
- Column: "Debits" (left column)
- Keywords: `PURCHASE`, `ONLINE TRANSFER TO`, `CHECK`

**Common Pitfalls:**
- Credit card payments to BofA appear as debits (outgoing money)
- Internal transfers between BofA accounts need context to classify
- "TRANSFER" alone is ambiguous - look for FROM/TO

---

### Wells Fargo

**Format Characteristics:**
- Uses "Additions" and "Subtractions" terminology
- Transaction reference numbers included
- May have multi-line descriptions
- Date format: MM/DD

**Credit Indicators:**
- Section: "ADDITIONS"
- Keywords: `DEPOSIT`, `TRANSFER IN`, `CREDIT`, `REFUND`

**Debit Indicators:**
- Section: "SUBTRACTIONS" or "WITHDRAWALS"
- Keywords: `PURCHASE`, `BILL PAY`, `ATM WITHDRAWAL`, `DEBIT`

**Common Pitfalls:**
- International transactions may have currency conversion notes
- Business accounts have additional fee transactions
- Overdraft protection transfers can be confusing

---

### Citizens Bank

**Format Characteristics:**
- Uses "Credits" and "Debits" columns side by side
- Amount appears in respective column (other column blank)
- Reference numbers at line start
- Date format: MM/DD

**Credit Indicators:**
- Column: Has value in "Credits" column
- Keywords: `DEPOSIT`, `FROM`, `CREDIT`

**Debit Indicators:**
- Column: Has value in "Debits" column
- Keywords: `TO`, `PAYMENT`, `WITHDRAWAL`

**Common Pitfalls:**
- Minimum balance fees appear as debits
- Interest credits can be very small amounts
- ACH credits/debits need description analysis

---

### TD Bank

**Format Characteristics:**
- Clean tabular format with separate amount columns
- "Withdrawals" and "Deposits" as column headers
- Check number column for checks
- Date format: MM/DD

**Credit Indicators:**
- Column: "Deposits" column
- Keywords: `DEPOSIT`, `INCOMING`, `FROM`

**Debit Indicators:**
- Column: "Withdrawals" column
- Keywords: `CHECK`, `PAYMENT`, `TO`, `ATM`

**Common Pitfalls:**
- TD-branded credit card payments may cause confusion
- Cross-border transactions (US/Canada) have additional details
- Service charges at month end

---

### PNC Bank

**Format Characteristics:**
- Uses "DEPOSITS/CREDITS" and "WITHDRAWALS/DEBITS" sections
- Running balance updated after each transaction
- Clear date and description fields
- Date format: MM/DD/YYYY

**Credit Indicators:**
- Section: "DEPOSITS/CREDITS"
- Keywords: `DEPOSIT`, `CREDIT`, `FROM`, `ACH CREDIT`

**Debit Indicators:**
- Section: "WITHDRAWALS/DEBITS"
- Keywords: `DEBIT`, `CHECK`, `PAYMENT`, `TO`, `ACH DEBIT`

**Common Pitfalls:**
- Virtual Wallet sub-accounts may appear as internal transfers
- Spending account vs Reserve account transactions

---

## MCA Lenders Detection

### Common MCA Lender Names

| Lender | Common Transaction Patterns |
|--------|----------------------------|
| Kapitus | `KAPITUS`, `KAPITUS LLC`, `KAPITUS MERCHANT` |
| Merchant Marketplace | `MERCHANT MARKETPLACE`, `MMP ACH` |
| Yellowstone Capital | `YELLOWSTONE`, `YSC ACH` |
| Credibly | `CREDIBLY`, `CREDIBLY FUNDING` |
| OnDeck | `ONDECK`, `ON DECK` |
| BlueVine | `BLUEVINE`, `BLUE VINE` |
| Kabbage | `KABBAGE`, `KBB ACH` |
| Can Capital | `CAN CAPITAL`, `CANCAPITAL` |
| Forward Financing | `FORWARD FIN`, `FORWARD FINANCING` |
| Rapid Finance | `RAPID FINANCE`, `RAPID ADVANCE` |
| Swift Capital | `SWIFT CAPITAL`, `SWIFT FUNDING` |
| ClearView | `CLEARVIEW`, `CLEAR VIEW` |
| Lendio | `LENDIO` |
| Fora Financial | `FORA FINANCIAL`, `FORA ACH` |
| Libertas | `LIBERTAS`, `LIBERTAS FUNDING` |
| National Funding | `NATIONAL FUNDING`, `NATL FUNDING` |
| Merchants Capital | `MERCHANTS CAPITAL`, `MERCH CAP` |

**MCA Payment Characteristics:**
- Usually daily ACH debits
- Consistent amounts (same dollar value each day)
- Business days only (no weekends)
- May have reference numbers or contract IDs

---

## Revenue Classification Rules

### Definite Revenue (Deposits/Credits)

**Strong Indicators:**
- Customer payment keywords: `PAYMENT FROM`, `PAY FROM`, `CUST`
- Point of sale credits: `SQ *`, `SQUARE`, `STRIPE`, `SHOPIFY`
- Card processing: `CARD SETTLEMENT`, `MERCHANT SERVICES`
- Invoice payments: `INV#`, `INVOICE`

### Definite Non-Revenue

**Loan Proceeds:**
- Keywords: `LOAN PROCEEDS`, `FUNDING`, `ADVANCE`
- Large single deposits (compared to typical)
- From known lender names

**Internal Transfers:**
- Keywords: `TRANSFER FROM`, `INTERNAL`, `SWEEP`
- Matching outgoing transfer on same date
- Same-institution references

**Refunds to Customer:**
- Keywords: `REFUND TO`, `RETURN`, `CHARGEBACK`
- May have original transaction reference

---

## Date Parsing Patterns

| Bank | Primary Format | Secondary Format | Notes |
|------|---------------|------------------|-------|
| Chase | MM/DD | - | Year from statement header |
| Bank of America | MM/DD/YY | MM/DD/YYYY | Mixed within same statement |
| Wells Fargo | MM/DD | MM-DD | Dash separator rare |
| Citizens | MM/DD | - | Always 2-digit month/day |
| TD Bank | MM/DD | MM/DD/YYYY | YY format rare |
| PNC | MM/DD/YYYY | MM/DD/YY | Usually full year |

---

## Amount Parsing Edge Cases

### Negative Amount Indicators
- Parentheses: `(123.45)` = negative
- Minus sign: `-123.45` = negative
- CR suffix: `123.45 CR` = credit (positive)
- DR suffix: `123.45 DR` = debit (negative for balance)

### Currency Symbols
- Dollar sign: `$1,234.56`
- No symbol: `1,234.56`
- Comma thousands: Common in US
- Period decimal: Standard in US

### International Amounts
- Some banks show original currency and USD equivalent
- Exchange rate may be included
- Look for 3-letter currency codes: `EUR`, `GBP`, `CAD`

---

## Confidence Scoring Adjustments

### High Confidence Scenarios (0.9+)
- Transaction in clearly labeled section (DEPOSITS vs WITHDRAWALS)
- Strong directional keyword (FROM/TO)
- Balance verification matches expected change

### Medium Confidence Scenarios (0.6-0.9)
- Keyword match but no section context
- Amount format correct but description vague
- Mixed signals (e.g., "TRANSFER" without direction)

### Low Confidence Scenarios (<0.6)
- No clear keywords or section headers
- Ambiguous description
- Balance doesn't verify as expected
- Multi-currency or international transaction

---

## Common Extraction Errors

### Type Misclassification
1. **Loan funding marked as revenue** - Watch for large single deposits
2. **Refunds to customers marked as credits** - Check for REFUND TO keyword
3. **Internal transfers counted** - Look for matching opposite transaction

### Amount Errors
1. **OCR issues with 0/O and 1/l** - Common in scanned PDFs
2. **Missing decimal point** - 12345 vs 123.45
3. **Thousand separator confusion** - 1,234 vs 1.234 (European)

### Date Errors
1. **Wrong year assumed** - Check statement period
2. **Month/day swap** - Ambiguous formats like 01/02
3. **Missing dates** - Some continuation rows omit date

---

## Best Practices for New Banks

When encountering a new bank format:

1. **Identify Section Headers** - Look for DEPOSIT/CREDIT and WITHDRAWAL/DEBIT sections
2. **Map Column Structure** - Note position of Date, Description, Amount, Balance
3. **Find Credit/Debit Indicators** - Section names, column headers, or amount signs
4. **Test with Known Transactions** - Use statements with expected values
5. **Document Quirks** - Add to this file for future reference
6. **Add to Examples** - Update chain-of-thought prompt examples

---

## Version History

| Date | Changes |
|------|---------|
| 2025-01-07 | Initial documentation - PR4 Production Hardening |

