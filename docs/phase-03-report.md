# Phase 03 implementation report

## Before implementation

The project had Laravel 9.52.21, Sanctum authentication, the protected `/api/user`,
the health endpoint, the core response/UUID architecture, and the Phase 02 login/dashboard
shell. It had no companies, branches, employees, tenant context, roles, permissions, branch
access, or tenant-aware administration pages. `users` contained the standard Laravel identity
and password fields only.

## Design decisions

- The authenticated user's `company_id` is the only source of the current company.
- `TenantContext` is container-scoped and stores only a verified branch ID in the session.
- Company administrators can use every active branch in their company. Other users receive only
  their default/explicitly granted branches.
- Controllers scope queries by the context company and, for branch-level users, accessible branch IDs.
- Route permissions, policies, Form Requests, and ownership checks are intentionally layered.
- Branch main-state changes use a transaction and row locks. MySQL has no portable conditional
  unique constraint for one `is_main = 1` row, so `BranchService` owns that invariant.
- `company_id` and `branch_id` are not mass assignable on `User`; management writes them only after
  tenant-aware validation.
- A branch manager cannot grant an out-of-scope branch or assign `system_admin`, `company_owner`,
  or `general_manager`.
- A management user cannot change their own management roles/access.

## Database

Migrations created:

- `companies`
- `branches`
- `branch_settings`
- `employees`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`
- `user_branch_access`

The `users` table now has nullable `company_id`, nullable default `branch_id`, `phone`, `status`,
`last_login_at`, and `last_login_ip`. Existing users were preserved. No destructive migration or
schema reset was used.

## Models and relationships

Added `Company`, `Branch`, `BranchSetting`, `Employee`, `Role`, and `Permission`. `User` now relates
to its company, default branch, roles, and accessible branches. Company/branch/employee records use
the existing UUID base architecture where applicable.

## Authentication, tenant context, middleware, and policies

- Login accepts only `active` users and records login timestamp/IP.
- `EnsureActiveUser` rejects inactive/suspended users and users in inactive companies.
- `TenantContext` verifies company, branch, activity, and branch grants before accepting session state.
- Middleware aliases: `tenant`, `active.user`, `active.company`, `active.branch`,
  `company.access`, `branch.access`, and `permission`.
- Policies: `CompanyPolicy`, `BranchPolicy`, `UserPolicy`, and `RolePolicy`.
- The existing `/api/user` remains protected by `auth:sanctum`.

## Roles and permissions

Reference roles:

`system_admin`, `company_owner`, `general_manager`, `branch_manager`, `accountant`, `sales`,
`warehouse_keeper`, `technician`, `quality_controller`, and `receptionist`.

Phase permissions:

`dashboard.view`, `companies.view`, `companies.update`, `branches.view`, `branches.create`,
`branches.update`, `branches.disable`, `users.view`, `users.create`, `users.update`,
`users.disable`, `roles.view`, and `roles.manage`.

## Routes and interfaces

Added company settings, branch list/create/show/edit/disable/make-main, user
list/create/edit/disable, role list/create/permission-edit, and `POST /branch-context`.
The topbar shows a verified branch selector only when multiple branches are available and shows
the branch name for a single-branch user. The sidebar exposes only authorized Phase 03 pages;
future commercial modules remain disabled.

## Seeders

`FoundationPermissionSeeder` creates the reference roles and Phase 03 permissions.
`SevenWaysTenantSeeder` creates the Seven Ways company, its main branch, and branch settings.
It is restricted to local/testing and creates an administrator only when both strong credentials
are explicitly supplied through `SEED_ADMIN_EMAIL` and `SEED_ADMIN_PASSWORD`.

## Tests added

`TenantFoundationTest` covers guest protection, company/branch isolation, unauthorized branch
switches, authorized session switching, page permissions, main-branch disable protection,
branch-manager visibility, duplicate branch codes, inactive-user login, forbidden access without
permission, and self-management protection. The existing authentication test was adapted to the
new dashboard permission.

## Commands and observed results

- `php artisan migrate --force`: passed; six Phase 03 migrations applied without reset.
- `php artisan db:seed --force`: passed; permissions/roles and Seven Ways tenant seeded.
- `php artisan test`: passed, 26 tests.
- `php artisan route:list`: passed, 34 routes.
- `composer validate`: passed; `composer.json` is valid.
- `npm run build`: PowerShell blocked `npm.ps1`; the equivalent `npm.cmd run build` passed with
  Vite 4.5.14 and 58 transformed modules.
- `vendor/bin/pint --test`: Phase 03 files pass. The repository-wide command remains non-zero for
  four pre-existing style files: `app/Console/Kernel.php`, `app/Http/Controllers/UserController.php`,
  `app/Http/Controllers/WelcomeController.php`, and
  `app/Http/Middleware/RedirectIfAuthenticated.php`.

PHP 8.4 continues to emit dependency deprecation warnings from the current Laravel 9-era Symfony,
Termwind, Collision, and Pint dependency set. PHP 8.2 remains recommended. No dependency or Laravel
upgrade was performed.

## Phase 03 files

- Core: `app/Core/Support/UserStatus.php`, `app/Core/Tenancy/TenantContext.php`
- Models: `app/Models/{Company,Branch,BranchSetting,Employee,Role,Permission,User}.php`
- HTTP: Phase 03 controllers, Form Requests, middleware, and four policies under `app/`
- Service: `app/Services/BranchService.php`
- Database: six `2026_07_25_10*.php` migrations and three seeders
- UI: `resources/views/{settings,branches,users,roles}`, plus tenant-aware topbar/sidebar,
  shared badge/table updates, and `resources/css/app.css`
- Routes/providers: `routes/web.php`, `app/Http/Kernel.php`,
  `AppServiceProvider.php`, `AuthServiceProvider.php`
- Tests/docs/config: `tests/Feature/TenantFoundationTest.php`,
  `tests/Feature/AuthenticationUiTest.php`, `.env.example`,
  `docs/phase-03-tenant-foundation.md`, and this report

## Deferred and out of scope

No customer, vehicle, product, inventory, service, booking, work order, invoice, accounting,
supplier, purchase, warranty, ZATCA, payroll, or real dashboard metric module was implemented.
Laravel/dependency upgrades and the four unrelated legacy Pint issues were intentionally deferred.
