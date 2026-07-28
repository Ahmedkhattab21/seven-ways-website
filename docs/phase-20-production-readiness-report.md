# Phase 20 — Production Readiness Report

Date: 2026-07-28
Target: Seven Ways ERP, Laravel 9, PHP 8.2, MariaDB
Decision scope: readiness to start Phase 21 Full QA/UAT. This report does not authorize production deployment or go-live.

## 1. Executive summary

Phase 20 added production-safe environment defaults, explicit proxy and HTTPS handling, response security headers, live/readiness health checks, database-backed queue infrastructure, migration safety and integrity checks, restricted production seeding, private upload hardening, CI checks, deployment/rollback/backup/worker/scheduler runbooks, and automated tests.

The full application suite passed with 301 tests and no failures. A clean disposable deployment rehearsal and a separate backup/restore rehearsal passed on MariaDB port 3307. No historical documents, journals, balances, or production data were changed. No production deployment was performed.

**Readiness decision: GO for Phase 21 Full QA/UAT.**
**Go-live decision: NOT YET AUTHORIZED.** Hosting-specific credentials, workers, scheduler, SMTP, TLS/proxy settings, monitoring, and an approved production backup must be verified in the real hosting environment before go-live.

## 2. Audit and gap classification

| Area | Previous state | Requirement / gap | Severity | Action |
| --- | --- | --- | --- | --- |
| Environment template | Development-style defaults and local assumptions | Production-safe placeholders without secrets | High | Updated `.env.example`; added validation command and checklist |
| HTTPS and proxies | No explicit production enforcement; proxy trust depended on defaults | HTTPS only in production and no trust-all proxy behavior | High | Added `ForceHttps`; explicit `TRUSTED_PROXIES` configuration |
| Security headers | Not applied centrally | CSP, clickjacking, MIME sniffing, referrer and permissions policy | High | Added global `SecurityHeaders` middleware |
| Health checks | Safe API health existed | Separate liveness/readiness for operations | Medium | Added `/health` and `/health/ready`; preserved `/api/health` |
| Queue | Database queue configured but jobs table missing | Forward-only queue infrastructure | High | Added and applied `jobs` migration |
| Production seeders | Root seeder could include operational/demo data | Reference-only, idempotent production path | Critical | Added `ProductionReferenceSeeder`; production guard in `DatabaseSeeder` |
| Uploads | Private attachment service existed; some inline images accepted broad image types | Strict paths, safe MIME types and cross-tenant protection | High | Hardened service/controller validation and safe download headers |
| Migration safety | No automated destructive-operation gate | Review pending migrations before deploy | High | Added allowlisted read-only scanner and deployment/CI checks |
| Accounting/inventory integrity | Existing domain rules, no single pre-deploy summary | Read-only corruption/blocker report | High | Added `production:check-integrity` |
| Error responses | Existing 403/404/419/500/503 pages | Safe 422/429 pages and correlation reference | Medium | Added safe pages and optional reference |
| CI/CD | Deployment workflow could run from branch activity | Approval, explicit ref, dry run, gated release | Critical | Deployment is now manual; added isolated CI workflow |
| Backup/restore | No consolidated operational drill | Tested backup, restore and integrity process | Critical | Added runbook and completed disposable restore drill |
| Queue/scheduler operations | Application schedules existed, no operating instructions | Worker lifecycle and one scheduler entry | Medium | Added worker/scheduler runbooks and commands |
| Frontend dependencies | Production runtime has no npm vulnerabilities; build toolchain has advisories | Separate production exposure from dev-tool upgrade | Medium | Documented; no broad dependency upgrade in this phase |
| CSP compatibility | Existing inline Blade scripts/styles | Remove `unsafe-eval`; preserve current UI safely | Medium | CSP excludes `unsafe-eval`; retains `unsafe-inline` pending nonce migration |

## 3. Security hardening

- Production requests are redirected to HTTPS only when `APP_ENV=production` and `FORCE_HTTPS=true`.
- Trusted proxies are explicit; an empty setting trusts none.
- Global headers include CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, strict referrer policy, and permissions policy.
- Session cookies default to secure, HTTP-only and `SameSite=Lax` in the production template.
- CORS has no wildcard production default.
- Logs use daily rotation with configurable retention and a warning production default.
- Private attachments are resolved only under the owning company path, use sanitized download names, and are audited.
- Inspection/rework/delivery/warranty photos accept only JPEG, PNG and WebP MIME types; SVG and executable uploads are rejected.
- Cross-company access remains protected by existing policies and was regression-tested.
- Health responses expose only component status names, never credentials, connection strings, stack traces, paths, or exception messages.
- A repository scan found no tracked `.env`, private key, or real credential.

## 4. Environment and configuration

Recommended runtime:

| Setting | Value |
| --- | --- |
| PHP | 8.2 |
| Laravel | 9.x |
| Database | MariaDB/MySQL |
| Country | Egypt |
| Currency | EGP |
| Timezone | Africa/Cairo |
| Locale | Arabic with English fallback |
| Queue | Database |
| Session | Database |
| Debug | False |

