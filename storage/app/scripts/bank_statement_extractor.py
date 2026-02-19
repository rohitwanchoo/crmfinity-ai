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
from datetime import datetime, timedelta
from anthropic import Anthropic
from typing import List, Dict, Tuple, Optional
from collections import defaultdict
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


# ─────────────────────────────────────────────────────────────────────────────
# DETERMINISTIC EXTRACTION — SHARED CONSTANTS & HELPERS
# ─────────────────────────────────────────────────────────────────────────────

# Section marker keywords (Chase/JPMorgan embed *start*name in PDF text)
_CREDIT_SECTION_MARKERS = {
    'deposits and additions', 'deposits and credits',
    'other credits', 'credits',
}
_DEBIT_SECTION_MARKERS = {
    'atm debit withdrawal', 'atm & debit card withdrawal',
    'atm and debit card withdrawal', 'electronic withdrawal',
    'checks paid', 'fees section', 'fees', 'service charges',
    'other withdrawals', 'withdrawals and debits', 'withdrawals', 'debits',
}
_NON_TXN_SECTION_MARKERS = {
    'summary', 'post summary', 'message', 'daily ending balance',
    'disclosure', 'atm and debit card summary', 'atm & debit card summary',
}

# Section header patterns for Strategy 2 (full-line section headers, no markers)
# Each tuple: (compiled regex, section_type, section_name)
# section_type = 'credit' | 'debit' | None (None = stop parsing, e.g. balance table)
# section_name is a short slug used internally (e.g. 'checks' triggers check-row parsing)
_SECTION_HEADER_PATTERNS = [
    # Credits
    (re.compile(r'^\s*DEPOSITS?\s+AND\s+ADDITIONS?\s*$', re.I), 'credit', 'deposits'),
    (re.compile(r'^\s*DEPOSITS?\s+AND\s+CREDITS?\s*$', re.I), 'credit', 'deposits'),
    (re.compile(r'^\s*DEPOSITS?\s+&\s+CREDITS?\s*$', re.I), 'credit', 'deposits'),
    (re.compile(r'^\s*OTHER\s+CREDITS?\s*$', re.I), 'credit', 'credits'),
    (re.compile(r'^\s*CREDITS?\s*$', re.I), 'credit', 'credits'),
    (re.compile(r'^\s*DEPOSITS?\s*$', re.I), 'credit', 'deposits'),
    # Debits — ATM/debit card (matches both "Withdrawals" and "Transactions" variants)
    (re.compile(r'^\s*ATM\s*[&AND]+\s*DEBIT\s+CARD\s+(WITHDRAWALS?|TRANSACTIONS?)\s*$', re.I), 'debit', 'atm'),
    (re.compile(r'^\s*ELECTRONIC\s+WITHDRAWALS?\s*$', re.I), 'debit', 'electronic'),
    # Checks — "Checks Paid" header and JPMorgan "Check Date" column-header both trigger check parsing
    (re.compile(r'^\s*CHECKS?\s+PAID\s*$', re.I), 'debit', 'checks'),
    (re.compile(r'^\s*CHECK\s+DATE\s*$', re.I), 'debit', 'checks'),
    # ACH/wire payments (JPMorgan Classic "Payments & Transfers" section)
    (re.compile(r'^\s*PAYMENTS?\s+[&AND]+\s+TRANSFERS?\s*$', re.I), 'debit', 'payments'),
    # Fees
    (re.compile(r'^\s*FEES?,\s*CHARGES?\s*[&AND]+\s*OTHER\s+WITHDRAWALS?\s*$', re.I), 'debit', 'fees'),
    (re.compile(r'^\s*FEES?\s*$', re.I), 'debit', 'fees'),
    (re.compile(r'^\s*SERVICE\s+CHARGES?\s*$', re.I), 'debit', 'fees'),
    # Other debit sections
    (re.compile(r'^\s*OTHER\s+WITHDRAWALS?\s*$', re.I), 'debit', 'withdrawals'),
    (re.compile(r'^\s*WITHDRAWALS?\s+AND\s+DEBITS?\s*$', re.I), 'debit', 'withdrawals'),
    (re.compile(r'^\s*DEBITS?\s*$', re.I), 'debit', 'debits'),
    (re.compile(r'^\s*WITHDRAWALS?\s*$', re.I), 'debit', 'withdrawals'),
    # Non-transaction sections — stop parsing until next real section
    (re.compile(r'^\s*DAILY\s+ENDING\s+BALANCE\s*$', re.I), None, 'balance'),
    (re.compile(r'^\s*ACCOUNT\s+ACTIVITY\s+SUMMARY\s*$', re.I), None, 'summary'),
]

# Month name → number mapping (for reconstructing dates from *end* lines)
_MONTH_NAMES = {
    'january': 1, 'february': 2, 'march': 3, 'april': 4,
    'may': 5, 'june': 6, 'july': 7, 'august': 8,
    'september': 9, 'october': 10, 'november': 11, 'december': 12,
}

# Skip *start* and *end* marker lines
_MARKER_LINE_RE = re.compile(r'^\s*\*(start|end)\*', re.I)

# Transaction line: starts with MM/DD or M/D, ends with optional $ and decimal amount
_TXN_LINE_RE = re.compile(r'^(\d{1,2}/\d{1,2})\s+(.+?)\s+\$?([\d,]+\.\d{2})\s*$')

