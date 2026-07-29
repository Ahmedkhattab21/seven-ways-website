# UAT-DEF-010 — Cash count review workflow

## Result

READY — Cashiers submit counts, accountants review them, and authorized managers approve and close sessions.

## Changes

- Added `treasury.cash_sessions.review` as a separate permission.
- Cashier remains limited to view/open/count/submit; review is removed.
- Accountant receives view/review only for cash sessions; approval/close/reopen
  are not granted.
- Review is enforced by policy and controller, including creator self-review
  prevention and normal company/branch scope checks.
- The UI uses explicit Arabic labels for submit, review, and approval actions;
  the sidebar already keys the sessions link to `cash_sessions.view`.
- Added an idempotent UAT repair command; it does not touch sessions, counts,
  balances, journals, or documents.

## Verification

- UAT repair ran twice with no data changes; production execution rejected.
- Cash session tests: 4 passed.
- Full suite: 328 passed.
- Pint, view cache, and diff check passed.

## UAT-DEF-010A — Actual company accountant role repair

The repair now reconciles every active `accountant` role used by company users,
in addition to the canonical system role. This covers the company-specific role
that can shadow the global role with the same technical name and was the cause
of the manual 403 when only the global role had been updated.

The company role receives only `treasury.cash_sessions.view` and
`treasury.cash_sessions.review`; opening, counting, submitting, approving,
closing, reopening, and custodian override remain removed. The command prints
the role name, company scope, and added/removed permissions and is idempotent.
No user-role assignment, session, count, balance, journal, document, password,
or branch data is changed.

### UAT database diagnosis

- `accountant@sevenways.test` is user ID **17** and is attached to role ID **5**:
  system `accountant`, `company_id = NULL`, scope `branch`, active/system.
- The database also contains role ID **14**: company-specific `accountant`,
  `company_id = 1`, scope `company`, active/non-system, used by
  `uat.accountant@sevenways.test`.
- This is a technical-name collision. The previous repair updated the global
  role only; the reconciler now updates both roles when active company users
  use them.
- After repair, both roles have `view` and `review`; the company role has all
  forbidden cash-session actions removed. The command’s second run reported no
  additions or removals, confirming idempotency.
- No passwords, role assignments, sessions, counts, balances, journals,
  documents, or branches were changed. The pre-repair 403 was caused by stale
  role permissions; the post-repair route is authorized for the attached role.
