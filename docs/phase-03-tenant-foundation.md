# Phase 03 — Multi-tenant foundation

## Scope

This patch adds the company, branch, branch settings, employee foundation, user tenancy fields,
roles, permissions, user roles, and branch access. It does not add commercial modules or alter
the established visual theme.

## Tenant rules

- `TenantContext` derives the company exclusively from the authenticated user.
- The selected branch is stored in `tenant.branch_id` in the server session.
- Branch switches are accepted only when the target is active, belongs to the user's company,
  and is available through the user's role, default branch, or `user_branch_access`.
- Company-scoped queries in the management controllers use the context company ID.
- Route permissions, policies, and tenant ownership checks are layered together.
- Disabled users and users in disabled companies are logged out.
- A main branch cannot be disabled until another active branch is made main.
- Main-branch changes use a transaction and row locks. MySQL has no portable partial unique
  constraint for `is_main`, so the one-main-branch invariant is enforced by `BranchService`.

## Seed data

`DatabaseSeeder` creates the Seven Ways company, a `MAIN` branch, branch settings, foundation
permissions, and reference roles. It is idempotent and restricted to `local`/`testing`.

No default password is shipped. A local admin is created only when both variables are configured:

```dotenv
SEED_ADMIN_NAME="Seven Ways Admin"
SEED_ADMIN_EMAIL=admin@example.test
SEED_ADMIN_PASSWORD=a-strong-local-password
```

## Remaining compatibility warning

The project remains on Laravel 9.52.21 and has not upgraded dependencies. PHP 8.4 emits
deprecation warnings from the current Symfony and Termwind packages. PHP 8.2 remains the
recommended runtime for this Laravel version. A Laravel/dependency upgrade is outside this patch.
