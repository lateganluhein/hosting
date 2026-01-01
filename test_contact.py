#!/usr/bin/env python3
"""
Test contact form endpoint

Usage:
    python test_contact.py [base_url]

    Default URL: http://localhost (requires local PHP server)
    Production:  python test_contact.py https://www.manycents.co.za
"""

import requests
import sys
import time

BASE_URL = sys.argv[1] if len(sys.argv) > 1 else "http://localhost"


def test_form(data, description, expected_error=None, expected_success=False):
    """Test the contact form with given data"""
    url = f"{BASE_URL}/contact.php"
    print(f"\n{'='*60}")
    print(f"Testing: {description}")
    print(f"{'='*60}")

    try:
        response = requests.post(url, data=data, allow_redirects=False, timeout=10)

        print(f"Status Code: {response.status_code}")

        if response.status_code in (301, 302, 303):
            location = response.headers.get('Location', '')
            print(f"Redirect to: {location}")

            if expected_success and 'success=true' in location:
                print("✅ SUCCESS - Form accepted")
                return True
            elif expected_error and f'error={expected_error}' in location:
                print(f"✅ SUCCESS - Got expected error: {expected_error}")
                return True
            else:
                print(f"❌ FAILED - Unexpected redirect")
                return False
        else:
            print(f"❌ FAILED - Expected redirect, got {response.status_code}")
            return False

    except requests.exceptions.ConnectionError:
        print(f"❌ CONNECTION ERROR - Is the server running?")
        print(f"   For local testing: php -S localhost:80")
        print(f"   Or test production: python test_contact.py https://www.manycents.co.za")
        return False
    except Exception as e:
        print(f"❌ ERROR: {e}")
        return False


def run_all_tests():
    print("\n" + "="*60)
    print("🧪 CONTACT FORM TEST SUITE")
    print("="*60)
    print(f"Testing: {BASE_URL}")
    input("Press ENTER to start tests...")

    results = {}
    unique_id = int(time.time())

    # Test 1: Missing required fields
    results['missing_fields'] = test_form(
        {"name": "", "company": "", "email": "", "message": ""},
        "Missing required fields",
        expected_error="missing"
    )

    # Test 2: Invalid email
    results['invalid_email'] = test_form(
        {
            "name": "Test User",
            "company": "Test Company",
            "email": "not-an-email",
            "message": "Test message"
        },
        "Invalid email format",
        expected_error="invalid_email"
    )

    # Test 3: Honeypot triggered (spam)
    results['honeypot_spam'] = test_form(
        {
            "name": "Test User",
            "company": "Test Company",
            "email": f"test{unique_id}@example.com",
            "message": "Test message",
            "website": "http://spam.com"  # Honeypot field filled = spam
        },
        "Honeypot field filled (spam detection)",
        expected_error="spam"
    )

    # Test 4: Valid submission (only run if explicitly requested)
    # Commented out to avoid sending actual emails during testing
    # results['valid_submission'] = test_form(
    #     {
    #         "name": "Test User",
    #         "company": "Test Company",
    #         "email": f"test{unique_id}@example.com",
    #         "product": "Test Product",
    #         "message": "This is a test submission from test_contact.py",
    #         "website": ""  # Honeypot must be empty
    #     },
    #     "Valid form submission",
    #     expected_success=True
    # )

    # Summary
    print("\n" + "="*60)
    print("TEST SUMMARY")
    print("="*60)

    passed = sum(1 for v in results.values() if v)
    total = len(results)

    for test, result in results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{status} - {test}")

    print(f"\n{passed}/{total} tests passed")
    print("="*60)
    print("\nNote: Valid submission test is disabled to avoid sending emails.")
    print("Uncomment in test_contact.py to test full form flow.")


if __name__ == "__main__":
    run_all_tests()
