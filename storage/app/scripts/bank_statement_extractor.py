#!/usr/bin/env python3
"""
Bank Statement Transaction Extractor
A production-level script to extract and analyze bank statement transactions using AI.

Works with any bank statement from any financial institution (banks, credit unions, etc.)
as long as the statement contains dated transactions.

Usage: python3 bank_statement_extractor.py <pdf_path> <api_key> [model] [corrections_json]
"""

import pdfplumber
import json
import sys
import os
import re
import time
from datetime import datetime
from anthropic import Anthropic
from typing import List, Dict, Tuple
import pytesseract
from pdf2image import convert_from_path
from PIL import Image
import fitz  # PyMuPDF

# Pricing per 1M tokens (as of 2026)
PRICING = {
    "claude-opus-4-6": {
        "input": 15.00,
        "output": 75.00
    },
    "claude-sonnet-4-5": {
        "input": 3.00,
        "output": 15.00
    },
    "claude-haiku-4-5": {
        "input": 0.80,
        "output": 4.00
    }
}


def extract_text_with_pymupdf(pdf_path: str) -> Tuple[str, int]:
    """
    Extract text using PyMuPDF (fitz) which has better font encoding handling.
    This works better for PDFs with CID font issues.
    """
    if not os.path.exists(pdf_path):
        raise FileNotFoundError(f"PDF file not found: {pdf_path}")

    try:
        doc = fitz.open(pdf_path)
        text_content = []

        for page_num in range(len(doc)):
            page = doc[page_num]
            # Extract text with layout preservation
            text = page.get_text("text")
            if text and text.strip():
                text_content.append(text)

        doc.close()

        if not text_content:
            raise Exception("No text could be extracted with PyMuPDF")

        combined_text = "\n\n".join(text_content)
        return combined_text, len(text_content)

    except Exception as e:
        raise Exception(f"PyMuPDF extraction failed: {str(e)}")


def extract_text_with_ocr(pdf_path: str) -> Tuple[str, int]:
    """
    Extract text from scanned PDFs using OCR (Optical Character Recognition).
    This is used as a fallback when pdfplumber can't extract text.
    """
    if not os.path.exists(pdf_path):
        raise FileNotFoundError(f"PDF file not found: {pdf_path}")

    try:
        # Convert PDF pages to images at 150 DPI (optimized for speed)
        # Lower DPI = faster processing, still readable for bank statements
        # Most bank statements are clear enough at 150 DPI
        images = convert_from_path(pdf_path, dpi=150, fmt='jpeg', thread_count=4)

        if not images:
            raise Exception("Could not convert PDF to images")

        text_content = []
        total_pages = len(images)
        print(f"OCR: Processing {total_pages} pages at 150 DPI (optimized for speed)...", file=sys.stderr)

        # Limit OCR to first 10 pages to prevent excessive processing time
        # Most bank statements are 1-5 pages
        max_pages = min(total_pages, 10)
        if total_pages > 10:
            print(f"  WARNING: PDF has {total_pages} pages, limiting to first {max_pages} pages", file=sys.stderr)

        for i, image in enumerate(images[:max_pages]):
            print(f"  Page {i+1}/{max_pages}...", end=' ', flush=True, file=sys.stderr)
            # Run OCR on each image with optimized config
            # PSM 6 = Assume uniform block of text (faster than PSM 4)
            # OEM 1 = Neural nets LSTM only (faster than OEM 3)
            custom_config = r'--oem 1 --psm 6'
            text = pytesseract.image_to_string(image, lang='eng', config=custom_config)
            if text and text.strip():
                text_content.append(f"\n=== PAGE {i+1} ===\n{text}")
            print("✓", file=sys.stderr)

        if not text_content:
            raise Exception("No text could be extracted via OCR")

        return "\n\n".join(text_content), len(images)

    except Exception as e:
        raise Exception(f"OCR extraction failed: {str(e)}")


def is_text_garbled(text: str) -> bool:
    """
    Check if extracted text is garbled (contains too many CID references, encoding issues, or watermarks).
    CID references look like (cid:0), (cid:2), etc. and indicate font encoding problems.
    Watermarks create scattered single letters throughout the text.

    NOTE: This is intentionally LESS strict to avoid triggering slow OCR unnecessarily.
    We prefer to use slightly garbled text over waiting minutes for OCR.
    """
    if not text or len(text) < 50:  # Reduced from 100 to be less strict
        return True

    # Count CID references
    cid_count = text.count('(cid:')

    # If more than 5% of the text is CID references (each ~10 chars), it's garbled
    # 5% threshold = very lenient, most good PDFs have 0%
    cid_percentage = (cid_count * 10) / len(text)

    if cid_percentage > 0.05:
        return True

    # Detect watermark interference: count lines with scattered single letters
    # Watermarks typically create lines like "e c h k a c r l p n t a"
    lines = text.split('\n')
    single_letter_lines = 0
    total_lines = len(lines)

    for line in lines:
        # Strip and check if line consists mostly of single letters separated by spaces
        stripped = line.strip()
        if len(stripped) > 0 and len(stripped) < 50:
            # Count single-letter tokens
            tokens = stripped.split()
            if len(tokens) > 3:
                single_letter_count = sum(1 for t in tokens if len(t) == 1 and t.isalpha())
                # If more than 70% are single letters, it's likely watermark noise
                if single_letter_count / len(tokens) > 0.7:
                    single_letter_lines += 1

    # If more than 10% of lines are watermark noise, consider it garbled
    # Increased threshold from 3% to 10% to avoid triggering OCR unnecessarily
    # Most bank statements can be processed even with minor watermark noise
    if total_lines > 0 and (single_letter_lines / total_lines) > 0.10:
        return True

    return False


def extract_text_from_pdf(pdf_path: str) -> Tuple[str, int]:
    if not os.path.exists(pdf_path):
        raise FileNotFoundError(f"PDF file not found: {pdf_path}")

    text_content = []
    pages = 0

    # First, try to extract text using pdfplumber (fast for text-based PDFs)
    with pdfplumber.open(pdf_path) as pdf:
        if len(pdf.pages) == 0:
            raise Exception("PDF file has no pages")

        pages = len(pdf.pages)
        for page in pdf.pages:
            text = page.extract_text()
            if text:
                text_content.append(text)

    # Check if text is garbled (CID references, encoding issues)
    extracted_text = "\n\n".join(text_content) if text_content else ""

    # If no text was extracted or text is garbled, try alternative methods
    if not text_content or is_text_garbled(extracted_text):
        print(f"⚠️  pdfplumber extraction {'failed' if not text_content else 'produced garbled text'}, trying PyMuPDF...", file=sys.stderr)

        # First try PyMuPDF (better font handling)
        try:
            pymupdf_text, pymupdf_pages = extract_text_with_pymupdf(pdf_path)
            # Check if PyMuPDF text is also garbled
            if not is_text_garbled(pymupdf_text):
                print("✓ PyMuPDF extraction successful", file=sys.stderr)
                return pymupdf_text, pymupdf_pages
            else:
                print("⚠️  PyMuPDF produced garbled text", file=sys.stderr)
        except Exception as pymupdf_error:
            print(f"⚠️  PyMuPDF failed: {str(pymupdf_error)}", file=sys.stderr)

        # Check if we have SOME usable text before falling back to OCR
        # If we have at least 500 characters of text, use it even if slightly garbled
        # This avoids slow OCR when text is mostly readable
        if len(extracted_text) > 500:
            print(f"ℹ️  Using pdfplumber text despite minor issues ({len(extracted_text)} chars available)", file=sys.stderr)
            print("   Skipping OCR to save time", file=sys.stderr)
            return extracted_text, pages

        # Last resort: fall back to OCR (SLOW!)
        print("⚠️  Falling back to OCR (this may take several minutes)...", file=sys.stderr)
        try:
            return extract_text_with_ocr(pdf_path)
        except Exception as ocr_error:
            # If OCR fails but we had some text from pdfplumber, use that
            if len(extracted_text) > 100:
                print(f"⚠️  OCR failed, using pdfplumber text as fallback ({len(extracted_text)} chars)", file=sys.stderr)
                return extracted_text, pages
            raise Exception(f"No text could be extracted from PDF. Text extraction (pdfplumber), PyMuPDF, and OCR all failed.")

    return extracted_text, pages


