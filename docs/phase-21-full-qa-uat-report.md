# Phase 21 — Full QA and UAT Report

Date: 2026-07-28

## 1. Scope

| Area | Status |
| --- | --- |
| Automated static/security | Automated Passed |
| Automated database E2E | Blocked by Environment |
| HTTP Smoke through real server | Not Executed |
| Manual Browser | Pending |
| Accounting Reconciliation | Pending |
| Security runtime | Partially completed |
| Performance | Blocked by Environment |
| Queue worker / retry | Blocked by Environment |
| Scheduler runtime/idempotency | Blocked by Environment |
| Backup / Restore | Blocked by Environment |

No production deployment, credential, commit, tag, push, database wipe, historical migration edit, or go-live action was performed.

## 2. UAT environment

| Setting | Value |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3307` |
| Database | `seven_ways_uat` |
| Restore database | `seven_ways_uat_restore` |
| PHP | 8.2.12 |
| MariaDB | Expected 10.4.32; service unavailable |
| Branch | `main` |
| Commit at audit start | `900eb67` |
| Working tree | Dirty with uncommitted Phase 21 changes |

The safe TCP probe and `uat:validate-target --env=uat.local` both failed because no MariaDB process accepts connections on port 3307. Port 3306 was never contacted. `D:\xxamp\mysql\data` was not read, changed, repaired, or deleted.

The ignored `.env.uat.local` contains only local placeholders and an empty application key. It is covered by `.env.*.local` in `.gitignore`.

## 3. UAT accounts

| Role | Email | Branches |
| --- | --- | --- |
| Company owner | `uat.owner@sevenways.test` | All |
| General manager | `uat.general.manager@sevenways.test` | All |
| Cairo manager | `uat.cairo.manager@sevenways.test` | Cairo |
| Giza manager | `uat.giza.manager@sevenways.test` | Giza |
| Accountant | `uat.accountant@sevenways.test` | Cairo/Giza/Alexandria |
| Treasury manager | `uat.treasury.manager@sevenways.test` | Cairo/Giza |
| Cairo cashier | `uat.cairo.cashier@sevenways.test` | Cairo |
| Sales | `uat.sales@sevenways.test` | Cairo |
| Warehouse keeper | `uat.warehouse@sevenways.test` | Cairo |
| Technician | `uat.technician@sevenways.test` | Cairo |
| Quality controller | `uat.quality@sevenways.test` | Cairo |
| Receptionist | `uat.reception@sevenways.test` | Cairo |
| Viewer | `uat.viewer@sevenways.test` | Cairo |
| Disabled | `uat.disabled@sevenways.test` | Login denied |

Passwords are hashed by the seeder. Accounts are created only after the UAT target guard passes.

## 4. UAT seed design

`SevenWaysUatSeeder` is guarded by environment, host, port, connection and exact database name. It:

- creates `Seven Ways UAT Egypt` with EG, EGP, `Africa/Cairo`, Arabic and RTL;
- creates Cairo, Giza and Alexandria branches;
- creates role/branch-scoped test accounts;
- creates VAT 14%, zero and exempt reference taxes without applying VAT to all data;
- preserves SAR and USD as additional currencies;
- creates four regular warehouses while the official transfer seeder creates one system transit warehouse per branch;
- creates cash boxes and fake bank accounts without IBAN or stored balance;
- creates an open July 2026 UAT accounting period;
- creates five products, five services, six customers, five vehicles, five suppliers and ten employees;
- creates no posted invoice, journal, stock movement/balance, treasury operation, approval task, notification, delegation, commission accrual, expense, advance, or fake audit event;
- is designed to be idempotent and refuses a conflicting email owned by another company.

`UatPerformanceSeeder` is a separate guarded operation. It prepares the requested approximate dataset through idempotent, UAT-prefixed fixtures:

| Dataset | Target |
| --- | ---: |
| Customers | 500 |
| Suppliers | 100 |
| Products | 300 |
| Draft sales invoices | 2,000 |
| Draft supplier invoices | 500 |
| Synthetic stock movement report rows | 5,000 |
| Balanced draft journal lines | 10,000 |
| Approval tasks | 1,000 |
| Audit events | 10,000 |
| Database notifications | 2,000 |

The performance seeder was not executed because MariaDB is stopped. It must run only after a logical backup of base UAT results.

## 5. Readiness matrix

The detailed module matrix is in `docs/uat/phase-21-uat-readiness-matrix.md`.

| Module group | Automated | HTTP | Manual | Accounting | Status |
| --- | --- | --- | --- | --- | --- |
| Tenant, users, roles, branch scope | Prepared | Not Executed | Pending | N/A | Blocked by Environment |
| CRM and master data | Prepared | Not Executed | Pending | N/A | Blocked by Environment |
| Quotation, appointment, work order | Prepared | Not Executed | Pending | Pending | Blocked by Environment |
| Quality, rework, delivery, warranty | Existing regression coverage | Not Executed | Pending | Pending | Blocked by Environment |
| Sales and receivables | Existing regression coverage | Not Executed | Pending | Pending | Blocked by Environment |
| Purchasing and payables | Existing regression coverage | Not Executed | Pending | Pending | Blocked by Environment |
| Inventory and transfers | Prepared + existing regression | Not Executed | Pending | Pending | Blocked by Environment |
| Accounting and closing | Existing regression coverage | Not Executed | Pending | Pending | Blocked by Environment |
| Banking and reconciliation | Existing regression coverage | Not Executed | Pending | Pending | Blocked by Environment |
| Treasury, cheques, merchant | Prepared + existing regression | Not Executed | Pending | Pending | Blocked by Environment |
| Employee finance | Prepared + existing regression | Not Executed | Pending | Pending | Blocked by Environment |
| Approvals, notifications, delegation, audit | Existing regression coverage | Not Executed | Pending | N/A | Blocked by Environment |
| Dashboards, reports and exports | Prepared + existing regression | Not Executed | Pending | Pending | Blocked by Environment |
| Security / health headers | 4 Passed | Not Executed | Pending | N/A | Partially completed |
| Queue, scheduler, backup/restore | Static checks passed | Not Executed | Pending | N/A | Blocked by Environment |

## 6. Business cycles

| Cycle | Automated passed | Automated blocked | Manual passed | Manual pending | Blocked |
| --- | ---: | ---: | ---: | ---: | ---: |
| Master data and access | 0 | 2 | 0 | 1 | 1 |
| Customer, vehicle and quotation | 0 | 1 | 0 | 1 | 1 |
| Appointment / work order / quality | 0 | Existing suite not rerun | 0 | 2 | 1 |
| Sales / AR | 0 | Existing suite not rerun | 0 | 2 | 1 |
| Purchasing / AP | 0 | Existing suite not rerun | 0 | 2 | 1 |
| Inventory | 0 | 2 | 0 | 1 | 1 |
| Accounting / treasury | 0 | 2 | 0 | 2 | 1 |
| Employee finance / approvals | 0 | 1 | 0 | 2 | 1 |
| Reports / exports | 0 | 2 | 0 | 2 | 1 |
| Security / operations | 4 | 0 | 0 | 2 | 1 environment blocker |

## 7. Accounting reconciliation

No UAT financial document or journal was created because database provisioning did not run. Therefore no accounting value is reported as reconciled.

Current status:

- Posted UAT journals: Not Executed.
- Unbalanced UAT journals: Not Executed.
- Posting-link comparison: Not Executed.
- Report vs GL: Pending.
- Dashboard vs GL: Pending.
- Inventory vs GL: Pending.
- AR/AP vs control accounts: Pending.

The execution sheet is `docs/uat/phase-21-accounting-reconciliation.md`.

## 8. Security

Automated passed:

- UAT guard rejects port 3306 and a non-UAT database.
- UAT seeder is not registered in production seeding.
- `/health` returns only `{"status":"ok"}`.
- Security headers include MIME-sniffing and frame protections.
- Queue migration and six scheduler commands are present.

Prepared for database execution:

- active/disabled login;
- viewer read-only behavior;
- accountant/cashier SOD;
- Cairo/Giza branch isolation;
- cross-branch inventory rejection;
- transit warehouse restrictions;
- private attachments, upload MIME/path security, export permission, CSRF/throttling, and cross-company regressions through the existing suite.

No manual security scenario is marked Passed.

## 9. Performance

The guarded performance dataset and targets are prepared, but no timing, query count, memory, result-row, timeout, slow-query, or 5,000-row export measurement was executed.

Status: **Blocked by Environment**.

Targets remain guidance, not a claimed production SLA:

- normal page: up to 2 seconds;
- heavy report: up to 5 seconds;
- 5,000-row export: up to 15 seconds.

## 10. Backup and restore

No UAT backup was produced because `seven_ways_uat` could not be provisioned. `seven_ways_uat_restore` was not created. No database was deleted.

Status: **Blocked by Environment**.

The required drill remains:

1. Validate exact source target.
2. Create a non-empty logical dump.
3. Restore into the exact disposable restore database.
4. Compare table and row counts, migrations, companies, users, branches, documents, journals, stock, approvals and audit.
5. Run integrity, readiness, login, dashboard and reports smoke.
6. Remove only the verified disposable restore database after evidence is captured.

## 11. Queue and scheduler

- Database queue migration exists.
- Six scheduler entries were listed successfully.
- Queue/scheduler operating runbooks exist.
- No worker process was started.
- No success/retry/failed/retry cycle was executed.
- No scheduler command was executed twice against UAT.

Status: **Static configuration passed; runtime blocked by environment**.

## 12. Defects

| ID | Severity | Module | Description | Status | Retest |
| --- | --- | --- | --- | --- | --- |
| UAT-ENV-001 | Blocker | Environment | MariaDB port 3307 refuses connections | Open | Pending |

No application defect was confirmed in this run. The detailed register is `docs/uat/phase-21-defect-register.md`.

## 13. Tests and commands

| Command / check | Passed | Failed | Blocked | Notes |
| --- | ---: | ---: | ---: | --- |
| PHP syntax for Phase 21 files | 11 files | 0 | 0 | Passed |
| Phase 21 static/security | 4 | 0 | 0 | Automated Passed |
| Full Phase 21 filter | 4 | 0 product failures | 10 | SQLSTATE 2002 / port 3307 stopped |
| Phase 20 regression | 0 | 0 | All | Not rerun; DB unavailable |
| Phase 19 regression | 0 | 0 | All | Not rerun; DB unavailable |
| Phase 18 regression | 0 | 0 | All | Not rerun; DB unavailable |
| Phase 17 regression | 0 | 0 | All | Not rerun; DB unavailable |
| Phase 15 regression | 0 | 0 | All | Not rerun; DB unavailable |
| Egypt Localization | 0 | 0 | All | Not rerun; DB unavailable |
| Treasury Manual QA | 0 | 0 | All | Not rerun; DB unavailable |
| PublicWebsiteTest | 0 | 0 | All | Not rerun; DB unavailable |
| Full suite | 0 | 0 | All | Not rerun; DB unavailable |
| Pint | 1,401 files | 0 | 0 | Passed |
| Composer validate | 1 | 0 | 0 | Passed with PHP 8.2 |
| Vite build | 60 modules | 0 | 0 | Passed; nine existing runtime asset warnings |
| Config cache | 1 | 0 | 0 | Passed with temporary non-production key |
| Route cache | 1 | 0 | 0 | Passed |
| View cache | 1 | 0 | 0 | Passed |
| Route list | 515 | 0 | 0 | Passed |
| Schedule list | 6 | 0 | 0 | Passed |
| Production asset verification | 21 assets | 0 | 0 | Passed |
| UAT target validation | 0 | 0 | 1 | Database unavailable |
| Migration status / pretend / force | 0 | 0 | 1 | Not executed |
| UAT seed twice | 0 | 0 | 1 | Not executed |
| Production validation | 0 | 0 | 1 | Real production environment is outside this task |
| Migration scan / integrity | 0 | 0 | 1 | Database unavailable |
| HTTP smoke | 0 | 0 | 1 | No real server/database |
| Backup/restore | 0 | 0 | 1 | Database unavailable |
| Queue worker/retry | 0 | 0 | 1 | Database unavailable |

## 14. Manual UAT status

```text
Manual browser UAT: Pending
```

No screenshot or actual browser result was produced. The Arabic guide and CSV execution sheet are ready:

- `docs/uat/phase-21-full-uat-guide-ar.md`
- `docs/uat/phase-21-uat-results-template.csv`

## 15. Files

### Added

- `app/Console/Commands/ValidateUatEnvironment.php`
- `app/Services/UatEnvironmentGuard.php`
- `database/seeders/SevenWaysUatSeeder.php`
- `database/seeders/UatPerformanceSeeder.php`
- `tests/Concerns/UsesPhaseTwentyOneUat.php`
- seven Phase 21 Feature test classes
- `docs/uat/phase-21-uat-readiness-matrix.md`
- `docs/uat/phase-21-full-uat-guide-ar.md`
- `docs/uat/phase-21-uat-results-template.csv`
- `docs/uat/phase-21-defect-register.md`
- `docs/uat/phase-21-accounting-reconciliation.md`
- `docs/uat/release-candidate-checklist.md`
- `docs/phase-21-full-qa-uat-report.md`
- ignored local `.env.uat.local`

### Modified

- `.gitignore` — ignores `.env.*.local`.

No historical migration or production data was modified.

## 16. Remaining risks

| Risk | Severity | Evidence | Required action | Blocks go-live? |
| --- | --- | --- | --- | --- |
| MariaDB 3307 stopped | Blocker | TCP/Artisan connection refused | Environment owner starts approved isolated service after backup | Yes |
| Phase 21 DB tests not executed | Blocker | 10 environment-blocked tests | Provision and rerun all tests | Yes |
| Manual browser and owner sign-off absent | Blocker | No screenshots/actual results | Execute Arabic guide and sign results | Yes |
| Accounting reconciliation absent | Critical until tested | No UAT journals/documents | Complete zero-difference pack | Yes |
| Backup/restore UAT absent | Blocker | No UAT database | Complete drill and evidence | Yes |
| Queue/scheduler runtime absent | High until tested | Static checks only | Run bounded worker/retry/scheduler tests | Yes |
| Performance seeder not runtime-verified | Medium | DB unavailable | Backup, run, benchmark, review query evidence | Yes for performance acceptance |
| Real hosting sign-off absent | High | Phase 20 documented pending actions | Complete hosting checklist | Yes |

## 17. Release decision

```text
NO-GO — Go-Live is blocked
```

Reason: an open environment Blocker prevents UAT database provisioning, database E2E, manual browser evidence, accounting reconciliation, performance, queue/scheduler, backup/restore, and business-owner sign-off. The code and execution artifacts are prepared, but Phase 21 is not complete and no go-live action is authorized.
