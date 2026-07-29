# UAT-DEF-012 — Cash-box custodian abilities

## Result

READY — Cash-box custodians have explicit receive, pay, transfer, and payment-limit controls, and the active cashier assignment can authorize cash payments.

## Changes

- The custodian form now sends explicit boolean values for receive, pay, transfer, and primary, plus payment limit and validity dates.
- Active assignments can be updated without changing the user or cash box. Updates are tenant-scoped, reject inactive assignments, prevent overlapping primary periods, and write before/after audit metadata.
- Added `PUT /treasury/cash-box-custodians/{cashBoxCustodian}` protected by `treasury.cash_boxes.manage_custodians`.
- The cash-box page displays each custodian's abilities, limit, dates, status, and provides an update form.
- Custodian authorization errors are Arabic and distinguish missing assignment, receive/pay/transfer denial, and limit violations. Web failures redirect with a generic business error.

## UAT repair

`uat:repair-cashier-custodian-abilities --env=uat.local` updated the existing active assignment for `cashier@sevenways.test` on the Cairo main cash box to receive/pay/transfer enabled, limit `10000`, and primary. No duplicate assignment, session, balance, journal, document, branch, user, or password was changed. A second run reports no changes.

## Verification

- Regression tests cover independent abilities, limit enforcement, update safety, inactive assignments, primary overlap, and existing payment workflow coverage.
- Full suite and requested quality checks passed.
