# BootstrapAccessSeeder Report

Date: 2026-07-28

## Accounts

| Account | Email | Role | Company | Branch |
| --- | --- | --- | --- | --- |
| System Administrator | `system.admin@sevenways.test` | `system_admin` | None | None |
| Seven Ways Owner | `owner@sevenways.test` | `company_owner` | Seven Ways | None until first branch |

Passwords are not repeated in this report. The local/testing defaults are
documented in the task and are never valid production credentials. Production
requires explicit non-`.test` environment values and `--force`.

## Login flow

- System Administrator can authenticate without a company or branch.
- Company Owner can authenticate only for the narrow case of an active company
  with zero active branches, then is redirected to first-branch setup.
- Creating the first branch makes it the main branch and binds it to the owner,
  including `user_branch_access` and default branch context.
- Other users remain subject to the normal active company/branch rules.

## Seeder scope

The seeder is idempotent and creates only the bootstrap company, roles,
permissions and two access accounts. It creates no branches, warehouses,
cash-boxes, bank accounts, products, services, customers, suppliers,
employees, documents, journals, opening balances, QA data or UAT transactions.
The existing schema has no `companies.code` column; the company UUID remains the
schema identity and no unnecessary migration was introduced.

## Execution

| Command / test | Result | Notes |
| --- | --- | --- |
| `BootstrapAccessSeederTest` | **3 passed, 0 failed** | Seeder, login gate and first-branch binding |
| ProductionReferenceSeeder + Bootstrap twice on `uat.local` | **Passed** | Backup taken first; Seven Ways has 0 branches |
| `ProductionReferenceSeeder` on `testing` | Blocked | Existing `role_permissions` table is read-only; no repair attempted |
| Full application suite | **318 passed, 0 failed** | Existing suite plus three BootstrapAccessSeeder tests |

The scripted HTTP login smoke attempt was not treated as manual UAT; the feature
tests cover the login and redirect flow. Browser/manual UAT remains pending.

The UAT environment was also missing the `sessions` table while configured with
the database session driver. A forward-only migration was added and applied to
UAT; no existing business data was changed.

## Data safety

UAT backup was written outside Git before execution:
`D:\xxamp\uat-backups\seven_ways_uat_before_bootstrap_20260728_193954.sql`.
No historical database on port 3306 was accessed, and no destructive migration,
database wipe or fresh rebuild was executed.

## Result

```text
READY - Bootstrap accounts and first-branch onboarding are available for
local/testing/UAT manual access setup.
```

This does not create production credentials or authorize deployment.
