# Phase 06 — Inventory

## Scope

Implemented only product catalog and inventory foundations. No transfers, purchasing, services, work orders, sales, invoices, payments, accounting, warranties, or ZATCA functionality was added.

## Test database safety

- Connection: MySQL.
- Testing database: `laravel_test_project_testing` (no credentials are stored in this report).
- `phpunit.xml` explicitly selects the testing environment, MySQL, and the isolated database.
- `tests/TestCase.php` stops tests unless `APP_ENV=testing`, the connection is MySQL, the database differs from development, and its name contains `_test` or `_testing`.
- Development and testing schemas were migrated independently.

## Schema

Migration `2026_07_25_130000_create_inventory_tables.php` adds:

- Catalog: `product_categories`, `product_brands`, `products`, `film_product_profiles`, `product_unit_conversions`.
- Warehousing: `warehouses`, `stock_balances`, append-only `stock_movements`.
- Rolls: `inventory_rolls`, append-only `roll_movements`, `roll_scraps`.
- Reservations: `inventory_reservations`.
- Documents: `stock_opening_documents/items`, `stock_adjustments/items`, `inventory_counts/items`.

Quantities and dimensions use `decimal(19,6)`, conversions use `decimal(19,8)`, and money/cost uses `decimal(19,4)`. Tenant, branch, uniqueness, reference, and lookup indexes are included.

## Inventory rules

- `stock_movements` and `roll_movements` are the historical source of truth. Their models reject update and delete.
- `stock_balances` is changed only by `InventoryService`, inside transactions with `lockForUpdate`.
- Receipts recalculate weighted average as `(old quantity × old average + received quantity × receipt cost) ÷ new quantity`.
- Issues use the current average cost and cannot exceed available stock unless the branch setting explicitly allows negative stock.
- Reversal creates a linked opposite movement; it does not edit history.
- Reservation changes `reserved_quantity` and `available_quantity`, never on-hand quantity.
- Roll area is based on its actual width × actual length. Specific area cost is total cost ÷ actual area.
- Roll consumption and waste lock the roll, prevent negative remaining dimensions, calculate usable/waste cost, and close at the configured tolerance.
- Reusable scraps inherit the source roll area cost, deduct from the roll in the same transaction, and can be consumed once.
- Opening balances, adjustments, and counts remain inert as drafts and affect stock only when posted.

## Application layer

Services:

- `ProductService`, `ProductUnitConversionService`, `WarehouseService`
- `InventoryService`, `StockMovementService`
- `RollService`, `RollConsumptionService`, `RollScrapService`
- `InventoryReservationService`
- `StockOpeningService`, `StockAdjustmentService`, `InventoryCountService`

The services enforce tenant scope, transactions, ordered row locking, safe reference whitelists, numbering through `DocumentNumberService`, business exceptions, and audit events.

## Authorization and UI

- Added the requested inventory permissions and role distribution in `InventorySeeder`.
- Added the ten requested policies and registered them in `AuthServiceProvider`.
- Added RTL pages and sidebar links for products, categories, brands, warehouses, balances, movements, rolls, scraps, opening stock, adjustments, counts, reservations, and alerts.
- Posted movements have no delete action. Authorized reversal, roll consumption, scrap handling, document posting, count snapshot/post, and reservation release use explicit action routes.
- Alerts include low products, low rolls, expired/damaged/quarantined rolls, available scraps, and unposted counts.

## Seeders and factories

- `InventorySeeder` adds starter Seven Ways categories, one main warehouse per active branch, permissions, role grants, and sequences for product, stock movement, opening, adjustment, count, roll, and scrap.
- No fake stock, rolls, or balances are seeded.
- Factories: Product, Warehouse, InventoryRoll, StockAdjustment, InventoryCount.

## Verification

- `php artisan migrate --force`: passed on development; migration batch 6.
- isolated testing migration: passed on `laravel_test_project_testing`.
- `php artisan db:seed --force`: passed.
- `php artisan test`: **60 passed**.
- Phase 6 inventory tests: **7 passed** (weighted average/append-only, negative stock/reversal, roll area/cost/over-consumption, reservation/release, unit conversions, reusable scraps, authentication/permission).
- `vendor/bin/pint --test`: **236 files passed**.
- `composer validate`: passed; `composer.json is valid`.
- `npm.cmd run build`: passed; 58 modules transformed.
- `php artisan route:list`: passed; 96 routes.
- `php artisan view:cache`: passed.
- `git diff --check`: passed; only Windows LF-to-CRLF notices were emitted.

## Warnings and concurrency

- PHP CLI is 8.4.21 while the project-recommended runtime remains PHP 8.2. Laravel 9.52.21/Symfony vendor deprecation warnings remain on PHP 8.4. Dependencies were not upgraded.
- A true parallel process concurrency test was not performed on Windows. Concurrency safety relies on database transactions, `lockForUpdate`, deterministic ordering, and unique constraints; sequential contention and rollback behavior are covered.

## Changed files

- Test safety: `phpunit.xml`, `tests/TestCase.php`.
- Schema/config: inventory migration, `config/inventory.php`.
- Models: all catalog, warehouse, stock, roll, scrap, reservation, opening, adjustment, and count models under `app/Models`.
- Services: the inventory services listed above.
- HTTP/UI: `ProductController`, `ProductReferenceController`, `WarehouseController`, `InventoryController`, `InventoryActionController`, `InventoryDocumentController`, inventory Blade views, sidebar, and `routes/web.php`.
- Authorization: ten inventory policies, policy scope concern, `AuthServiceProvider`.
- Seed/data: `InventorySeeder`, `DatabaseSeeder`, reference sequence validation/view, and five factories.
- Tests: `tests/Feature/PhaseSixInventoryTest.php`.
