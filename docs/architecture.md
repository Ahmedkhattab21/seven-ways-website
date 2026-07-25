# Architecture

## Current shape

The project is a Laravel 9 modular monolith. Laravel routes and controllers remain the
HTTP delivery layer. Shared, reusable rules live under `App\Core`; future business
capabilities live under `App\Modules`.

The administration frontend uses Blade, vanilla CSS, and small vanilla JavaScript
behaviors compiled by Vite. Shared UI is organized under `resources/views/components`,
with authenticated and guest layouts under `resources/views/layouts`. Design tokens live
only in `resources/css/app.css`.

The existing `/` and `/api/welcome` behavior is retained during phase 01. `/api/user`
returns the authenticated user and is protected by `auth:sanctum`. New API endpoints use
the standard envelope through `App\Core\Http\ApiResponse`.

## Module boundaries

Planned modules are: Core, Identity, Companies, Branches, Customers, Vehicles, Products,
Inventory, Rolls, Services, Quotations, Appointments, WorkOrders, Inspections, Quality,
Sales, Purchases, Accounting, Warranties, Employees, Approvals, Notifications, Reports,
and Integrations.

Only create a module when it has working behavior. A module may own:

- `Actions` or `Services` for use cases and transactions.
- `DTOs` for explicit cross-layer input.
- `Http/Controllers`, `Http/Requests`, and `Http/Resources` for delivery concerns.
- `Models` for persistence and relationships.
- `Policies` for authorization.

Cross-module calls should use an application service or explicit contract. Controllers
must not contain business rules, and Eloquent models must not become workflow services.

## Identity and tenancy

Sanctum is installed; the current user model is the only identity implementation.
Company and branch tables, memberships, roles, and permissions are deferred.

Tenant-bound tables will carry `company_id` and, where applicable, `branch_id`. Scoping
must be opt-in per model and derived from authenticated access, never request-provided
ownership alone. System-global tables must not receive tenant scopes. An administration
context may bypass branch filters only through explicit authorization.

## Shared model policy

Future domain models may extend `BaseModel`, which assigns an ordered public UUID.
Database relations use internal bigint `id`; public URLs and APIs use unique indexed
`uuid`. `TracksAuthors` is opt-in only for tables that define nullable `created_by` and
`updated_by` foreign keys. Soft deletes are also opt-in and require a documented recovery
or retention reason.

## API and failures

New JSON APIs use:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {},
  "errors": null,
  "meta": {}
}
```

Validation, authentication, authorization, missing models, business rules, database
conflicts, HTTP failures, and production-only unexpected errors are mapped centrally.
Existing successful endpoint payloads remain unchanged until a versioned compatibility
decision is made.

## Document lifecycle

- `draft`: editable and has no stock or financial effect.
- `pending_approval`: awaiting an authorized decision.
- `approved`: approved but not necessarily posted.
- `posted`: has stock or accounting effect and is not edited directly.
- `cancelled`: reversed or voided through a controlled workflow.

Posted documents will be corrected using reversal/adjustment records, not destructive
updates. No transactional document module exists in phase 01.
