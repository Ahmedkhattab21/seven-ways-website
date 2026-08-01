# UAT-DEF-028 — Branch manager cash-box sessions

## Diagnosis

The application code and the canonical `ThreeRoleOperatingModelSeeder` define the expected branch-manager permissions, but the UAT user role had no cash-session permissions assigned. The issue was stale UAT role-permission data, not the permission cache.

## Implemented controls

- A branch manager can view, open, count, and submit cash-box sessions.
- The session page lists only active cash boxes in the manager's branch where the current manager has an active, date-valid custodian assignment.
- A branch manager is fixed as the session custodian and cannot open a session for another user.
- Central users see only active, date-valid custodians for each eligible cash box.
- Cross-company and cross-branch access remains blocked.
- Duplicate active sessions remain protected by the existing transaction, row lock, and active-session guard.
- Opening a session does not post a journal entry or alter a treasury balance.
- Review, approval, reopening, over/short approval or posting, and cash-box lifecycle permissions remain excluded from the branch-manager role.

## UAT reconciliation

The canonical, idempotent `ThreeRoleOperatingModelSeeder` was used to reconcile the existing role permissions. No users, cash boxes, sessions, journal entries, balances, or operational documents were created or modified by the reconciliation.

## Automated coverage

- Canonical branch-manager permission allowlist and sensitive-permission deny list.
- Sidebar/session-page visibility.
- Own-branch and eligible-custodian filtering.
- Read-only current-user custodian selection.
- Successful session opening without journal posting.
- Cross-branch and alternate-custodian rejection.
- Expired assignment rejection.
- Duplicate active-session rejection.

## Verification

- `php artisan optimize:clear --env=uat.local`: passed.
- `php artisan db:seed --class=ThreeRoleOperatingModelSeeder --env=uat.local`: passed.
- `php artisan test --filter=BranchManagerCashBoxSessionTest`: 6 passed.
- `php artisan test --filter=CashBoxSession`: passed.
- `php artisan test --filter=ThreeRoleBranchOperatingModel`: passed.
- `php artisan test --filter=SidebarNavigation`: passed.
- `php artisan test --filter=Treasury`: passed.
- `php artisan test`: 469 passed; one pre-existing calendar assertion failed in `PhaseNineQuotationAppointmentTest`.
- Targeted Pint check for the changed PHP files: passed.
- Full `vendor/bin/pint --test`: one pre-existing style issue in `AccountingPostingService.php`.
- `npm.cmd run build`: passed with existing unresolved static website asset warnings.
- `php artisan view:cache`: passed.
- `git diff --check`: passed.

PHP 8.4 continues to emit deprecation warnings from Laravel 9-era vendor packages.

## Result

READY — The Alexandria branch manager can access and open only their own active cash-box session while review and approval remain separated.
