# UAT-DEF-011 — Cash session closing workflow

## Result

READY — Cash sessions cannot be submitted, approved, or closed without an approved closing count, and business-rule failures return a safe web redirect instead of HTTP 500.

## Diagnosis

`CashBoxSessionService` allowed `counting -> pending_approval -> approved` without checking for an approved `closing` count. The later close check threw `BusinessRuleException` after the session had already become stuck at `approved`; the web handler also called a non-existent `Request::expectsHtml()` method, turning the expected business error into HTTP 500.

## Fix

- Submit, session approve, and close now require the latest approved closing count.
- Close continues to require a posted over/short adjustment for non-zero differences.
- The cash-session page hides submit/approve/close until the closing count is approved and shows Arabic status/help text.
- Web `BusinessRuleException` responses redirect back with the generic `business` error key and flashed input.
- Added a UAT-only, idempotent repair command:
  `php artisan uat:repair-cash-session-closing-workflow --env=uat.local`

## UAT repair

`CAI-MAIN-CS-2026-000001` was repaired from `approved` to `counting`. Its approved opening count was preserved; no counts, balances, journals, documents, passwords, or branches were changed. The second run repaired zero sessions. An audit event `treasury.cash_session.returned_to_counting` records the reason.

## Verification

- Regression tests cover submit, approve, and close without an approved closing count, unchanged state, zero closing count, and the normal close path.
- Full suite and quality checks were run after the patch.
