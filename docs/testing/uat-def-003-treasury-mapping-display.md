# UAT-DEF-003 — Treasury mapping display

## Result

READY — Treasury payment mappings display understandable names instead of raw IDs.

## Changes

- Added the `branch()` relation to `PaymentMethodAccountMapping`.
- Treasury mappings now eager-load payment method, branch, GL account, bank
  account, and cash box relations.
- Replaced raw IDs with readable payment-method, branch, operation, target, and
  active/inactive labels. Missing relations use safe fallbacks.
- Added labelled selects, clear defaults, and the single-target helper text.
- Existing mappings and business rules were not changed.

## Verification

- `PhaseFifteenTreasuryFoundationTest`: 8 passed.
- Pint: passed.
- Blade view cache: passed.
- `git diff --check`: passed (only Windows line-ending warnings).
