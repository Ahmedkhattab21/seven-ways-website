# Phase 13 — Purchasing Foundation Report

## Result

Phase 13 was implemented as a tenant- and branch-scoped operational purchasing module on Laravel 9.52.21 / PHP 8.2.12. It does not create general-ledger records, journal entries, ZATCA integration, supplier portal, contracts, landed cost, or purchase forecasting.

The Phase 12 prerequisite migration was already applied as batch 15. The purchasing migration was applied forward-only as batch 16. No existing table was dropped, renamed, truncated, or rebuilt, and no `migrate:fresh`/`db:wipe` command was used.

## Data design

The forward migration creates:

- Suppliers, contacts, addresses, and supplier-product mappings.
- Purchase requisitions and lines.
- Version-ready purchase orders and lines (`parent_purchase_order_id`, `version_number`).
- Goods receipts and lines.
- Inventory batches.
- Purchase returns and lines.
- Supplier invoices, lines, and three-way matching results.
- Supplier credit notes and lines.
- Supplier payments and reversible allocations.

Tenant, branch, status, document-number, supplier invoice, and operational lookup indexes were added. Supplier codes and tax numbers are unique per company; document numbers are unique within their company/branch scope; supplier invoice references are unique per company and supplier. Foreign keys use restrictive deletion for operational history and cascades only for true child rows.

`supplier_addresses.country_id` and `city_id` remain nullable indexed identifiers without foreign keys because the current project does not contain countries/cities tables.

## Suppliers

Suppliers are company-owned, numbered from a sequence, soft-deletable only before purchasing history exists, and protected against cross-company access. Status supports active, inactive, suspended, and blocked. Contacts and addresses enforce one primary record per relevant scope. Supplier-product mappings hold supplier SKU, purchase unit, conversion, lead time, prices, minimum quantity, and preference.

## Purchase requisitions

Draft requisitions have no stock effect. Submission locks the workflow into pending approval. Approval is separated from creation, supports approved quantities, requires a reason above requested quantity, and preserves rejected/cancelled lines. Approved quantities feed purchase orders and ordered quantities are rebuilt safely.

## Purchase orders

Supplier identity, tax number, and address are snapshotted. Pricing, discounts, allocated global discount, tax, shipping, charges, and rounding are calculated by backend services. Approval blocks inactive suppliers and enforces separation of duties and price-variance permission. Sending is operational only and creates no stock or accounting movement. Sent records are not exposed for direct editing; parent/version columns preserve the amendment path without a contracts workflow.

## Goods receipts and inventory

Purchase orders do not affect stock. Stock changes only when an accepted receipt is posted.

- Partial receipt is supported.
- Accepted quantity plus free quantity enters stock.
- Rejected quantity stays outside available stock.
- A receipt with rejected quantity is marked `partially_rejected`.
- Free units use the configured distributed-cost policy: accepted line cost is spread over accepted plus free stock units.
- Normal quantity products reuse `InventoryService`, including weighted-average costing.
- Posting is transactionally locked and cannot run twice.
- Over-receipt requires tolerance or an explicit override permission.

Roll receipts reuse `RollService`; each roll is recorded once with supplier, PO item, receipt item, dimensions, area, and allocated cost. Batch receipts require configured batch/expiry data, reject expired batches, and aggregate repeated batches using locked quantities and weighted cost rather than overwriting them.

Inspection attachments use the private local disk, generated stored names, fixed categories, MIME/extension validation, and attachment authorization. Cross-company or inaccessible-branch downloads are forbidden. Damaged inspected goods require a private damage attachment.

## Purchase returns

Returns are linked to the original receipt where supplied, cannot exceed accepted quantity, and use the original receipt/roll cost. Only full unused rolls can be returned. Batch available quantity is reduced under a lock. Posting creates one outbound stock movement, updates PO returned quantity, preserves history, and cannot be repeated.

## Supplier invoices and matching

Supplier invoice totals are backend-owned. PO/receipt-linked invoices perform line matching for quantity, price, tax, and unmatched/over-invoiced states. Variances require explicit approval before posting. Posting updates operational invoiced quantities and balances but does not move stock or create accounting entries.

Supplier credit notes reduce the official invoice balance only after approval/posting and cannot exceed the current balance.

## Supplier payments, statement, and aging

Supplier payments are operational records with draft, approved, processed, partially allocated, and allocated states. Allocations require the same company, supplier, currency, and accessible branch; cannot exceed either available payment or invoice balance; and are reversed rather than deleted. Invoice and payment balances are rebuilt from active official allocations and posted credit notes.