# Embedded transaction in *end* marker lines — date is partial (/DD, month consumed by marker)
# e.g. "*end*deposit0s and additio1ns /08 Deposit 1680055706 878.00"
_END_EMBEDDED_TXN_RE = re.compile(r'\s/(\d{1,2})\s+(.+?)\s+\$?([\d,]+\.\d{2})\s*$')

# Check transaction format used in Chase "Checks Paid" section
# e.g. "148 ^ 01/20 $125.00"  →  CHECK_NO ^ DATE AMOUNT
# Also handles MM/DD/YY and MM/DD/YYYY date formats (e.g. "3301 01/02/25 1,000.00")
_CHECK_TXN_RE = re.compile(r'^(\d+)\s+\^?\s*(\d{1,2}/\d{1,2}(?:/\d{2,4})?)\s+\$?([\d,]+\.\d{2})\s*$')

# Multi-column check table pattern (JPMorgan/Chase compact "Checks Paid" table)
# e.g. "1068 11/24 450.00 1072 11/17 1,600.00 1077 11/19 2,558.83"
# Each column: CHECK_NO MM/DD[/YY] AMOUNT
_MULTI_CHECK_COL_RE = re.compile(r'(\d{3,6})\s+(\d{1,2}/\d{1,2}(?:/\d{2,4})?)\s+([\d,]+\.\d{2})')

# Date word pattern (M/D or MM/DD without year)
_DATE_WORD_RE = re.compile(r'^\d{1,2}/\d{1,2}$')

# Amount word pattern
_AMOUNT_WORD_RE = re.compile(r'^[\d,]+\.\d{2}$')


def _det_parse_amount(s: str) -> float:
    """Parse amount string to float, stripping commas and dollar signs."""
    return float(s.replace(',', '').replace('$', '').strip())


def _det_parse_date(date_str: str, year: int) -> Optional[str]:
    """Parse MM/DD, MM/DD/YY, or MM/DD/YYYY to YYYY-MM-DD string."""
    for fmt in ['%m/%d/%Y', '%m/%d/%y', '%m/%d']:
        try:
            dt = datetime.strptime(date_str.strip(), fmt)
            if fmt == '%m/%d':
                dt = dt.replace(year=year)
            return dt.strftime('%Y-%m-%d')
        except ValueError:
            continue
    return None


def _detect_year(text: str) -> int:
    """
    Extract the statement year from PDF text.
    Prioritises years embedded in slash-separated dates (M/D/YYYY or M/D/YY) because
    bank PDFs often contain distracting 4-digit numbers such as ZIP+4 codes (e.g.
    '43218-2051') that would otherwise be mistaken for a year.
    """
    counts: Dict[str, int] = {}

    # 1st priority: 4-digit years inside slash dates (01/30/2026, 1/1/2026)
    for y in re.findall(r'\b\d{1,2}/\d{1,2}/(20\d{2})\b', text):
        counts[y] = counts.get(y, 0) + 1
    if counts:
        return int(max(counts, key=counts.get))

    # 2nd priority: 2-digit years inside slash dates (1/30/26) → prefix with 20
    for y in re.findall(r'\b\d{1,2}/\d{1,2}/(\d{2})\b', text):
        full = str(2000 + int(y))
        counts[full] = counts.get(full, 0) + 1
    if counts:
        return int(max(counts, key=counts.get))

    # 3rd priority: standalone 4-digit years not preceded or followed by another digit
    # (avoids matching ZIP+4 extensions like '-2051' → the lookbehind excludes '-')
    for y in re.findall(r'(?<![/\-\d])\b(20\d{2})\b(?![/\-\d])', text):
        counts[y] = counts.get(y, 0) + 1
    if counts:
        return int(max(counts, key=counts.get))

    return datetime.now().year


# ─── Strategy 1: *start*/*end* section markers (Chase / JPMorgan) ────────────

