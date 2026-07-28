# UAT-DEF-004 — Bank access and treasury mapping

## Result

READY — BANK_TRANSFER receipt maps to BANK-CAI-001 with valid branch access.

## Root cause and fix

Branch-owned bank accounts were created without a matching access row, while
mapping validation required branch access. Creation now transactionally creates
active view/receive access only; payment and transfer rights remain explicit.
Branch changes before financial activity safely deactivate the old access and
create the new one. Mapping validation now checks the operation-specific
ability instead of using `can_view` for every operation.

Business-rule failures from web POST forms redirect back with old input and a
user-facing error; API behavior remains unchanged.

## UAT repair

`uat:repair-bank-access BANK-CAI-001 --env=uat.local` completed idempotently.
No balances, journal entries, or documents were changed. Production execution
is rejected.

## Verification

- Treasury regression: 10 passed.
- Full suite before this patch: 322 passed.
- Pint: passed; view cache: passed; diff check: passed.