`production:validate-env` checks environment name, debug mode, HTTPS URL, secure session settings, CORS origins, PHP version, application key presence, and database/queue settings without printing their values.

The first local migration-status probe inherited a stale local `.env` target at `127.0.0.1:3306` and was refused immediately. It performed no read or write. Every subsequent database command used the required isolated MariaDB service at `127.0.0.1:3307`.

## 5. Database, queues and scheduler

- Added forward-only `jobs` table migration; no table was dropped or renamed.
- Migration was previewed before application.
- Migration status is `Ran` in both `seven_ways_testing` and `seven_ways_clean_local`.
- `failed_jobs` was already present and retained.
- Queue workers are documented for Supervisor/systemd or an equivalent hosting process manager.
- Deployments restart workers with `queue:restart`.
- The scheduler has six registered jobs and requires one host cron entry calling `schedule:run` every minute.
- Integrity checks returned zero for unbalanced posted journals, broken posting links, orphan journal lines, stock formula mismatches, and invalid reservations.

## 6. Seeder safety

Production execution of `DatabaseSeeder` now calls only `ProductionReferenceSeeder`. It does not call tenant demo, operational, manual QA, analytics demo, or treasury QA seeders.

The production reference seeder was run twice against the clean local database. It remained idempotent and did not change counts for companies, branches, users, journal entries, sales invoices, supplier invoices, treasury transfers, cash receipts, or cash payments. EGP and SAR both remain available.

Existing local/testing guards on QA and operational seeders remain active. No operational documents, journals, stored balances, or historical records were created.

## 7. Health and operability

| Endpoint | Authentication | Purpose | Safe output |
| --- | --- | --- | --- |
| `/health` | Public | Process liveness | `{"status":"ok"}` |
| `/health/ready` | Public, rate-limited | DB, cache, private storage and queue readiness | Status labels only |
| `/api/health` | Public | Backward-compatible API health | Existing safe contract |

The disposable production rehearsal returned:

- `/` — 200
- `/login` — 200
- `/dashboard` — 302 to HTTPS login
- `/dashboard/executive` — 302 to HTTPS login
- `/health` — 200
- `/health/ready` — 200, all components ready

## 8. CI and deployment controls

CI now uses an isolated MariaDB service and PHP 8.2, installs dependencies, builds assets, runs migrations, runs the production reference seeder twice, scans migrations, executes the full test suite, creates framework caches, verifies assets, and clears caches.

The deployment workflow:

1. Runs only through manual dispatch.
2. Requires an explicit approved Git ref.
3. Defaults to dry-run mode.
4. Uses the protected GitHub production environment.
5. Validates the environment without exposing values.
6. Scans and previews migrations before applying them.
7. Applies migrations only after the gates pass.
8. Builds config/route/view caches and restarts queue workers.

The GitHub workflows were statically reviewed and their equivalent local steps passed. They were not executed on GitHub because no commit, push, or deployment was requested.

## 9. Deployment rehearsal

A full rehearsal was performed in a temporary application copy and a disposable database:

- Clean Composer production install with PHP 8.2: passed.
- Clean npm install and Vite build: passed.
- Production environment validation: passed.
- Migration safety scan: passed.
- Migration preview and application: passed.
- Production reference seed twice: passed.
- Integrity check: passed.
- Config, route and view caches: passed.
- Asset verification: 21 configured assets passed.
- HTTP smoke tests: passed.
- Temporary application directory and disposable database were removed after verification.

No real hosting server, production database, DNS, email account, or production secret was accessed.

## 10. Backup and restore rehearsal

A logical backup of the disposable rehearsal database was created:

- Size: 546,354 bytes
- Tables: 197

The first restore attempt used a compact dump that omitted foreign-key guard statements and failed on table order. This affected only the empty disposable restore database. It was recreated and restored using disabled foreign-key checks for that isolated restore session.

Final validation:

- 197 source and restored tables compared.
- Zero row-count differences.
- Zero pending migrations.
- Integrity checks all zero.
- Restored `/health/ready` returned 200 with database, cache, storage and queue ready.
- The exact disposable source/restore databases and temporary directory were removed.

The published runbook uses a standard dump retaining database safety statements and requires restore into a new empty database before any cutover.

## 11. Test and quality results

| Check | Result |
| --- | --- |
| Phase 20 tests | 17 passed |
| Phase 19 tests | 19 passed |
| Phase 18 tests | 12 passed |
| Phase 17 tests | 8 passed |
| Phase 15 tests | 31 passed |
| Egypt localization tests | 9 passed |
| Treasury manual QA tests | 6 passed |
| Public website tests | 21 passed |
| Full `php artisan test` | 301 passed, 0 failed, 77.95s |
| `vendor/bin/pint --test` | 1,389 files passed |
| `composer validate` | Passed |
| `npm.cmd run build` | Passed, 60 modules |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `php artisan route:list` | Passed, 515 routes |
| `php artisan schedule:list` | Passed, 6 schedules |
| `php artisan config:cache` | Passed |
| `php artisan route:cache` | Passed |
| `php artisan view:cache` | Passed |
| `production:verify-assets` | Passed, 21 assets |
| `git diff --check` | Passed; line-ending notices only |