Supplier statements are currency-specific and AP aging returns current, 1–30, 31–60, 61–90, and 90+ buckets. `supplier-invoices:mark-overdue` is idempotent and scheduled daily without overlap.

## Domain events, audit, authorization

Events cover supplier creation, requisition submission/approval/rejection, PO creation/approval/send, receipt creation/partial rejection/posting, return posting, invoice creation/approval/posting/overdue, payment processing/allocation/reversal, and credit-note posting. Events are dispatched after commit.

Policies and route middleware enforce company, accessible-branch, permission, document-state, cost visibility, inspection, approval, posting, allocation, and reversal rules. Form Requests whitelist operational fields; tenant IDs, actor IDs, statuses, totals, snapshots, paths, and calculated values remain server-owned.

## UI, routes, seeders, and factories

RTL pages and sidebar entries were added for suppliers, requisitions, orders, receipts, returns, supplier invoices, payments, credit notes, statements, aging, and operational purchasing reports. The full route list contains 318 routes.

`PurchasingSeeder` adds permissions, role mappings, and eight branch document sequences without fake production documents. It completed through `DatabaseSeeder` and also completed twice consecutively, confirming idempotency. Factories were added for all major purchasing records.

## Tests

New Phase 13 feature tests cover:

- Separation of duties and no stock effect from requisitions/orders.
- Backend PO totals and snapshots.
- Partial receipt, rejected/free quantities, weighted average, and double-post protection.
- Return-at-receipt-cost and double-return protection.
- Supplier invoice/payment allocation/reversal and official balance rebuild.
- No stock or journal effect from supplier invoices/payments.
- Supplier tenant isolation.
- Private receipt attachments and cross-company download denial.

Final full suite: **139 passed**. Phase 13 focused suite after the last receipt-state adjustment: **6 passed**.

## Command results

| Command | Result |
|---|---|
| `php artisan migrate --force` | Passed; purchasing migration applied as batch 16 |
| `php artisan db:seed --force` | Passed; all seeders including purchasing |
| `php artisan optimize:clear` | Passed |
| `php artisan test` | Passed; 139 tests |
| `vendor/bin/pint --test` | Passed; 722 files |
| `composer validate` | Passed; `composer.json` valid |
| `npm.cmd run build` | Passed; Vite 4.5.14, 60 modules |
| `php artisan route:list` | Passed; 318 routes |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed |
| `git status --short` | Reviewed; only existing worktree changes plus Phase 13 files |

Warnings:

- Windows reports that TTY mode is unsupported when running PHPUnit.
- Vite leaves existing absolute website font/image URLs for runtime resolution. The build succeeds.
- Git reports LF-to-CRLF conversion notices for several tracked files. No whitespace errors were found.

## Files

Modified integration files:

- `app/Console/Kernel.php`
- `app/Http/Controllers/AttachmentController.php`
- `app/Policies/AttachmentPolicy.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Services/InventoryService.php`
- `app/Services/RollService.php`
- `config/inventory.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/partials/sidebar.blade.php`
- `routes/web.php`
- `tests/Feature/AuthenticationUiTest.php`

Added Phase 13 groups:

- `app/Console/Commands/MarkSupplierInvoicesOverdue.php`
- `app/Events/*` purchasing events
- `app/Http/Controllers/*` purchasing controllers
- `app/Http/Requests/*` purchasing requests
- `app/Models/*` purchasing models
- `app/Policies/*` purchasing policies and concern
- `app/Services/*` purchasing services
- `config/purchasing.php`
- `database/factories/*` purchasing factories
- `database/migrations/2026_07_26_010000_create_purchasing_tables.php`
- `database/seeders/PurchasingSeeder.php`
- `resources/views/{suppliers,purchase-requisitions,purchase-orders,goods-receipts,purchase-returns,supplier-invoices,supplier-payments,supplier-credit-notes,purchasing,purchasing-reports}/*`
- `tests/Feature/PhaseThirteenPurchasingTest.php`
- `docs/phase-13-report.md`

## Rollback and deferred scope

The migration `down()` removes only Phase 13 tables/columns in reverse dependency order. Rollback would delete Phase 13 operational data and must therefore be preceded by a database backup; it does not alter Phase 12 tables beyond removing the three nullable purchasing links added to `inventory_rolls`.

Deferred by scope: GL, journal entries, supplier liabilities, VAT returns, ZATCA, supplier portal, purchasing contracts, landed cost, purchase forecasting, real outbound email, and complex amendment contracts.