def preprocess_check_tables(text: str) -> str:
    """
    Extract checks from table format commonly found in bank statements.

    Handles formats like:
    CATE CHECK # AMOUNT(S)   DATE CHECK # AMOUNT(S)   DATE CHECK # AMOUNT(S)
    /03  03      310.30       /13  56      270.00      /21  79     705.00

    Also handles two-column formats like:
    '08/22/25 2745 -756.00 08/13/25 3879 -648.00'

    And PNC 3-column format like:
    '11/10    14381  *               12.92    081942621   11/17    14390                276.00    018849020'
    """
    lines = text.split('\n')
    processed_lines = []
    extracted_checks = []
    in_check_table = False

    # Pattern for two-column check lines: DATE CHECKNUM AMOUNT DATE CHECKNUM AMOUNT
    two_col_pattern = re.compile(
        r'^(\d{2}/\d{2}/\d{2})\s+(\d{4})\*?\s+(-[\d,]+\.\d{2})\s+(\d{2}/\d{2}/\d{2})\s+(\d{4})\*?\s+(-[\d,]+\.\d{2})$'
    )

    # Pattern for 3-column check table (Truist format):
    # /03  03  310.30  /13  56  270.00  /21  79  705.00
    three_col_pattern = re.compile(
        r'(/\d{2})\s+(\*?\d+|\*\d+)\s+([\d,]+\.?\d*)\s+(/\d{2})\s+(\*?\d+|\*\d+)\s+([\d,]+\.?\d*)\s+(/\d{2})\s+(\*?\d+|\*\d+)\s+([\d,]+\.?\d*)'
    )

    # Pattern for PNC 3-column format with reference numbers:
    # 11/10    14381  *               12.92    081942621   11/17    14390                276.00    018849020   11/24    14400                496.00    011725650
    # Each check: MM/DD CheckNum [*] Amount RefNum
    pnc_check_pattern = re.compile(
        r'(\d{2}/\d{2})\s+(\d{4,5})\s+\*?\s+([\d,]+\.\d{2})\s+(\d{9,10})'
    )

    # Single check entry pattern: /03  03  310.30
    single_check_pattern = re.compile(r'(/\d{2})\s+(\*?\d+)\s+([\d,]+\.?\d*)')

    for line in lines:
        line_stripped = line.strip()

        # Detect check table header (Truist format)
        if 'CATE' in line and 'CHECK' in line and 'AMOUNT' in line:
            in_check_table = True
            processed_lines.append(line)
            continue

        # Detect PNC check table header
        if 'Checks and Substitute Checks' in line or ('Date' in line and 'Check' in line and 'Reference' in line):
            in_check_table = True
            processed_lines.append(line)
            continue

        # End of check table
        if in_check_table and ('total checks' in line.lower() or 'Other withdrawals' in line or 'Debit Card Purchases' in line or 'POS Purchases' in line):
            in_check_table = False
            processed_lines.append(line)
            continue

        # Process check table rows
        if in_check_table:
            # Try 3-column pattern
            match_3col = three_col_pattern.search(line_stripped)
            if match_3col:
                groups = match_3col.groups()
                # Extract 3 checks from this line
                for i in range(0, 9, 3):
                    date, check_num, amount = groups[i], groups[i+1], groups[i+2]
                    extracted_checks.append((date, check_num.replace('*', ''), amount))
                processed_lines.append(f"[3 CHECK TRANSACTIONS extracted]")
                continue

            # Try single or multiple check patterns
            matches = single_check_pattern.findall(line_stripped)
            if matches:
                for date, check_num, amount in matches:
                    extracted_checks.append((date, check_num.replace('*', ''), amount))
                processed_lines.append(f"[{len(matches)} CHECK TRANSACTION(S) extracted]")
                continue

        # Try PNC format (outside or inside check table) - extract all checks on the line
        pnc_matches = pnc_check_pattern.findall(line_stripped)
        if pnc_matches:
            for date, check_num, amount, ref_num in pnc_matches:
                extracted_checks.append((date, check_num, amount))
            processed_lines.append(f"[{len(pnc_matches)} PNC CHECK TRANSACTION(S) extracted]")
            continue

        # Handle two-column format outside table
        match_2col = two_col_pattern.match(line_stripped)
        if match_2col:
            date1, check1, amt1, date2, check2, amt2 = match_2col.groups()
            extracted_checks.append((date1, check1, amt1))
            extracted_checks.append((date2, check2, amt2))
            processed_lines.append(f"[2 CHECK TRANSACTIONS extracted]")
        else:
            processed_lines.append(line)

    # Add all extracted checks as clear, individual transactions
    if extracted_checks:
        processed_lines.append("")
        processed_lines.append("=== INDIVIDUAL CHECK TRANSACTIONS (IMPORTANT: These are DEBITS) ===")
        processed_lines.append(f"Total {len(extracted_checks)} checks found - each is a separate withdrawal transaction")
        processed_lines.append("")
        for date, check_num, amount in extracted_checks:
            # Format as clear debit transaction
            processed_lines.append(f"{date} Check #{check_num} DEBIT ${amount}")

    return '\n'.join(processed_lines)


def build_corrections_prompt(corrections: List[Dict]) -> str:
    """Build a prompt section for learned corrections."""
    if not corrections:
        return ""

    correction_rules = []
    for c in corrections:
        pattern = c.get('description_pattern', '')
        correct_type = c.get('correct_type', '')
        if pattern and correct_type:
            correction_rules.append(f"- Transactions matching '{pattern}' should be classified as '{correct_type}'")

    if not correction_rules:
        return ""

    return f"""

LEARNED CORRECTIONS (IMPORTANT - Apply these rules with high priority):
The following patterns have been manually corrected by users. Apply these classifications:
{chr(10).join(correction_rules)}

These corrections override the default classification rules when the description matches the pattern.
"""


def safe_float_amount(value) -> float:
    """
    Safely convert amount to float, handling commas and dollar signs.
    Handles both numeric and string inputs with commas.
    """
    if isinstance(value, (int, float)):
        return float(value)
    if isinstance(value, str):
        # Remove dollar signs, commas, and whitespace
        cleaned = value.replace('$', '').replace(',', '').strip()
        try:
            return float(cleaned) if cleaned else 0.0
        except ValueError:
            return 0.0
    return 0.0


def normalize_pattern(description: str) -> str:
    """Normalize description for pattern matching."""
    # Remove dates
    normalized = re.sub(r'\d{1,2}/\d{1,2}(/\d{2,4})?', '', description)
    # Replace numbers with placeholder
    normalized = re.sub(r'\d+', '#', normalized)
    # Clean up whitespace
    normalized = re.sub(r'\s+', ' ', normalized)
    return normalized.strip()