## 12. Remaining risks and decisions

| Risk / decision | Effective severity | Phase 21 impact | Required action |
| --- | --- | --- | --- |
| Vite/build dependencies report three development-only advisories; production-only audit is clean | Medium | Does not block UAT | Never expose Vite dev server; upgrade Vite/plugin in a separate compatibility change |
| Composer clean install reports duplicate Flysystem local class candidates from locked packages | Low | Does not block UAT; runtime smoke passed | Resolve during a controlled dependency/framework compatibility review |
| CSP still permits inline scripts/styles for current Blade compatibility | Medium | Does not block UAT | Move inline code to nonce/hash-based policy before a later strict-CSP milestone |
| Real host proxy IPs, TLS, SMTP, filesystem permissions, worker manager, cron and monitoring are unknown | High for go-live only | Does not block isolated UAT | Hosting owner must complete and sign the production checklist |
| GitHub CI/deploy workflows have not run remotely | Medium | Run during Phase 21 branch/PR validation | Configure protected environment and execute CI/dry run |
| Historical database recovery remains a separate incident | High for that database only | Use the verified clean UAT database | Take backup and complete the separate non-destructive recovery procedure |
| Vite emitted nine existing runtime asset-resolution warnings | Low | Verified configured assets and pages still work | Review unused/legacy references during UAT |

There is no unresolved Critical or High application-code risk blocking Phase 21 UAT. The hosting decision above blocks go-live, not UAT.

## 13. Files changed by Phase 20

### Environment, configuration and workflows

- `.env.example` — production-safe placeholders.
- `.github/workflows/ci.yml` — isolated CI pipeline.
- `.github/workflows/deploy.yml` — manual, approval-gated dry-run deployment.
- `config/cors.php` — explicit allowed origins.
- `config/logging.php` — rotation and retention defaults.
- `config/production.php` — reviewed migration-operation allowlist.
- `config/security.php` — HTTPS, proxy and header policy.
- `config/session.php` — secure cookie defaults.

### Application

- `app/Console/Commands/CheckProductionIntegrity.php`
- `app/Console/Commands/ScanMigrationSafety.php`
- `app/Console/Commands/ValidateProductionEnvironment.php`
- `app/Console/Commands/VerifyProductionAssets.php`
- `app/Http/Controllers/Api/HealthController.php`
- `app/Http/Controllers/AttachmentController.php`
- `app/Http/Controllers/DeliveryController.php`
- `app/Http/Controllers/QualityCheckController.php`
- `app/Http/Controllers/ReworkOrderController.php`
- `app/Http/Controllers/VehicleInspectionController.php`
- `app/Http/Controllers/WarrantyClaimController.php`
- `app/Http/Kernel.php`
- `app/Http/Middleware/ForceHttps.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Http/Middleware/TrustProxies.php`
- `app/Providers/RouteServiceProvider.php`
- `app/Services/AttachmentService.php`
- `app/Services/MigrationSafetyScanner.php`
- `app/Services/ProductionEnvironmentValidator.php`
- `routes/web.php`

### Database

- `database/migrations/2026_07_28_100000_create_jobs_table.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/ProductionReferenceSeeder.php`

### Views

- `resources/views/components/error-page.blade.php`
- `resources/views/errors/422.blade.php`
- `resources/views/errors/429.blade.php`
- `resources/views/errors/500.blade.php`

### Tests

- `tests/Feature/PhaseTwentyFilesQueueSchedulerTest.php`
- `tests/Feature/PhaseTwentyHealthDeploymentTest.php`
- `tests/Feature/PhaseTwentyProductionSecurityTest.php`
- `tests/Feature/PhaseTwentySeederSafetyTest.php`

### Operations documentation

- `docs/production/backup-restore-runbook.md`
- `docs/production/deployment-runbook.md`
- `docs/production/initial-onboarding-runbook.md`
- `docs/production/operations-cheatsheet.md`
- `docs/production/production-env-checklist.md`
- `docs/production/queue-worker-runbook.md`
- `docs/production/rollback-runbook.md`
- `docs/production/scheduler-runbook.md`
- `docs/production/security-hardening-checklist.md`
- `docs/phase-20-production-readiness-report.md`

Pre-existing Phase 19 and public-website working-tree changes were preserved and were not attributed to Phase 20.

## 14. Final checklist

- [x] Production-safe environment template without secrets
- [x] HTTPS and explicit trusted-proxy policy
- [x] Central security headers
- [x] Safe liveness and readiness endpoints
- [x] Database queue migration
- [x] Worker and scheduler runbooks
- [x] Production reference-only seeding
- [x] Migration preview and destructive-operation scanner
- [x] Read-only accounting and inventory integrity checks
- [x] Private upload and download hardening
- [x] Safe error pages
- [x] CI and manual approval-gated deployment workflow
- [x] Clean deployment rehearsal
- [x] Backup and restore rehearsal
- [x] Full automated suite passed
- [x] No historical financial data changed
- [x] No production deployment or go-live performed
- [x] GO for Phase 21 Full QA/UAT
- [ ] Real hosting checklist approved before go-live