def _extract_by_section_markers(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Strategy 1: Chase/JPMorgan PDFs embed *start*section-name and *end*section-name
    tags directly in the extracted text. These reliably identify credit vs debit sections.

    Also handles:
    - Transactions embedded in *end* marker lines (date's month is consumed by the marker
      text, leaving only /DD — reconstructed using the statement's primary month).
    - Chase "Checks Paid" section format: "CHECK_NO ^ DATE AMOUNT"
    """
    transactions: List[Dict] = []
    try:
        with pdfplumber.open(pdf_path) as pdf:
            if not pdf.pages:
                return [], False

            # Quick check — only proceed if *start* markers are present
            has_markers = any(
                '*start*' in (page.extract_text() or '').lower()
                for page in pdf.pages[:3]
            )
            if not has_markers:
                return [], False

            first_text = pdf.pages[0].extract_text() or ''
            statement_year = _detect_year(first_text)

            # Detect the statement's primary month for reconstructing partial dates
            month_match = re.search(
                r'\b(January|February|March|April|May|June|July|August|'
                r'September|October|November|December)\b', first_text, re.I
            )
            statement_month = (
                _MONTH_NAMES[month_match.group(1).lower()] if month_match
                else datetime.now().month
            )

            current_section_type: Optional[str] = None
            current_section_name: str = ''

            for page in pdf.pages:
                text = page.extract_text()
                if not text:
                    continue

                for line in text.split('\n'):
                    line = line.strip()
                    if not line:
                        continue

                    # Handle *start*/*end* marker lines
                    if _MARKER_LINE_RE.match(line):
                        if line.lower().startswith('*start*'):
                            marker = line[7:].lower().strip()
                            if any(kw in marker for kw in _CREDIT_SECTION_MARKERS):
                                current_section_type = 'credit'
                                current_section_name = marker
                            elif any(kw in marker for kw in _DEBIT_SECTION_MARKERS):
                                current_section_type = 'debit'
                                current_section_name = marker
                            elif any(kw in marker for kw in _NON_TXN_SECTION_MARKERS):
                                current_section_type = None
                                current_section_name = ''

                        elif line.lower().startswith('*end*') and current_section_type is not None:
                            # Some *end* lines have a transaction embedded whose month
                            # was consumed by the marker text, leaving only /DD.
                            # e.g. "*end*deposit0s and additio1ns /08 Deposit 1680055706 878.00"
                            m = _END_EMBEDDED_TXN_RE.search(line)
                            if m:
                                day_str, description, amount_str = m.groups()
                                date_str = f"{statement_month:02d}/{day_str}"
                                parsed_date = _det_parse_date(date_str, statement_year)
                                if parsed_date:
                                    try:
                                        transactions.append({
                                            'date': parsed_date,
                                            'description': description.strip(),
                                            'amount': round(_det_parse_amount(amount_str), 2),
                                            'type': current_section_type,
                                        })
                                    except ValueError:
                                        pass
                        continue  # skip marker lines (already handled above)

                    if current_section_type is None:
                        continue

                    # Chase Checks Paid section uses a different row format:
                    # "CHECK_NO ^ DATE AMOUNT"  (no leading date)
                    if 'checks' in current_section_name:
                        m = _CHECK_TXN_RE.match(line)
                        if m:
                            check_num, date_str, amount_str = m.groups()
                            parsed_date = _det_parse_date(date_str, statement_year)
                            if parsed_date:
                                try:
                                    transactions.append({
                                        'date': parsed_date,
                                        'description': f'Check #{check_num}',
                                        'amount': round(_det_parse_amount(amount_str), 2),
                                        'type': 'debit',
                                    })
                                except ValueError:
                                    pass
                            continue

                    # Standard transaction line: MM/DD Description... AMOUNT
                    m = _TXN_LINE_RE.match(line)
                    if not m:
                        continue

                    date_str, description, amount_str = m.groups()
                    parsed_date = _det_parse_date(date_str, statement_year)
                    if not parsed_date:
                        continue
                    try:
                        amount = _det_parse_amount(amount_str)
                    except ValueError:
                        continue

                    transactions.append({
                        'date': parsed_date,
                        'description': description.strip(),
                        'amount': round(amount, 2),
                        'type': current_section_type,
                    })

        return transactions, len(transactions) >= 2

    except Exception as e:
        print(f"Strategy 1 (section markers) failed: {e}", file=sys.stderr)
        return [], False


# ─── Strategy 2: Section header keywords (generic) ───────────────────────────

def _extract_by_section_headers(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Strategy 2: For banks that use plain section headers (no *start* markers).
    Handles JPMorgan Classic Business Checking which:
    - Shows multiple section names at the top of continuation pages (TOC-style),
      e.g. both "Deposits & Credits" AND "Checks Paid" appear before the credit
      transactions continue — only the FIRST header seen before any transaction
      on a page is treated as the active section.
    - Uses "Check Date" as a column header to signal the start of the checks table.
    - Uses check-row format: CHECK_NO DATE AMOUNT (no leading date, no ^ symbol).
    - Has a "Daily Ending Balance" section whose rows must not be parsed as transactions.
    Also filters out daily balance rows where the description itself contains a date.
    """
    transactions: List[Dict] = []
    try:
        with pdfplumber.open(pdf_path) as pdf:
            if not pdf.pages:
                return [], False

            first_text = pdf.pages[0].extract_text() or ''
            statement_year = _detect_year(first_text)
            current_section_type: Optional[str] = None
            current_section_name: str = ''

            for page in pdf.pages:
                text = page.extract_text()
                if not text:
                    continue

                # Per-page pending buffer: section headers seen before the first
                # transaction on this page are buffered. On continuation pages,
                # multiple section names appear as a table of contents at the top;
                # only the FIRST (= the section that continues from the prior page)
                # is applied when the first transaction line is encountered.
                pending_sections: List[Tuple[Optional[str], str]] = []
                page_has_transactions: bool = False

                for line in text.split('\n'):
                    line = line.strip()
                    if not line:
                        continue

                    if _MARKER_LINE_RE.match(line):
                        continue

                    # ── Section header detection ──────────────────────────────
                    section_matched = False
                    for pattern, section_type, section_name in _SECTION_HEADER_PATTERNS:
                        if pattern.match(line):
                            if not page_has_transactions:
                                # Pre-transaction phase: buffer (only first will be used)
                                pending_sections.append((section_type, section_name))
                            else:
                                # Post-transaction phase: real section transition
                                current_section_type = section_type
                                current_section_name = section_name
                            section_matched = True
                            break
                    if section_matched:
                        continue

                    # Nothing to do if no section context yet
                    if current_section_type is None and not pending_sections:
                        continue

                    # Determine the effective section for pre-transaction rows
                    if not page_has_transactions and pending_sections:
                        eff_type, eff_name = pending_sections[0]
                    else:
                        eff_type, eff_name = current_section_type, current_section_name

                    if eff_type is None:
                        continue  # in a non-transaction section (e.g. balance table)

                    # ── Multi-column check table (JPMorgan compact "Checks Paid") ──
                    # Handles rows like: "1068 11/24 450.00 1072 11/17 1,600.00 1077 11/19 2,558.83"
                    # These appear at the bottom of a deposits page with no section transition,
                    # so we detect them by pattern (2+ CHECK_NO MM/DD AMOUNT groups per line).
                    multi_check_cols = _MULTI_CHECK_COL_RE.findall(line)
                    if len(multi_check_cols) >= 2:
                        for check_num, date_str, amount_str in multi_check_cols:
                            parsed_date = _det_parse_date(date_str, statement_year)
                            if parsed_date:
                                try:
                                    transactions.append({
                                        'date': parsed_date,
                                        'description': f'Check #{check_num}',
                                        'amount': round(_det_parse_amount(amount_str), 2),
                                        'type': 'debit',
                                    })
                                except ValueError:
                                    pass
                        page_has_transactions = True
                        continue

                    # ── Check-row format: CHECK_NO [^] DATE AMOUNT ────────────
                    # Used when the effective section is checks (before or after
                    # first transaction, e.g. after "Check Date" column header).
                    if 'checks' in eff_name:
                        m = _CHECK_TXN_RE.match(line)
                        if m:
                            check_num, date_str, amount_str = m.groups()
                            parsed_date = _det_parse_date(date_str, statement_year)
                            if parsed_date:
                                try:
                                    # Apply pending section on first transaction of page
                                    if not page_has_transactions and pending_sections:
                                        current_section_type, current_section_name = pending_sections[0]
                                        page_has_transactions = True
                                    transactions.append({
                                        'date': parsed_date,
                                        'description': f'Check #{check_num}',
                                        'amount': round(_det_parse_amount(amount_str), 2),
                                        'type': 'debit',
                                    })
                                except ValueError:
                                    pass
                            continue

                    # ── Standard transaction line: MM/DD Description AMOUNT ───
                    m = _TXN_LINE_RE.match(line)
                    if not m:
                        continue

                    date_str, description, amount_str = m.groups()
                    parsed_date = _det_parse_date(date_str, statement_year)
                    if not parsed_date:
                        continue

                    # Skip daily-ending-balance rows: their "description" field
                    # is actually more date+amount pairs, e.g.:
                    #   "3,067.85 01/05 8,372.05 01/06"      (positive balances)
                    #   "(1,951.14) 01/08 427.80 01/14"      (parenthesized negatives)
                    # Real transaction descriptions start with text ("Card Purchase ...",
                    # "ACH Transfer ..."), not with a bare or parenthesized amount.
                    if re.match(r'^\s*(\([\d,]+\.\d{2}\)|[\d,]+\.\d{2})(?=\s|$)', description):
                        continue

                    # Apply pending section on first real transaction of this page
                    if not page_has_transactions and pending_sections:
                        current_section_type, current_section_name = pending_sections[0]
                        eff_type = current_section_type
                    page_has_transactions = True

                    if eff_type is None:
                        continue

                    try:
                        amount = _det_parse_amount(amount_str)
                    except ValueError:
                        continue

                    transactions.append({
                        'date': parsed_date,
                        'description': description.strip(),
                        'amount': round(amount, 2),
                        'type': eff_type,
                    })

        return transactions, len(transactions) >= 2

    except Exception as e:
        print(f"Strategy 2 (section headers) failed: {e}", file=sys.stderr)
        return [], False


# ─── Strategy 3: Column-position based (Wells Fargo / column-table style) ────

def _extract_by_column_position(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Strategy 3: For statements with separate Credits and Debits columns (Wells Fargo
    style). Uses pdfplumber word-level x-coordinates to identify which column each
    transaction amount falls in.
    """
    CREDIT_COL_WORDS = {'credits', 'deposits', 'deposits/'}
    DEBIT_COL_WORDS = {'debits', 'withdrawals', 'withdrawals/'}
    BALANCE_COL_WORDS = {'balance'}
    COL_TOLERANCE = 45  # ±45 points tolerance for column matching

    transactions: List[Dict] = []
    credits_x: Optional[float] = None
    debits_x: Optional[float] = None
    balance_x: Optional[float] = None
    statement_year = datetime.now().year

    try:
        with pdfplumber.open(pdf_path) as pdf:
            if not pdf.pages:
                return [], False

            first_text = pdf.pages[0].extract_text() or ''
            statement_year = _detect_year(first_text)

            for page in pdf.pages:
                words = page.extract_words()
                if not words:
                    continue

                # Group words into rows by approximate y-position (within 3pt)
                rows_by_y: Dict[int, List[Dict]] = defaultdict(list)
                for w in words:
                    rows_by_y[round(w['top'] / 3) * 3].append(w)

                # Look for header row containing BOTH a credit and a debit column label
                for row_key in sorted(rows_by_y):
                    row_words = rows_by_y[row_key]
                    row_texts = {w['text'].lower() for w in row_words}
                    if (row_texts & CREDIT_COL_WORDS) and (row_texts & DEBIT_COL_WORDS):
                        # Found the column header row — record x-centers
                        for w in row_words:
                            t = w['text'].lower()
                            cx = (w['x0'] + w['x1']) / 2
                            if t in CREDIT_COL_WORDS:
                                credits_x = cx
                            elif t in DEBIT_COL_WORDS:
                                debits_x = cx
                            elif t in BALANCE_COL_WORDS:
                                balance_x = cx
                        break  # use first matching row per page

                if credits_x is None or debits_x is None:
                    continue  # no usable column info on this page yet

                # Parse transaction rows
                for row_key in sorted(rows_by_y):
                    row_words = rows_by_y[row_key]
                    if not row_words:
                        continue

                    # Row must start with a date word (M/D or MM/DD)
                    first_word = min(row_words, key=lambda w: w['x0'])
                    if not _DATE_WORD_RE.match(first_word['text']):
                        continue

                    # Split words into amounts vs description words
                    amount_words = [w for w in row_words if _AMOUNT_WORD_RE.match(w['text'])]
                    desc_words = [
                        w for w in row_words
                        if not _AMOUNT_WORD_RE.match(w['text']) and w != first_word
                    ]

                    # Classify each amount by column x-position
                    credit_amounts = []
                    debit_amounts = []
                    for w in amount_words:
                        cx = (w['x0'] + w['x1']) / 2
                        if abs(cx - credits_x) <= COL_TOLERANCE:
                            credit_amounts.append(w['text'])
                        elif abs(cx - debits_x) <= COL_TOLERANCE:
                            debit_amounts.append(w['text'])
                        # balance column amounts are intentionally ignored

                    if credit_amounts and not debit_amounts:
                        txn_type = 'credit'
                        amount_str = credit_amounts[0]
                    elif debit_amounts and not credit_amounts:
                        txn_type = 'debit'
                        amount_str = debit_amounts[0]
                    else:
                        continue  # ambiguous or no classifiable amount

                    parsed_date = _det_parse_date(first_word['text'], statement_year)
                    if not parsed_date:
                        continue
                    try:
                        amount = _det_parse_amount(amount_str)
                    except ValueError:
                        continue
                    if amount <= 0:
                        continue

                    description = ' '.join(
                        w['text'] for w in sorted(desc_words, key=lambda w: w['x0'])
                    ).strip()
                    if not description:
                        continue

                    transactions.append({
                        'date': parsed_date,
                        'description': description,
                        'amount': round(amount, 2),
                        'type': txn_type,
                    })

        return transactions, len(transactions) >= 2

    except Exception as e:
        print(f"Strategy 3 (column position) failed: {e}", file=sys.stderr)
        return [], False


# ─── Strategy 4: PDF table structure (original fallback) ─────────────────────

def _extract_by_table_structure(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Strategy 4: Original table-based extraction using pdfplumber.extract_tables().
    Rarely succeeds for real bank statements but kept as final fallback.
    """
    try:
        transactions: List[Dict] = []

        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                tables = page.extract_tables()
                if not tables:
                    continue

                for table in tables:
                    if not table or len(table) < 2:
                        continue

                    headers = [str(h).lower() if h else '' for h in table[0]]
                    date_col = desc_col = debit_col = credit_col = balance_col = None

                    for i, header in enumerate(headers):
                        if 'date' in header:
                            date_col = i
                        elif 'description' in header or 'transaction' in header:
                            desc_col = i
                        elif 'debit' in header or 'withdrawal' in header or ('payment' in header and 'out' in header):
                            debit_col = i
                        elif 'credit' in header or 'deposit' in header or ('payment' in header and 'in' in header):
                            credit_col = i
                        elif 'balance' in header:
                            balance_col = i

                    if debit_col is None or credit_col is None:
                        continue

                    for row in table[1:]:
                        if not row or len(row) <= max(debit_col, credit_col):
                            continue

                        date_val = row[date_col] if date_col is not None and date_col < len(row) else None
                        desc_val = row[desc_col] if desc_col is not None and desc_col < len(row) else ''
                        debit_val = row[debit_col] if debit_col < len(row) else None
                        credit_val = row[credit_col] if credit_col < len(row) else None
                        balance_val = row[balance_col] if balance_col is not None and balance_col < len(row) else None

                        if not date_val or not desc_val:
                            continue

                        amount = None
                        txn_type = None

                        if debit_val and str(debit_val).strip() not in ['-', '', 'None']:
                            try:
                                amount = abs(float(str(debit_val).replace('$', '').replace(',', '').replace('(', '').replace(')', '').strip()))
                                txn_type = 'debit'
                            except ValueError:
                                pass

                        if amount is None and credit_val and str(credit_val).strip() not in ['-', '', 'None']:
                            try:
                                amount = abs(float(str(credit_val).replace('$', '').replace(',', '').replace('(', '').replace(')', '').strip()))
                                txn_type = 'credit'
                            except ValueError:
                                pass

                        if amount is None or txn_type is None:
                            continue

                        parsed_date = None
                        for fmt in ['%m/%d/%Y', '%m/%d/%y', '%Y-%m-%d', '%m-%d-%Y', '%m-%d-%y', '%m/%d', '%d/%m/%Y']:
                            try:
                                parsed_date = datetime.strptime(str(date_val).strip(), fmt)
                                if fmt == '%m/%d':
                                    parsed_date = parsed_date.replace(year=datetime.now().year)
                                break
                            except ValueError:
                                continue

                        if not parsed_date:
                            continue

                        transaction: Dict = {
                            'date': parsed_date.strftime('%Y-%m-%d'),
                            'description': str(desc_val).strip(),
                            'amount': round(amount, 2),
                            'type': txn_type,
                        }

                        if balance_val and str(balance_val).strip() not in ['-', '', 'None']:
                            try:
                                transaction['ending_balance'] = float(
                                    str(balance_val).replace('$', '').replace(',', '').replace('(', '').replace(')', '').strip()
                                )
                            except ValueError:
                                pass

                        transactions.append(transaction)

        return transactions, len(transactions) > 0

    except Exception as e:
        print(f"Strategy 4 (table structure) failed: {e}", file=sys.stderr)
        return [], False


# ─── Strategy 5: U.S. Bank ────────────────────────────────────────────────────

# Section header → (transaction_type, section_slug)
# transaction_type: 'credit' | 'debit' | 'checks' | None (stop parsing)
_USBANK_SECTION_HEADERS = {
    'other deposits':                    ('credit',  'deposits'),
    'other deposits (continued)':        ('credit',  'deposits'),
    'card withdrawals':                  ('debit',   'card'),
    'other withdrawals':                 ('debit',   'withdrawals'),
    'other withdrawals (continued)':     ('debit',   'withdrawals'),
    'checks presented conventionally':   ('checks',  'checks'),
    'balance summary':                   (None,      'balance'),
    'analysis service charge detail':    (None,      'analysis'),
}

# Transaction line: "Oct 3 Description ... Amount" or "Oct 3 Description ... Amount-"
# \s* between month and day handles "Nov12" (no space) and "Nov 3" (space) — pdfplumber
# drops the space for 2-digit days due to column alignment in the PDF.
# \$?\s* handles both "5,000.00" and "$ 5,000.00" (U.S. Bank puts $ before first entry per section)
# Captures: month_abbr, day, description, amount_str, minus_sign
_USBANK_TXN_RE = re.compile(
    r'^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s*(\d{1,2})\s+(.+?)\s+\$?\s*([\d,]+\.\d{2})(-?)\s*$',
    re.I,
)

# Multi-column check row: "CheckNo Month Day RefNum Amount [CheckNo Month Day RefNum Amount ...]"
# e.g. "1236 Oct 3 9213379221 612.50 1262 Oct 22 8613180667 690.00"
# \s* between month and day for same pdfplumber spacing issue (e.g. "Nov14" vs "Nov 5")
# Groups: check_num, month_abbr, day, amount_str
_USBANK_CHECK_COL_RE = re.compile(
    r'(\d+\*?)\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s*(\d{1,2})\s+\d+\s+([\d,]+\.\d{2})',
    re.I,
)


def _det_parse_usbank_date(month_abbr: str, day: str, year: int) -> Optional[str]:
    """Parse U.S. Bank date tokens ('Oct', '3', 2025) → '2025-10-03'."""
    try:
        dt = datetime.strptime(f"{month_abbr} {int(day):02d} {year}", "%b %d %Y")
        return dt.strftime('%Y-%m-%d')
    except ValueError:
        return None


def _extract_usbank(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Strategy 5: U.S. Bank statement format (Business & Consumer Checking).

    Characteristics:
    - Section headers: 'Other Deposits', 'Card Withdrawals', 'Other Withdrawals',
      'Checks Presented Conventionally' (each on its own line, mixed case)
    - Date format: 'Oct 3' / 'Oct 27'  (3-letter month + 1-2 digit day)
    - Debit amounts end with '-': '1,071.45-'
    - Check rows are multi-column pairs: CheckNo Month Day RefNum Amount
    """
    transactions: List[Dict] = []
    try:
        with pdfplumber.open(pdf_path) as pdf:
            if not pdf.pages:
                return [], False

            first_text = pdf.pages[0].extract_text() or ''

            # Quick guard: only activate for U.S. Bank PDFs
            first_lower = first_text.lower()
            if 'u.s. bank' not in first_lower and 'usbank.com' not in first_lower:
                return [], False

            statement_year = _detect_year(first_text)
            current_section_type: Optional[str] = None  # 'credit' | 'debit' | 'checks' | None
            current_section_name: str = ''

            for page in pdf.pages:
                text = page.extract_text()
                if not text:
                    continue

                for line in text.split('\n'):
                    line = line.strip()
                    if not line:
                        continue

                    # ── Section header detection ──────────────────────────────
                    line_lower = line.lower()
                    if line_lower in _USBANK_SECTION_HEADERS:
                        current_section_type, current_section_name = _USBANK_SECTION_HEADERS[line_lower]
                        continue

                    if current_section_type is None:
                        continue  # not in a parseable section yet

                    # ── Check section: multi-column rows ──────────────────────
                    if current_section_name == 'checks':
                        matches = _USBANK_CHECK_COL_RE.findall(line)
                        if matches:
                            for check_num, month, day, amount_str in matches:
                                parsed_date = _det_parse_usbank_date(month, day, statement_year)
                                if parsed_date:
                                    try:
                                        transactions.append({
                                            'date': parsed_date,
                                            'description': f'Check #{check_num.rstrip("*")}',
                                            'amount': round(_det_parse_amount(amount_str), 2),
                                            'type': 'debit',
                                        })
                                    except ValueError:
                                        pass
                        continue  # skip non-check-row lines in checks section

                    # ── Standard transaction line: Mon D Description Amount[-] ─
                    m = _USBANK_TXN_RE.match(line)
                    if not m:
                        continue

                    month, day, description, amount_str, _minus = m.groups()
                    parsed_date = _det_parse_usbank_date(month, day, statement_year)
                    if not parsed_date:
                        continue

                    try:
                        amount = _det_parse_amount(amount_str)
                    except ValueError:
                        continue

                    transactions.append({
                        'date': parsed_date,
                        'description': description.strip(),
                        'amount': round(amount, 2),
                        'type': current_section_type,
                    })

        return transactions, len(transactions) >= 2

    except Exception as e:
        print(f"Strategy 5 (U.S. Bank) failed: {e}", file=sys.stderr)
        return [], False


# ─── Strategy 6: FirstLight Federal Credit Union (FLFCU) ─────────────────────

# Transaction line: "MM/DD/YYYY Description [-Amount] Balance"
# Negative amount → debit; positive → credit.
_FLFCU_TXN_RE = re.compile(
    r'^(\d{2}/\d{2}/\d{4})\s+(.+?)\s+(-?[\d,]+\.\d{2})\s+([\d,]+\.\d{2})\s*$'
)

# Section keywords that signal end of transaction data (summary sections)
_FLFCU_STOP_KEYWORDS = frozenset({
    'checks cleared at a glance',
    'atm / checkcard activity at a glance',
    'summary of check fees',
})


def _extract_flfcu(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Strategy 6: FirstLight Federal Credit Union (FLFCU) member account statement.

    Characteristics:
    - Identifier: 'MemberAccountStatement' (no spaces in pdfplumber) + 'flfcu' / 'firstlightfcu'
    - Sections: SHARE SAVINGS (skipped) and CHECKING (captured)
      * Only CHECKING transactions are included — Share Savings are internal transfers
        that inflate totals and don't belong in MCA analysis
    - Transaction format: MM/DD/YYYY Description Amount Balance
      * Negative amount → debit; positive → credit
    - Continuation lines (CO:, ACHTraceNumber:, OnUsDraft, etc.) are automatically
      skipped because they don't start with MM/DD/YYYY
    - Summary sections (Checks Cleared, ATM Activity) stop parsing
    """
    transactions: List[Dict] = []
    try:
        with pdfplumber.open(pdf_path) as pdf:
            if not pdf.pages:
                return [], False

            first_lower = (pdf.pages[0].extract_text() or '').lower().replace(' ', '')

            # Guard: only activate for FLFCU statements
            if 'memberaccountstatement' not in first_lower:
                return [], False
            if 'flfcu' not in first_lower and 'firstlightfcu' not in first_lower:
                return [], False

            stop_parsing = False
            in_checking = False  # Only collect transactions from CHECKING section
            for page in pdf.pages:
                if stop_parsing:
                    break
                text = page.extract_text()
                if not text:
                    continue

                for line in text.split('\n'):
                    stripped = line.strip()
                    if not stripped:
                        continue

                    # Stop at summary sections
                    if stripped.lower() in _FLFCU_STOP_KEYWORDS:
                        stop_parsing = True
                        break

                    # Track account section — only parse CHECKING, skip SHARE SAVINGS
                    if stripped == 'CHECKING':
                        in_checking = True
                        continue
                    if stripped == 'SHARE SAVINGS':
                        in_checking = False
                        continue

                    if not in_checking:
                        continue

                    m = _FLFCU_TXN_RE.match(stripped)
                    if not m:
                        continue

                    date_str, description, amount_str, balance_str = m.groups()

                    parsed_date = _det_parse_date(date_str, 0)
                    if not parsed_date:
                        continue

                    try:
                        amount = _det_parse_amount(amount_str)
                        balance = _det_parse_amount(balance_str)
                    except ValueError:
                        continue

                    is_debit = amount < 0
                    transactions.append({
                        'date': parsed_date,
                        'description': description.strip(),
                        'amount': round(abs(amount), 2),
                        'type': 'debit' if is_debit else 'credit',
                        'ending_balance': round(balance, 2),
                    })

        return transactions, len(transactions) >= 2

    except Exception as e:
        print(f"Strategy 6 (FLFCU) failed: {e}", file=sys.stderr)
        return [], False


# ─── Orchestrator ─────────────────────────────────────────────────────────────

def extract_transactions_deterministic(pdf_path: str) -> Tuple[List[Dict], bool]:
    """
    Extract transactions deterministically using cascading strategies:
      1. *start*/*end* section markers  (Chase / JPMorgan)
      2. Section header keywords         (generic section-based banks)
      3. Column-position based           (Wells Fargo and similar)
      4. PDF table structure             (rarely succeeds, original fallback)
      5. U.S. Bank format               (Other Deposits / Card Withdrawals sections)
      6. FirstLight FCU                  (MemberAccountStatement / FLFCU format)

    Returns (transactions_list, success_flag, strategy_label).
    """
    for strategy_fn, label in [
        (_extract_by_section_markers, "section markers"),
        (_extract_by_section_headers, "section headers"),
        (_extract_by_column_position, "column position"),
        (_extract_by_table_structure, "table structure"),
        (_extract_usbank, "U.S. Bank"),
        (_extract_flfcu, "FirstLight FCU"),
    ]:
        txns, ok = strategy_fn(pdf_path)
        if ok:
            print(f"✓ Deterministic extraction successful ({label}): {len(txns)} transactions", file=sys.stderr)
            return txns, True, label

    return [], False, None


def _extract_statement_summary(pdf_text: str) -> Dict:
    """
    Parse beginning balance, ending balance, and (if stated) average daily balance
    directly from the raw PDF text using labeled-line patterns.

    Handles both positive amounts and parenthesized negatives, e.g.:
        Beginning Balance   3,587.23
        Ending Balance      2,472.00
        Average Daily Bal   (1,234.56)   ← negative, shown in parens
    """
    result: Dict[str, float] = {}

    # Label → field mapping (checked in order; first match per field wins)
    label_patterns = [
        (re.compile(r'(?:beginning|previous|prior|opening)\s+balance', re.I), 'beginning_balance'),
        (re.compile(r'(?:ending|closing|new)\s+balance', re.I), 'ending_balance'),
        (re.compile(r'average\s+(?:daily\s+)?balance', re.I), 'average_daily_balance'),
    ]

    # Amount: optional sign/paren, optional $, digits/commas, decimal places
    amount_re = re.compile(r'(-?\$?|\$?-?)(\()?\s*([\d,]+\.\d{2})\s*(\))?')

    for line in pdf_text.split('\n'):
        line = line.strip()
        for label_re, field in label_patterns:
            if field in result:
                continue  # already found this field
            lm = label_re.search(line)
            if not lm:
                continue
            # Search for an amount *after* the label text
            am = amount_re.search(line, lm.end())
            if not am:
                continue
            sign_prefix, open_paren, amount_str, close_paren = am.groups()
            try:
                amount = float(amount_str.replace(',', ''))
                if '-' in (sign_prefix or '') or (open_paren == '(' and close_paren == ')'):
                    amount = -amount
                result[field] = amount
            except ValueError:
                pass

    return result


def _compute_adb_from_transactions(transactions: List[Dict], beginning_balance: float) -> Optional[float]:
    """
    Reconstruct end-of-day balances for every calendar day in the statement period,
    then return the simple average (average daily balance).

    Starts from beginning_balance, applies credits (+) and debits (-) in date order.
    Carries the previous day's balance forward on days with no transactions.
    """
    if not transactions:
        return None

    by_date: Dict[str, List[Dict]] = defaultdict(list)
    for t in transactions:
        d = t.get('date')
        if d:
            by_date[d].append(t)

    if not by_date:
        return None

    dates = sorted(by_date.keys())
    try:
        start = datetime.strptime(dates[0], '%Y-%m-%d')
        end = datetime.strptime(dates[-1], '%Y-%m-%d')
    except ValueError:
        return None

    daily_balances: List[float] = []
    balance = beginning_balance
    current = start

    while current <= end:
        date_str = current.strftime('%Y-%m-%d')
        for t in by_date.get(date_str, []):
            if t.get('type') == 'credit':
                balance += float(t.get('amount', 0))
            elif t.get('type') == 'debit':
                balance -= float(t.get('amount', 0))
        daily_balances.append(balance)
        current += timedelta(days=1)

    if not daily_balances:
        return None

    return round(sum(daily_balances) / len(daily_balances), 2)


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
    "ending_balance": 5678.90,
    "average_daily_balance": 3456.78
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
- statement_summary.average_daily_balance: (OPTIONAL) Average daily balance for the statement period if explicitly mentioned in the statement (can be negative)
"""

    # Debug: log input text length
    debug_log = "/var/www/html/crmfinity-ai/storage/logs/extraction_debug.log"

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
    debug_log = "/var/www/html/crmfinity-ai/storage/logs/extraction_debug.log"
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
        transactions_det, det_success, det_label = extract_transactions_deterministic(pdf_path)

        # Map strategy label → bank name (for strategies that identify a specific bank)
        _DET_BANK_NAMES = {
            "section markers":  "JPMorgan Chase",   # *start*/*end* markers are Chase/JPMorgan-specific
            "U.S. Bank":        "U.S. Bank",
            "FirstLight FCU":   "FirstLight FCU",
        }
        det_bank_name = _DET_BANK_NAMES.get(det_label) if det_label else None

        if det_success and len(transactions_det) > 0:
            # Deterministic extraction succeeded - use these results
            print(f"✓ Deterministic extraction successful: {len(transactions_det)} transactions", file=sys.stderr)
            transactions = transactions_det
            usage = {"prompt_tokens": 0, "completion_tokens": 0, "total_tokens": 0, "model": "none"}  # No AI usage
            extraction_method = "deterministic"

            # Extract statement summary (beginning/ending/average balance) from PDF text.
            # _extract_statement_summary uses labeled-line patterns (no AI cost).
            det_summary = _extract_statement_summary(pdf_text)

            # If we found a beginning balance but no explicit ADB, compute it from
            # the transaction list so downstream analytics have a useful number.
            if det_summary.get('beginning_balance') is not None:
                if det_summary.get('average_daily_balance') is None:
                    computed_adb = _compute_adb_from_transactions(
                        transactions_det, det_summary['beginning_balance']
                    )
                    if computed_adb is not None:
                        det_summary['average_daily_balance'] = computed_adb
                        print(f"  Computed ADB: ${computed_adb:,.2f}", file=sys.stderr)

            statement_summary = det_summary if det_summary else None
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
                "bank_name": det_bank_name if extraction_method == "deterministic" else None,
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