def apply_corrections(transactions: List[Dict], corrections: List[Dict]) -> List[Dict]:
    """Apply learned corrections to transactions after extraction."""
    if not corrections:
        return transactions

    # Build a lookup of patterns to correct types
    correction_map = {}
    for c in corrections:
        pattern = c.get('description_pattern', '').lower()
        correct_type = c.get('correct_type', '')
        if pattern and correct_type:
            correction_map[pattern] = correct_type

    # Apply corrections
    for txn in transactions:
        desc = txn.get('description', '')
        normalized = normalize_pattern(desc).lower()

        # Check for matching patterns
        for pattern, correct_type in correction_map.items():
            # Check if pattern is contained in description
            if pattern.lower() in normalized or normalized in pattern.lower():
                if txn['type'] != correct_type:
                    txn['type'] = correct_type
                    txn['corrected_by_learning'] = True
                break

    return transactions


def estimate_tokens(text: str) -> int:
    """Estimate token count (roughly 4 characters per token for English text)."""
    return len(text) // 4


def deduplicate_transactions(transactions: List[Dict]) -> List[Dict]:
    """
    Remove duplicate transactions that might appear in overlapping chunks.
    Two transactions are considered duplicates if they have the same date, description, and amount.
    """
    seen = set()
    unique_transactions = []

    for txn in transactions:
        # Create a unique key from date, description, and amount
        key = (
            txn.get("date", ""),
            txn.get("description", "").strip().lower(),
            round(safe_float_amount(txn.get("amount", 0)), 2)
        )

        if key not in seen:
            seen.add(key)
            unique_transactions.append(txn)

    return unique_transactions


def parse_json_response(result: str) -> Dict:
    """Parse JSON response from OpenAI with multiple recovery strategies."""
    data = None

    # Strip markdown code block markers if present
    result = result.strip()
    if result.startswith('```json'):
        result = result[7:]  # Remove ```json
    elif result.startswith('```'):
        result = result[3:]  # Remove ```
    if result.endswith('```'):
        result = result[:-3]  # Remove closing ```
    result = result.strip()

    try:
        data = json.loads(result)
    except json.JSONDecodeError as e:
        # Try multiple recovery strategies

        # Strategy 1: Find and extract just the transactions array using regex
        try:
            # Look for transactions array pattern
            match = re.search(r'"transactions"\s*:\s*\[', result)
            if match:
                start = match.start()
                # Find the opening brace before transactions
                brace_pos = result.rfind('{', 0, start)
                if brace_pos >= 0:
                    # Try to find matching closing structures
                    subset = result[brace_pos:]
                    # Count brackets to find where array ends
                    bracket_count = 0
                    brace_count = 0
                    end_pos = 0
                    in_string = False
                    escape_next = False

                    for i, char in enumerate(subset):
                        if escape_next:
                            escape_next = False
                            continue
                        if char == '\\':
                            escape_next = True
                            continue
                        if char == '"' and not escape_next:
                            in_string = not in_string
                            continue
                        if in_string:
                            continue
                        if char == '{':
                            brace_count += 1
                        elif char == '}':
                            brace_count -= 1
                            if brace_count == 0:
                                end_pos = i + 1
                                break
                        elif char == '[':
                            bracket_count += 1
                        elif char == ']':
                            bracket_count -= 1

                    if end_pos > 0:
                        clean_json = subset[:end_pos]
                        data = json.loads(clean_json)
        except:
            pass

        # Strategy 2: Try to fix truncated JSON
        if data is None:
            try:
                last_brace = result.rfind('}')
                last_bracket = result.rfind(']')
                if last_brace > 0 or last_bracket > 0:
                    cut_point = max(last_brace, last_bracket) + 1
                    truncated = result[:cut_point]
                    open_braces = truncated.count('{') - truncated.count('}')
                    open_brackets = truncated.count('[') - truncated.count(']')
                    truncated += ']' * open_brackets + '}' * open_braces
                    data = json.loads(truncated)
            except:
                pass

        # Strategy 3: Extract transactions using simple regex
        if data is None:
            try:
                # Find all transaction-like objects
                pattern = r'\{\s*"date"\s*:\s*"[^"]+"\s*,\s*"description"\s*:\s*"[^"]*"\s*,\s*"amount"\s*:\s*[\d.]+\s*,\s*"type"\s*:\s*"(?:credit|debit)"\s*\}'
                matches = re.findall(pattern, result, re.IGNORECASE)
                if matches:
                    transactions_list = [json.loads(m) for m in matches]
                    data = {"transactions": transactions_list}
            except:
                pass

        if data is None:
            # Return empty structure if all parsing fails
            data = {"transactions": []}

    return data


def chunk_text(text: str, max_tokens: int = 100000, overlap_lines: int = 5) -> List[str]:
    """
    Split text into chunks that fit within token limit.
    Tries to split at natural boundaries (empty lines) to avoid breaking transactions.
    """
    lines = text.split('\n')
    chunks = []
    current_chunk = []
    current_tokens = 0

    for i, line in enumerate(lines):
        line_tokens = estimate_tokens(line + '\n')

        # If adding this line exceeds the limit, save current chunk and start new one
        if current_tokens + line_tokens > max_tokens and current_chunk:
            chunks.append('\n'.join(current_chunk))
            # Keep last few lines for context continuity
            current_chunk = current_chunk[-overlap_lines:] if len(current_chunk) > overlap_lines else []
            current_tokens = sum(estimate_tokens(l + '\n') for l in current_chunk)

        current_chunk.append(line)
        current_tokens += line_tokens

    # Add the last chunk
    if current_chunk:
        chunks.append('\n'.join(current_chunk))

    return chunks


