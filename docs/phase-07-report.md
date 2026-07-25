# Phase 07 — Stock Transfers

## Scope

Implemented only internal and inter-branch stock transfers. No purchasing, sales, services,
work orders, invoicing, payments, accounting, warranty, or ZATCA module was added.

## Transit design

- Every active branch has one idempotently seeded system warehouse with code `TRANSIT`.
- The warehouse belongs to the source branch, has `warehouse_type=transit` and
  `is_system=true`, and cannot be used for sales or work-order issues.
- Shipping creates append-only source-out and Transit-in movements.
- Receiving creates append-only Transit-out and destination-in movements.
- Normal users cannot select or operate on a system Transit warehouse manually.
- Company on-hand is preserved while goods are in transit. A confirmed shortage creates an
  explicit Transit-out movement and an open discrepancy.
- A rejected quantity remains in Transit and creates an open rejection discrepancy until a
  later controlled return/settlement action. It is not added to destination stock, and the
  transfer remains `partially_received` rather than closing as fully `received`.

## Database

Migrations:

- `2026_07_25_140000_create_stock_transfer_tables.php`
- `2026_07_25_141000_remove_soft_deletes_from_stock_transfers.php`

Created:

- `stock_transfers`
- `stock_transfer_items`
- `stock_transfer_discrepancies`

Also added `warehouses.is_system`. Transfer documents do not use soft delete. Company/number,
roll/item, scrap/item, status, branch, and discrepancy indexes/constraints were added.

## Models and relations

- `StockTransfer`: company, source/destination branch and warehouse, Transit warehouse,
  requester/approver/shipper/receiver, items, discrepancies, original/reversal transfers.
- `StockTransferItem`: transfer, product, unit, roll, scrap, and all requested/approved/
  prepared/shipped/received/rejected/damaged/shortage quantities.
- `StockTransferDiscrepancy`: transfer, item, reporter, resolver.
- Reverse relations were added to Branch, Warehouse, Product, InventoryRoll, and RollScrap.

## Workflow and services

State flow:

`draft → pending_approval → approved → preparing → ready_to_ship → shipped →
partially_received/received`

Alternative terminal states: `rejected`, `cancelled`, `reversed`.

Services:

- `StockTransferService`: create/edit draft and submit.
- `StockTransferApprovalService`: lock, validate availability, reserve, approve/reject.
- `StockTransferPreparationService`: partial/full preparation and availability recheck.
- `StockTransferShipmentService`: consume reservations and move quantity/roll/scrap to Transit.
- `StockTransferReceivingService`: partial receipt, destination/damaged receipt, shortages,
  rejection discrepancies, roll and scrap movement.
- `StockTransferCancellationService`: pre-shipment cancellation and reservation release.
- `StockTransferReversalService`: one formal reverse transfer with new append-only movements.
- `TransferDiscrepancyService`: report and resolve discrepancies.

All stock-changing operations use database transactions and ordered `lockForUpdate()` locks.
Stock balances are never edited outside `InventoryService`. Transfer reversals link new stock
and roll movements through `reversal_of_id`.

## Reservation, cost, and discrepancies

- Draft has no stock or reservation effect.
- Approval creates `inventory_reservations` and `transfer_reservation` movements.
- Reject/cancel releases active reservations.
- Ship consumes reservations and records `transfer_reservation_release`.
- Quantity cost is captured from source weighted-average cost at shipping and carried through
  Transit into destination without profit or extra freight.
- Rolls preserve their specific total and area cost.
- Scraps preserve their area-specific and total cost.
- Damage goes to an active destination damaged/quarantine warehouse.
- Shortage, damage, and rejection create open discrepancies.

Movement types used:

`transfer_reservation`, `transfer_reservation_release`, `transfer_out`,
`transfer_in_transit`, `transfer_out_transit`, `transfer_in`,
`transfer_damaged_in`, `transfer_reversal_out`, `transfer_reversal_in`.

## Authorization and UI

- Policies enforce company isolation and source/destination branch access.
- Inter-branch approval and reversal require a company administrator plus permission.
- Shipping is source-branch scoped; receiving is destination-branch scoped.
- Ten transfer permissions are seeded and assigned by role.
- Added RTL list, draft form, details/timeline, approval, preparation, shipping, partial
  receiving, discrepancy, cancellation, and reversal controls.
- Inventory dashboard summary uses live transfer and discrepancy data.
- Added 15 permission-protected transfer routes inside the existing authenticated tenant group.

No domain events were added; the existing audit log records all workflow actions.

## Seeders and factories

- `StockTransferSeeder`: permissions, role assignment, yearly `stock_transfer` sequence, and
  branch Transit warehouses; safe to rerun.
- `StockTransferFactory`
- `StockTransferItemFactory`
- `StockTransferDiscrepancyFactory`

No sample production transfers are seeded.

## Tests

Added `PhaseSevenStockTransferTest` with 8 tests covering:

- draft/no stock effect, same-warehouse prevention, and cross-company prevention;
- approval reservation, duplicate approval prevention, cancellation release;
- partial preparation;
- source → Transit → partial/full destination receipt with company quantity and cost checks;
- shortage discrepancy and over-receipt prevention;
- roll transfer, specific cost, one-time reversal, and `reversal_of_id`;
- scrap transfer without changing quantity balance.

True parallel contention was not executed on Windows. Ordered row locks, unique constraints,
transaction rollback, duplicate-operation tests, and sequential contention coverage are in place.

## Commands and results

| Command | Result |
|---|---|
| `php artisan migrate --force` | Passed; Phase 07 migrations applied |
| `php artisan db:seed --force` | Passed; permissions, sequences, Transit warehouses seeded |
| isolated testing DB migration | Passed |
| `php artisan test` | Passed: 68 tests |
| `vendor/bin/pint --test` | Passed |
| `composer validate` | Passed: `composer.json` valid |
| `npm.cmd run build` | Passed: 58 modules transformed |
| `php artisan route:list` | Passed: 111 routes, including 15 transfer routes |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed; only Git LF/CRLF notices |

## Remaining warnings and deferred items

- PHP CLI is 8.4.21 while this Laravel 9.52.21 project recommends PHP 8.2. Vendor packages emit
  PHP 8.4 implicit-nullability deprecation warnings. No dependency/Laravel upgrade was made.
- Native multi-process concurrency tests are deferred because the current Windows environment
  does not provide the project with a stable parallel test runner.
- Rejected stock remains safely isolated in Transit; an explicit return-to-source workflow can
  be added in a later approved phase.

## Phase 07 file inventory

- Schema: the two Phase 07 migrations.
- Domain: three transfer models and relation additions.
- Application: eight transfer services plus guarded inventory/reservation/movement integration.
- HTTP: controller, seven Form Requests, two policies, policy registration, and web routes.
- UI: `resources/views/stock-transfers/*` and inventory sidebar link.
- Data: transfer seeder, DatabaseSeeder registration, three factories.
- Verification: `tests/Feature/PhaseSevenStockTransferTest.php`.
