#!/usr/bin/env python3
"""
Debug script to test amount parsing with commas.
This will help identify where amounts like "1,368.47" might be parsed as "1".
"""

import re

def test_patterns():
    """Test various regex patterns against amounts with commas."""
    test_amounts = [
        "1,368.47",
        "$1,368.47",
        "1,234.56",
        "$1.00",
        "1.00"
    ]

    patterns = [
        (r'(\d+),', 'WRONG: Captures only before comma'),
        (r'\$(\d+)', 'WRONG: Dollar + digits (no comma)'),
        (r'([\d,]+\.\d{2})', 'CORRECT: Digits with commas + decimal'),
        (r'\$?([\d,]+\.\d{2})', 'CORRECT: Optional dollar + digits with commas'),
        (r'(\d+)\.\d{2}', 'WRONG: Digits before dot (no comma)'),
    ]

    print("=" * 80)
    print("AMOUNT PARSING TEST")
    print("=" * 80)

    for amount in test_amounts:
        print(f"\nTesting amount: {amount!r}")
        print("-" * 40)
        for pattern, description in patterns:
            match = re.search(pattern, amount)
            if match:
                captured = match.group(1)
                # Try to convert to float after removing commas
                try:
                    clean_value = captured.replace(',', '')
                    float_value = float(clean_value)
                    status = "✓" if float_value > 1.0 else "✗"
                    print(f"{status} {description}")
                    print(f"  Pattern: {pattern!r}")
                    print(f"  Captured: {captured!r} -> Float: {float_value}")
                except ValueError as e:
                    print(f"✗ {description}")
                    print(f"  Pattern: {pattern!r}")
                    print(f"  Captured: {captured!r} -> ERROR: {e}")


def test_openai_json_parsing():
    """Test how OpenAI JSON responses with amounts might be parsed."""
    import json

    print("\n" + "=" * 80)
    print("OPENAI JSON PARSING TEST")
    print("=" * 80)

    # Simulate different ways OpenAI might return amounts
    test_cases = [
        ('{"amount": 1368.47}', 'Number (correct)'),
        ('{"amount": "1368.47"}', 'String without comma (correct)'),
        ('{"amount": "1,368.47"}', 'String with comma (PROBLEMATIC)'),
    ]

    for json_str, description in test_cases:
        print(f"\n{description}: {json_str}")
        try:
            data = json.loads(json_str)
            amount = data['amount']
            print(f"  Parsed as: {type(amount).__name__} = {amount!r}")

            # Try to convert to float
            try:
                if isinstance(amount, str):
                    # This is where the bug might be if we don't remove commas
                    float_val = float(amount)  # This will FAIL with commas
                    print(f"  float(): {float_val} ✓")
                else:
                    print(f"  Already a number: {amount} ✓")
            except ValueError:
                print(f"  float() FAILED - amount has comma! ✗")
                # Try with comma removal
                clean_amount = amount.replace(',', '')
                float_val = float(clean_amount)
                print(f"  float(amount.replace(',', '')): {float_val} ✓ FIX NEEDED")

        except Exception as e:
            print(f"  ERROR: {e}")


if __name__ == '__main__':
    test_patterns()
    test_openai_json_parsing()

    print("\n" + "=" * 80)
    print("RECOMMENDATIONS")
    print("=" * 80)
    print("""
1. ALWAYS use [\d,]+ instead of \d+ in regex patterns for amounts
2. ALWAYS remove commas before float() conversion: float(amount.replace(',', ''))
3. Check OpenAI responses to ensure amounts are returned as numbers, not strings
4. Add validation logging to see exactly what amounts are being extracted
    """)