def extract_transactions_deterministic(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Extract transactions deterministically using table structure from PDF.
    Returns (transactions_list, success_flag).

    This method uses pdfplumber to extract tables with exact column positions,
    then classifies transactions based on which column contains the amount.
    """
    try:
        transactions = []

        with pdfplumber.open(pdf_path) as pdf:
            for page_num, page in enumerate(pdf.pages, 1):
                # Extract tables from the page
                tables = page.extract_tables()

                if not tables:
                    continue

                for table in tables:
                    if not table or len(table) < 2:  # Need at least header + 1 row
                        continue

                    # First row is likely headers
                    headers = [str(h).lower() if h else '' for h in table[0]]

                    # Identify column indices
                    date_col = None
                    desc_col = None
                    debit_col = None
                    credit_col = None
                    balance_col = None

                    for i, header in enumerate(headers):
                        if 'date' in header:
                            date_col = i
                        elif 'description' in header or 'transaction' in header:
                            desc_col = i
                        elif 'debit' in header or 'withdrawal' in header or 'payment' in header and 'out' in header:
                            debit_col = i
                        elif 'credit' in header or 'deposit' in header or 'payment' in header and 'in' in header:
                            credit_col = i
                        elif 'balance' in header:
                            balance_col = i

                    # Skip if we can't identify debit and credit columns
                    if debit_col is None or credit_col is None:
                        continue

                    # Process each row (skip header)
                    for row in table[1:]:
                        if not row or len(row) <= max(debit_col, credit_col):
                            continue

                        # Extract values
                        date_val = row[date_col] if date_col is not None and date_col < len(row) else None
                        desc_val = row[desc_col] if desc_col is not None and desc_col < len(row) else ''
                        debit_val = row[debit_col] if debit_col < len(row) else None
                        credit_val = row[credit_col] if credit_col < len(row) else None
                        balance_val = row[balance_col] if balance_col is not None and balance_col < len(row) else None

                        # Skip if no date or description
                        if not date_val or not desc_val:
                            continue

                        # Parse amount and determine type based on column
                        amount = None
                        txn_type = None

                        # Check debit column first
                        if debit_val and str(debit_val).strip() and str(debit_val).strip() not in ['-', '', 'None']:
                            amount_str = str(debit_val).replace('$', '').replace(',', '').replace('(', '').replace(')', '').strip()
                            try:
                                amount = abs(float(amount_str))
                                txn_type = 'debit'
                            except ValueError:
                                pass

                        # Check credit column if no debit
                        if amount is None and credit_val and str(credit_val).strip() and str(credit_val).strip() not in ['-', '', 'None']:
                            amount_str = str(credit_val).replace('$', '').replace(',', '').replace('(', '').replace(')', '').strip()
                            try:
                                amount = abs(float(amount_str))
                                txn_type = 'credit'
                            except ValueError:
                                pass

                        # Skip if we couldn't extract amount
                        if amount is None or txn_type is None:
                            continue

                        # Parse date
                        date_str = str(date_val).strip()
                        parsed_date = None

                        # Try common date formats
                        for date_format in ['%m/%d/%Y', '%m/%d/%y', '%Y-%m-%d', '%m-%d-%Y', '%m-%d-%y', '%m/%d', '%d/%m/%Y']:
                            try:
                                parsed_date = datetime.strptime(date_str, date_format)
                                # If year is missing, assume current year
                                if date_format in ['%m/%d']:
                                    parsed_date = parsed_date.replace(year=datetime.now().year)
                                break
                            except ValueError:
                                continue

                        if not parsed_date:
                            continue

                        # Parse balance if available
                        ending_balance = None
                        if balance_val and str(balance_val).strip() not in ['-', '', 'None']:
                            balance_str = str(balance_val).replace('$', '').replace(',', '').replace('(', '').replace(')', '').strip()
                            try:
                                ending_balance = float(balance_str)
                            except ValueError:
                                pass

                        # Create transaction dict
                        transaction = {
                            'date': parsed_date.strftime('%Y-%m-%d'),
                            'description': str(desc_val).strip(),
                            'amount': round(amount, 2),
                            'type': txn_type
                        }

                        if ending_balance is not None:
                            transaction['ending_balance'] = ending_balance

                        transactions.append(transaction)

        # Return success if we extracted at least some transactions
        return (transactions, len(transactions) > 0)

    except Exception as e:
        print(f"Deterministic extraction failed: {str(e)}", file=sys.stderr)
        return ([], False)


def validate_statement_summary(statement_summary: Dict, transactions: List[Dict], debug_log: str) -> Dict:
    """
    Validate the statement_summary balances to catch extraction errors.

    Common issues:
    1. Beginning balance extracted with wrong sign
    2. Ending balance doesn't match last transaction's ending_balance

    Returns corrected statement_summary or original if validation passes.
    """
    if not statement_summary:
        return statement_summary

    beginning_balance = statement_summary.get("beginning_balance")
    ending_balance = statement_summary.get("ending_balance")

    # Skip validation if we don't have balances to validate
    if beginning_balance is None:
        return statement_summary

    # Validation 1: Check if ending balance matches last transaction's ending_balance
    if ending_balance is not None and transactions:
        # Find last transaction with ending_balance
        last_txn_balance = None
        for txn in reversed(transactions):
            if "ending_balance" in txn and txn["ending_balance"] is not None:
                last_txn_balance = txn["ending_balance"]
                break

        if last_txn_balance is not None:
            # If signs don't match, there might be an error
            if (ending_balance < 0) != (last_txn_balance < 0):
                with open(debug_log, 'a') as f:
                    f.write("\n⚠️  WARNING: Ending balance sign mismatch!\n")
                    f.write(f"  Statement summary ending: ${ending_balance:.2f}\n")
                    f.write(f"  Last transaction ending: ${last_txn_balance:.2f}\n")

    # Validation 2: Calculate expected ending balance from beginning + credits - debits
    credits_total = sum(t["amount"] for t in transactions if t.get("type") == "credit")
    debits_total = sum(t["amount"] for t in transactions if t.get("type") == "debit")
    calculated_ending = beginning_balance + credits_total - debits_total

    with open(debug_log, 'a') as f:
        f.write("\n=== STATEMENT SUMMARY VALIDATION ===\n")
        f.write(f"Beginning Balance: ${beginning_balance:.2f}\n")
        f.write(f"Total Credits: ${credits_total:.2f}\n")
        f.write(f"Total Debits: ${debits_total:.2f}\n")
        f.write(f"Calculated Ending: ${calculated_ending:.2f}\n")
        if ending_balance is not None:
            f.write(f"Stated Ending: ${ending_balance:.2f}\n")
            difference = abs(calculated_ending - ending_balance)
            f.write(f"Difference: ${difference:.2f}\n")

            # If difference is significant and beginning balance sign is suspect, flag it
            if difference > 100 and beginning_balance < 0:
                f.write("\n⚠️  WARNING: Large discrepancy detected!\n")
                f.write("   Beginning balance may have wrong sign (negative when should be positive)\n")
                f.write(f"   Try flipping sign: ${-beginning_balance:.2f}\n")

                # Recalculate with flipped sign
                flipped_ending = -beginning_balance + credits_total - debits_total
                flipped_difference = abs(flipped_ending - ending_balance) if ending_balance else None

                if flipped_difference and flipped_difference < difference:
                    f.write(f"   ✓ Flipped calculation is closer: ${flipped_ending:.2f}\n")
                    f.write(f"   ✓ New difference: ${flipped_difference:.2f}\n")
                    f.write("   → AUTO-CORRECTING beginning_balance sign\n")
                    statement_summary["beginning_balance"] = -beginning_balance

    return statement_summary


def call_claude_with_retry(client, model, max_tokens, temperature, system, messages, max_retries=5, allow_fallback=False):
    """
    Call Claude API with exponential backoff retry logic.
    Retries on 529 (overloaded) and 500 (internal error) errors with increasing delays.
    If allow_fallback=True and model is Haiku, will fallback to Sonnet after all retries fail.
    """
    current_model = model

    for attempt in range(max_retries):
        try:
            response = client.messages.create(
                model=current_model,
                max_tokens=max_tokens,
                temperature=temperature,
                system=system,
                messages=messages
            )
            return response
        except Exception as e:
            error_str = str(e)
            # Check if it's a retryable error (529 overloaded, 500 internal error, or 503 service unavailable)
            is_retryable = any(code in error_str for code in ['529', '500', '503']) or \
                          any(keyword in error_str.lower() for keyword in ['overloaded', 'internal server error', 'service unavailable'])

            if is_retryable:
                if attempt < max_retries - 1:
                    # Exponential backoff: 5s, 10s, 20s, 40s, 80s
                    wait_time = 5 * (2 ** attempt)
                    error_type = "API Error" if '500' in error_str else "API Overloaded" if '529' in error_str else "Service Unavailable"
                    print(f"⚠️  {error_type} (attempt {attempt + 1}/{max_retries}), retrying in {wait_time}s...", file=sys.stderr)
                    time.sleep(wait_time)
                    continue
                else:
                    # All retries exhausted - try fallback if enabled and model is Haiku
                    if allow_fallback and current_model == "claude-haiku-4-5":
                        print(f"❌ Haiku unavailable after {max_retries} attempts", file=sys.stderr)
                        print(f"🔄 Falling back to Sonnet 4.5 for this statement...", file=sys.stderr)
                        current_model = "claude-sonnet-4-5"
                        # Reset attempt counter for fallback model (give it one try)
                        try:
                            response = client.messages.create(
                                model=current_model,
                                max_tokens=max_tokens,
                                temperature=temperature,
                                system=system,
                                messages=messages
                            )
                            print(f"✅ Fallback to Sonnet successful!", file=sys.stderr)
                            return response
                        except Exception as fallback_error:
                            print(f"❌ Sonnet fallback also failed: {str(fallback_error)}", file=sys.stderr)
                            raise
                    else:
                        print(f"❌ API still unavailable after {max_retries} attempts", file=sys.stderr)
                        raise
            else:
                # For non-retryable errors, raise immediately
                raise


def extract_transactions_with_ai(text: str, api_key: str, model: str, corrections: List[Dict] = None) -> Tuple[List[Dict], Dict, Dict]:
    # Increase timeout to 20 minutes for large statements with 200+ transactions
    client = Anthropic(api_key=api_key, timeout=1200.0)  # 20 minutes in seconds
    current_year = datetime.now().year

    # Track original model for potential fallback
    original_model = model
    fallback_attempted = False

    # Build corrections section for prompt
    corrections_prompt = build_corrections_prompt(corrections) if corrections else ""

    prompt = f"""
You are an expert bank statement parser. Extract ALL transactions accurately from ANY bank.

CRITICAL EXTRACTION RULES:
- Extract EVERY SINGLE transaction - do NOT skip any
- INCLUDE DUPLICATES: If the same vendor appears multiple times, extract EACH occurrence separately
- Same amount from same vendor on same day = STILL extract as separate transactions
- Each date can have MULTIPLE transactions - extract ALL of them
- Standardize dates to YYYY-MM-DD format (assume year {current_year} if not specified)
- Do NOT deduplicate - the statement shows what actually happened
- Do NOT include Beginning/Ending balance summary lines as transactions

TABLE FORMAT HANDLING (VERY IMPORTANT):
- Some statements show checks in MULTI-COLUMN TABLES (2 or 3 columns per row)
- CAREFULLY read across EACH ROW - each column is a SEPARATE transaction
- Example: "11/10 14381 12.92  11/17 14390 276.00  11/24 14400 496.00" = 3 SEPARATE checks
- Do NOT skip checks just because they're in a compact table format
- Extract EVERY check number you see, even if they appear side-by-side in tables

AMOUNT PARSING - CRITICAL:
- Amounts with commas (1,368.47) are COMPLETE amounts - do NOT extract only the first digit
- When you see "1,368.47" the amount is ONE THOUSAND THREE HUNDRED SIXTY EIGHT dollars, NOT $1
- ALWAYS capture the FULL amount including all digits after commas
- Examples: "1,368.47" = 1368.47, "2,500.00" = 2500.00, "15,234.56" = 15234.56
- Return amounts as plain numbers WITHOUT commas or dollar signs in the JSON

🚨 CLASSIFICATION - READ FIRST 🚨

STEP 1: Analyze the statement format
- Does it have section headers? ("CREDITS", "DEBITS", "DEPOSITS", "WITHDRAWALS")
- OR does it have a table with separate columns? (Debits column, Credits column)

STEP 2: Classify based on structure ONLY

IF SECTION-BASED (has section headers):
- ALL transactions under "CREDITS" or "DEPOSITS" section → type: "credit"
- ALL transactions under "DEBITS" or "WITHDRAWALS" section → type: "debit"

IF COLUMN-BASED (table format):
- Find the column headers: "Debits", "Withdrawals", "Credits", "Deposits"
- For each transaction row, check which column has the dollar amount
- Amount in Debits/Withdrawals column → type: "debit"
- Amount in Credits/Deposits column → type: "credit"

CRITICAL: Description text is IRRELEVANT. ONLY use section or column position.

CARD PURCHASES & CHECKCARD TRANSACTIONS (EXTREMELY IMPORTANT):
- Bank statements often contain MANY card purchase transactions spread across multiple pages
- PURCHASE transactions format: "MM/DD/YY PURCHASE DDDD MERCHANT_NAME LOCATION -AMOUNT"
  Example: "09/08/25 PURCHASE 0905 COMCAST / XFINITY 800-266-2278 MD -259.95"
- CHECKCARD transactions format: "MM/DD/YY CHECKCARD DDDD MERCHANT_NAME LOCATION -AMOUNT"
  Example: "09/22/25 CHECKCARD 0920 ADVANCE AUTO PARTS #269 HARRISONBURG VA -90.00"
- These are ALWAYS DEBITS (money spent on purchases)
- EXTRACT EVERY SINGLE ONE - do not skip any card purchases
- Card purchases may appear in the middle of other transactions
- Multiple card purchases on the same date to the same merchant = extract EACH separately
- Even small amounts like $1.99, $5.00 must be extracted
- Look for patterns: "PURCHASE 09", "CHECKCARD 09", "PURCHASE 0905", etc.

Example (Section-Based):
```
CREDITS
  12/01  Transaction A  100.00 → type: "credit"
  12/02  Transaction B  200.00 → type: "credit"
DEBITS
  12/03  Transaction C  50.00 → type: "debit"
```

Example (Column-Based):
```
| Date  | Description    | Withdrawals | Deposits | Balance |
| 12/01 | Transaction A  | -           | 100.00   | 1100.00 | → type: "credit"
| 12/02 | Transaction B  | 50.00       | -        | 1050.00 | → type: "debit"
```

NEVER use description words to determine type. ONLY structure (section or column).

{corrections_prompt}
BALANCE EXTRACTION (IMPORTANT FOR ACCURACY):
- Many statements show a running balance or ending balance column
- If the statement has a balance column, extract the balance value for EACH transaction
- The balance shown is typically the account balance AFTER that transaction posted
- If no balance column exists, omit the balance field (don't guess or calculate)
- Balance format examples: "$1,234.56" → 1234.56, "($500.00)" → -500.00 (negative)
- Negative balances are shown in parentheses: "(1,234.56)" = -1234.56

STATEMENT SUMMARY EXTRACTION (CRITICAL - HIGHEST PRIORITY):
This is the MOST IMPORTANT part of extraction - get these balances correct!

WHERE TO LOOK:
1. FIRST: Check the top of the first page for "Account Summary" or "Statement Summary" section
2. SECOND: Check the bottom of the last page for summary totals
3. THIRD: Look for clearly labeled summary lines anywhere in the statement

WHAT TO EXTRACT:
- "Beginning Balance", "Opening Balance", "Previous Balance", or "Starting Balance"
- "Ending Balance", "Closing Balance", "New Balance", or "Current Balance"
- These are SUMMARY LINES, NOT individual transactions
- They are usually in a box, table, or clearly separated section

FORMAT EXAMPLES:
  "Beginning Balance: $1,234.56" → 1234.56 (positive)
  "Previous Balance.............$1,234.56" → 1234.56 (positive)
  "Opening Balance    ($1,234.56)" → -1234.56 (negative - parentheses mean negative)
  "Starting Balance    $1,234.56 CR" → 1234.56 (positive - CR means credit/positive)
  "Beginning Balance    $1,234.56 DR" → -1234.56 (negative - DR means debit/negative)

SIGN VALIDATION (VERY IMPORTANT):
- Parentheses () around a balance = NEGATIVE
- No parentheses and positive number = POSITIVE
- "CR" suffix = POSITIVE (credit balance)
- "DR" suffix = NEGATIVE (debit balance)
- Most checking accounts have POSITIVE beginning balances
- If you see a balance without parentheses and no DR suffix, it is POSITIVE

EXAMPLES OF CORRECT EXTRACTION:
  "$5,000.00" → 5000.00 (positive)
  "($500.00)" → -500.00 (negative - has parentheses)
  "$2,000 CR" → 2000.00 (positive - CR means credit)
  "$2,000 DR" → -2000.00 (negative - DR means debit)

- Return these as separate fields in the JSON output (not as transactions)

OUTPUT FORMAT - Return ONLY valid JSON:
{{
  "statement_summary": {{
    "beginning_balance": 1234.56,
    "ending_balance": 5678.90
  }},
  "transactions": [
    {{"date": "YYYY-MM-DD", "description": "description text", "amount": 123.45, "type": "credit", "ending_balance": 1234.56}}
  ]
}}

- amount: POSITIVE number only
- type: exactly "credit" or "debit"
- ending_balance: (OPTIONAL) Account balance after this transaction (can be negative)
- statement_summary: (OPTIONAL) If the statement shows summary balances, include them here
- statement_summary.beginning_balance: Opening/beginning/previous balance (can be negative)
- statement_summary.ending_balance: Closing/ending/new balance (can be negative)
"""

    # Debug: log input text length
    debug_log = "/var/www/html/crmfinity_laravel/storage/logs/openai_debug.log"

    # Estimate tokens and check if we need to chunk
    estimated_tokens = estimate_tokens(text)
    # Account for prompt tokens (roughly 2000) and leave room for response (16000)
    # Model limit is 128k, so we use much more conservative limit for input text
    # The prompt is ~2k tokens, so we can use up to 110k for input text safely
    # However, to be extra safe and handle token estimation errors, we use 25k per chunk
    max_input_tokens = 25000

    with open(debug_log, 'w') as f:
        f.write(f"=== INPUT ===\n")
        f.write(f"Text length: {len(text)} characters\n")
        f.write(f"Estimated tokens: {estimated_tokens}\n")
        f.write(f"Model: {model}\n")
        f.write(f"Chunking required: {estimated_tokens > max_input_tokens}\n\n")

    # If text is too large, chunk it
    if estimated_tokens > max_input_tokens:
        chunks = chunk_text(text, max_tokens=max_input_tokens)
        with open(debug_log, 'a') as f:
            f.write(f"=== CHUNKING ===\n")
            f.write(f"Split into {len(chunks)} chunks\n")
            for i, chunk in enumerate(chunks):
                f.write(f"Chunk {i+1}: {len(chunk)} chars, ~{estimate_tokens(chunk)} tokens\n")
            f.write("\n")

        all_transactions = []
        statement_summary = None
        total_usage = {
            "prompt_tokens": 0,
            "completion_tokens": 0,
            "total_tokens": 0,
            "model": model
        }

        # Process each chunk
        for i, chunk in enumerate(chunks):
            with open(debug_log, 'a') as f:
                f.write(f"=== PROCESSING CHUNK {i+1}/{len(chunks)} ===\n")

            response = call_claude_with_retry(
                client=client,
                model=model,
                max_tokens=32000,  # Increased from 16000 to handle statements with 200+ transactions
                temperature=0,
                system=prompt,
                messages=[
                    {"role": "user", "content": f"Bank Statement Text (Part {i+1} of {len(chunks)}):\n\n{chunk}"}
                ],
                allow_fallback=True  # Allow fallback to Sonnet if Haiku fails
            )

            result = response.content[0].text

            # Accumulate usage stats
            total_usage["prompt_tokens"] += response.usage.input_tokens
            total_usage["completion_tokens"] += response.usage.output_tokens
            total_usage["total_tokens"] += response.usage.input_tokens + response.usage.output_tokens

            with open(debug_log, 'a') as f:
                f.write(f"Response length: {len(result)} characters\n")
                f.write(f"Tokens - Input: {response.usage.input_tokens}, Output: {response.usage.output_tokens}\n\n")

            # Parse the chunk result
            chunk_data = parse_json_response(result)
            if chunk_data and "transactions" in chunk_data:
                all_transactions.extend(chunk_data["transactions"])
            # Extract statement summary if it appears in any chunk (usually first chunk)
            if chunk_data and "statement_summary" in chunk_data and statement_summary is None:
                statement_summary = chunk_data["statement_summary"]

        # Deduplicate transactions that might appear in overlapping regions
        transactions = deduplicate_transactions(all_transactions)
        usage = total_usage

        with open(debug_log, 'a') as f:
            f.write(f"=== CHUNK PROCESSING COMPLETE ===\n")
            f.write(f"Total transactions extracted: {len(all_transactions)}\n")
            f.write(f"After deduplication: {len(transactions)}\n")
            f.write(f"Total tokens used: {usage['total_tokens']}\n\n")
    else:
        # Single request for small PDFs
        response = call_claude_with_retry(
            client=client,
            model=model,
            max_tokens=32000,  # Increased from 16000 to handle statements with 200+ transactions
            temperature=0,
            system=prompt,
            messages=[
                {"role": "user", "content": f"Bank Statement Text:\n\n{text}"}
            ],
            allow_fallback=True  # Allow fallback to Sonnet if Haiku fails
        )

        result = response.content[0].text

        usage = {
            "prompt_tokens": response.usage.input_tokens,
            "completion_tokens": response.usage.output_tokens,
            "total_tokens": response.usage.input_tokens + response.usage.output_tokens,
            "model": model
        }

        # Debug: log response info
        with open(debug_log, 'a') as f:
            f.write(f"=== RESPONSE ===\n")
            f.write(f"Response length: {len(result)} characters\n")
            f.write(f"Stop reason: {response.stop_reason}\n")
            f.write(f"Tokens - Input: {response.usage.input_tokens}, Output: {response.usage.output_tokens}\n\n")
            f.write(f"=== RAW CLAUDE RESPONSE (first 2000 chars) ===\n")
            f.write(result[:2000])
            f.write(f"\n\n=== END RAW RESPONSE ===\n\n")

        # Parse the response
        chunk_data = parse_json_response(result)

        # Debug: log what we got from Claude
        print(f"DEBUG: chunk_data type: {type(chunk_data)}", file=sys.stderr)
        print(f"DEBUG: chunk_data keys: {chunk_data.keys() if chunk_data else 'None'}", file=sys.stderr)
        if chunk_data and "transactions" in chunk_data:
            print(f"DEBUG: transactions count from Claude: {len(chunk_data['transactions'])}", file=sys.stderr)
        else:
            print(f"DEBUG: No transactions key in chunk_data!", file=sys.stderr)

        transactions = chunk_data.get("transactions", []) if chunk_data else []

        # Extract statement summary if available (for non-chunked responses)
        statement_summary = None
        if chunk_data and "statement_summary" in chunk_data:
            statement_summary = chunk_data["statement_summary"]

    # Validate and clean transactions - ensure all required fields exist
    validated = []
    for txn in transactions:
        if not isinstance(txn, dict):
            continue
        # Ensure required fields with defaults
        # Use safe_float_amount to handle commas and dollar signs
        transaction_dict = {
            "date": txn.get("date", ""),
            "description": txn.get("description", "Unknown"),
            "amount": safe_float_amount(txn.get("amount", 0)),
            "type": txn.get("type", "debit")  # Default to debit if missing
        }

        # Include ending_balance if available
        if "ending_balance" in txn and txn["ending_balance"] is not None:
            transaction_dict["ending_balance"] = safe_float_amount(txn["ending_balance"])

        validated.append(transaction_dict)

    # Debug log to see what was extracted
    debug_log = "/var/www/html/crmfinity_laravel/storage/logs/openai_debug.log"
    with open(debug_log, 'a') as f:
        f.write("=== OpenAI Extraction Results ===\n")
        credits = [t for t in validated if t['type'] == 'credit']
        debits = [t for t in validated if t['type'] == 'debit']
        f.write(f"Total: {len(validated)} transactions\n")
        f.write(f"Credits: {len(credits)}, Debits: {len(debits)}\n\n")
        f.write("=== CREDITS ===\n")
        for t in credits:
            f.write(f"  {t['date']} | ${t['amount']:.2f} | {t['description'][:50]}\n")
        f.write("\n=== DEBITS ===\n")
        for t in debits:
            f.write(f"  {t['date']} | ${t['amount']:.2f} | {t['description'][:50]}\n")
        f.write(f"\nCredit Total: ${sum(t['amount'] for t in credits):.2f}\n")
        f.write(f"Debit Total: ${sum(t['amount'] for t in debits):.2f}\n")

    # Validate statement_summary balances
    if statement_summary and validated:
        statement_summary = validate_statement_summary(statement_summary, validated, debug_log)

    return validated, usage, statement_summary


def calculate_api_cost(usage: Dict) -> Dict:
    model = usage.get("model", "claude-opus-4-6")
    pricing = PRICING.get(model, PRICING["claude-opus-4-6"])

    input_cost = (usage["prompt_tokens"] / 1_000_000) * pricing["input"]
    output_cost = (usage["completion_tokens"] / 1_000_000) * pricing["output"]
    total_cost = input_cost + output_cost

    return {
        "input_tokens": usage["prompt_tokens"],
        "output_tokens": usage["completion_tokens"],
        "total_tokens": usage["total_tokens"],
        "input_cost": round(input_cost, 4),
        "output_cost": round(output_cost, 4),
        "total_cost": round(total_cost, 4),
        "model": model
    }


def calculate_summary(transactions: List[Dict]) -> Dict:
    credit_transactions = [t for t in transactions if t.get("type") == "credit"]
    debit_transactions = [t for t in transactions if t.get("type") == "debit"]

    credit_total = sum(safe_float_amount(t.get("amount", 0)) for t in credit_transactions)
    debit_total = sum(safe_float_amount(t.get("amount", 0)) for t in debit_transactions)

    return {
        "credit_count": len(credit_transactions),
        "debit_count": len(debit_transactions),
        "credit_total": round(credit_total, 2),
        "debit_total": round(debit_total, 2),
        "net_balance": round(credit_total - debit_total, 2),
        "total_transactions": len(transactions)
    }


# Known MCA lenders and their common transaction patterns
MCA_LENDERS = {
    "ondeck": {"name": "OnDeck Capital", "patterns": ["ondeck", "on deck"]},
    "kabbage": {"name": "Kabbage", "patterns": ["kabbage"]},
    "fundbox": {"name": "Fundbox", "patterns": ["fundbox"]},
    "bluevine": {"name": "BlueVine", "patterns": ["bluevine", "blue vine"]},
    "credibly": {"name": "Credibly", "patterns": ["credibly"]},
    "rapid_finance": {"name": "Rapid Finance", "patterns": ["rapid finance", "rapidfinance"]},
    "can_capital": {"name": "CAN Capital", "patterns": ["can capital", "cancapital"]},
    "yellowstone": {"name": "Yellowstone Capital", "patterns": ["yellowstone"]},
    "pearl_capital": {"name": "Pearl Capital", "patterns": ["pearl capital", "pearlcapital"]},
    "square_capital": {"name": "Square Capital", "patterns": ["square capital", "sq capital"]},
    "paypal_working": {"name": "PayPal Working Capital", "patterns": ["paypal working", "paypal wc"]},
    "shopify_capital": {"name": "Shopify Capital", "patterns": ["shopify capital"]},
    "clearco": {"name": "Clearco", "patterns": ["clearco", "clearbanc"]},
    "merchant_cash": {"name": "Merchant Cash Advance", "patterns": ["merchant cash", "mca payment", "mca pmt"]},
    "bizfi": {"name": "BizFi", "patterns": ["bizfi", "biz fi"]},
    "forward_financing": {"name": "Forward Financing", "patterns": ["forward financing", "forwardfinancing"]},
    "lendingtree": {"name": "LendingTree MCA", "patterns": ["lendingtree mca"]},
    "fundkite": {"name": "Fundkite", "patterns": ["fundkite"]},
    "libertas": {"name": "Libertas Funding", "patterns": ["libertas"]},
    "national_funding": {"name": "National Funding", "patterns": ["national funding"]},
    "kapitus": {"name": "Kapitus", "patterns": ["kapitus", "strategic funding"]},
    "lendio": {"name": "Lendio", "patterns": ["lendio"]},
    "fora_financial": {"name": "Fora Financial", "patterns": ["fora financial", "forafinancial"]},
    "reliant_funding": {"name": "Reliant Funding", "patterns": ["reliant funding", "reliantfunding"]},
    "greenbox": {"name": "Greenbox Capital", "patterns": ["greenbox"]},
    "bizfund": {"name": "BizFund", "patterns": ["bizfund"]},
    "behalf": {"name": "Behalf", "patterns": ["behalf inc", "behalf pay"]},
    "payability": {"name": "Payability", "patterns": ["payability"]},
    "newco_capital": {"name": "Newco Capital", "patterns": ["newco capital", "newcocapital"]},
    "united_capital": {"name": "United Capital Source", "patterns": ["united capital source", "ucs funding"]},
    "quickbridge": {"name": "QuickBridge", "patterns": ["quickbridge", "quick bridge"]},
    "mantis_funding": {"name": "Mantis Funding", "patterns": ["mantis funding"]},
    "cfgms": {"name": "CFGMS", "patterns": ["cfgms"]},
    "credova": {"name": "Credova", "patterns": ["credova"]},
    "liquidityaccess": {"name": "Liquidity Access", "patterns": ["liquidityaccess", "liquidity access"]},
    "m2_equipment": {"name": "M2 Equipment Finance", "patterns": ["m2 equipment"]},
}


def detect_mca_payments(transactions: List[Dict]) -> Dict:
    """
    Detect MCA (Merchant Cash Advance) payments in transactions.
    Returns summary of detected MCAs with payment frequency analysis.
    """
    mca_payments = {}  # Keyed by lender identifier

    for txn in transactions:
        if txn.get("type") != "debit":
            continue

        description = txn.get("description", "").lower()
        amount = safe_float_amount(txn.get("amount", 0))
        date = txn.get("date", "")

        # Check against known MCA lenders
        for lender_id, lender_info in MCA_LENDERS.items():
            for pattern in lender_info["patterns"]:
                if pattern in description:
                    if lender_id not in mca_payments:
                        mca_payments[lender_id] = {
                            "lender_name": lender_info["name"],
                            "payments": [],
                            "total_amount": 0,
                            "payment_count": 0,
                            "amounts": set(),
                        }

                    mca_payments[lender_id]["payments"].append({
                        "date": date,
                        "amount": amount,
                        "description": txn.get("description", "")
                    })
                    mca_payments[lender_id]["total_amount"] += amount
                    mca_payments[lender_id]["payment_count"] += 1
                    mca_payments[lender_id]["amounts"].add(amount)

                    # Mark the transaction as MCA
                    txn["is_mca_payment"] = True
                    txn["mca_lender"] = lender_info["name"]
                    break

    # Calculate frequency and format results
    mca_summary = {
        "total_mca_count": len(mca_payments),
        "total_mca_payments": sum(m["payment_count"] for m in mca_payments.values()),
        "total_mca_amount": round(sum(m["total_amount"] for m in mca_payments.values()), 2),
        "lenders": []
    }

    for lender_id, data in mca_payments.items():
        payments = sorted(data["payments"], key=lambda x: x["date"])

        # Calculate payment frequency
        frequency = "unknown"
        avg_amount = data["total_amount"] / data["payment_count"] if data["payment_count"] > 0 else 0

        if len(payments) >= 2:
            # Parse dates and calculate average days between payments
            dates = []
            for p in payments:
                try:
                    dates.append(datetime.strptime(p["date"], "%Y-%m-%d"))
                except:
                    pass

            if len(dates) >= 2:
                dates.sort()
                intervals = [(dates[i+1] - dates[i]).days for i in range(len(dates)-1)]
                avg_interval = sum(intervals) / len(intervals) if intervals else 0

                # Determine frequency based on average interval
                if avg_interval <= 1.5:
                    frequency = "daily"
                elif avg_interval <= 3:
                    frequency = "every_other_day"
                elif avg_interval <= 5.5:
                    frequency = "twice_weekly"
                elif avg_interval <= 8:
                    frequency = "weekly"
                elif avg_interval <= 16:
                    frequency = "bi_weekly"
                elif avg_interval <= 35:
                    frequency = "monthly"
                else:
                    frequency = "irregular"
        elif len(payments) == 1:
            frequency = "single_payment"

        # Convert amounts set to list for JSON serialization
        unique_amounts = sorted(list(data["amounts"]))

        lender_summary = {
            "lender_id": lender_id,
            "lender_name": data["lender_name"],
            "payment_count": data["payment_count"],
            "total_amount": round(data["total_amount"], 2),
            "average_payment": round(avg_amount, 2),
            "unique_amounts": [round(a, 2) for a in unique_amounts],
            "frequency": frequency,
            "frequency_label": {
                "daily": "Daily",
                "every_other_day": "Every Other Day",
                "twice_weekly": "Twice Weekly",
                "weekly": "Weekly",
                "bi_weekly": "Bi-Weekly",
                "monthly": "Monthly",
                "irregular": "Irregular",
                "single_payment": "Single Payment",
                "unknown": "Unknown"
            }.get(frequency, frequency),
            "first_payment": payments[0] if payments else None,
            "last_payment": payments[-1] if payments else None,
        }

        mca_summary["lenders"].append(lender_summary)

    # Sort lenders by total amount (highest first)
    mca_summary["lenders"].sort(key=lambda x: x["total_amount"], reverse=True)

    return mca_summary


def main():
    # Parse command line arguments
    if len(sys.argv) < 3:
        print(json.dumps({
            "success": False,
            "error": "Usage: python3 bank_statement_extractor.py <pdf_path> <api_key> [model] [corrections_json]"
        }))
        sys.exit(1)

    pdf_path = sys.argv[1]
    api_key = sys.argv[2]
    model = sys.argv[3] if len(sys.argv) > 3 else "claude-haiku-4-5"  # Default to most cost-effective model

    # Parse corrections JSON if provided
    corrections = []
    if len(sys.argv) > 4:
        try:
            corrections = json.loads(sys.argv[4])
        except json.JSONDecodeError:
            corrections = []

    try:
        # Extract text from PDF
        pdf_text, pages = extract_text_from_pdf(pdf_path)

        # Extract expected totals from statement header for validation
        # expected_totals = extract_statement_totals(pdf_text)

        # Pre-process check tables to extract individual check transactions
        # TEMPORARILY DISABLED - causing issues with PNC format
        # pdf_text = preprocess_check_tables(pdf_text)

        # TRY DETERMINISTIC EXTRACTION FIRST (Option 1 - programmatic table parsing)
        print("Attempting deterministic table extraction...", file=sys.stderr)
        transactions_det, det_success = extract_transactions_deterministic(pdf_path)

        if det_success and len(transactions_det) > 0:
            # Deterministic extraction succeeded - use these results
            print(f"✓ Deterministic extraction successful: {len(transactions_det)} transactions", file=sys.stderr)
            transactions = transactions_det
            usage = {"input_tokens": 0, "output_tokens": 0}  # No AI usage
            extraction_method = "deterministic"
            statement_summary = None  # Deterministic extraction doesn't extract summary
        else:
            # Fallback to AI extraction
            print("Deterministic extraction failed or returned no transactions. Falling back to AI extraction...", file=sys.stderr)
            transactions, usage, statement_summary = extract_transactions_with_ai(pdf_text, api_key, model, corrections)
            extraction_method = "ai"

        # Apply corrections post-processing (double-check) - only for AI extraction
        if corrections and extraction_method == "ai":
            transactions = apply_corrections(transactions, corrections)
            corrections_applied = len([t for t in transactions if t.get('corrected_by_learning')])
        else:
            corrections_applied = 0

        # Calculate costs and summary
        api_cost = calculate_api_cost(usage)
        summary = calculate_summary(transactions)

        # Detect MCA payments
        mca_summary = detect_mca_payments(transactions)

        # Validate extracted totals against statement header
        validation_warnings = []

        # expected_credits = expected_totals.get('total_credits') or expected_totals.get('total_deposits')
        # expected_debits = expected_totals.get('total_debits') or expected_totals.get('total_withdrawals')
        expected_credits = None
        expected_debits = None

        if expected_credits:
            credit_diff = abs(expected_credits - summary['credit_total'])
            if credit_diff > 1.00:  # Allow $1 rounding tolerance
                validation_warnings.append({
                    "type": "credit_mismatch",
                    "severity": "error" if credit_diff > 100 else "warning",
                    "message": f"Credits mismatch: Expected ${expected_credits:,.2f} from statement header, but extracted ${summary['credit_total']:,.2f}",
                    "difference": round(credit_diff, 2),
                    "expected": round(expected_credits, 2),
                    "extracted": round(summary['credit_total'], 2),
                    "suggestion": "Some credit transactions may be misclassified as debits. Review transactions in the 'Deposits/Credits' section."
                })

        if expected_debits:
            debit_diff = abs(expected_debits - summary['debit_total'])
            if debit_diff > 1.00:  # Allow $1 rounding tolerance
                validation_warnings.append({
                    "type": "debit_mismatch",
                    "severity": "error" if debit_diff > 100 else "warning",
                    "message": f"Debits mismatch: Expected ${expected_debits:,.2f} from statement header, but extracted ${summary['debit_total']:,.2f}",
                    "difference": round(debit_diff, 2),
                    "expected": round(expected_debits, 2),
                    "extracted": round(summary['debit_total'], 2),
                    "suggestion": "Some debit transactions may be misclassified as credits. Review transactions in the 'Withdrawals/Debits' section."
                })

        # Output JSON result
        result = {
            "success": True,
            "summary": summary,
            "api_cost": api_cost,
            "transactions": transactions,
            "mca_analysis": mca_summary,
            "statement_summary": statement_summary,  # Beginning/ending balance from statement
            "validation": {
                "expected_credits": round(expected_credits, 2) if expected_credits else None,
                "expected_debits": round(expected_debits, 2) if expected_debits else None,
                "warnings": validation_warnings,
                "has_warnings": len(validation_warnings) > 0
            },
            "metadata": {
                "pdf_file": os.path.basename(pdf_path),
                "pages": pages,
                "extraction_date": datetime.now().isoformat(),
                "extraction_method": extraction_method,
                "model_used": model if extraction_method == "ai" else None,
                "characters_extracted": len(pdf_text),
                "corrections_available": len(corrections),
                "corrections_applied": corrections_applied
            }
        }

        print(json.dumps(result))

    except FileNotFoundError as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()
