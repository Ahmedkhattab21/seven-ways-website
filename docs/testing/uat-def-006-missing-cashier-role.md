# UAT-DEF-006 — Missing cashier role

## Result

READY — Cashier role exists with branch-scoped operational treasury permissions.

## Fix

`FoundationPermissionSeeder` now creates one idempotent system role:
`cashier` / `أمين صندوق`, scope `branch`, active and system-owned. Treasury
seeders attach cash-session, receipt, payment, cheque, cash-box and balance
permissions with `syncWithoutDetaching`. No cashier user is created.

The user form now receives the role once and displays its technical name and
scope. Existing company roles remain preferred when a system/company name
collision exists.

## Verification

- `ProductionReferenceSeeder` ran successfully in isolated UAT.
- CashierRoleTest and TenantFoundationTest: 13 passed.
- Full suite: 326 passed.
- Pint, Vite build, view cache, and diff check: passed.
- No destructive database command was used.
