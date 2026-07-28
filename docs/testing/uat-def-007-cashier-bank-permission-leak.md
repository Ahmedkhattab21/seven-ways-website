# UAT-DEF-007 — Cashier bank-permission leak

## Result

READY — Cashier is restricted to branch cash operations and cannot access bank statement or reconciliation modules.

## Audit and fix

The leak came from `BankReconciliationSeeder`, which attached
`treasury.bank_statements.view` and `treasury.reconciliation.view` to cashier.
Those assignments were removed. A final `CashierPermissionReconciler` now runs
after all reference seeders and synchronizes only the approved cashier
allowlist. It changes only the system cashier role; company-specific roles and
users are untouched.

`uat:repair-cashier-permissions` is read-safe, idempotent, blocked in
production, and reports only permission names. It does not alter passwords,
branches, balances, journals, or documents.

## Verification

- UAT repair: first run removed two leaked permissions; second run made no changes.
- Production execution rejected.
- CashierRoleTest: 2 passed.
- Pint passed; no destructive database command was used.
