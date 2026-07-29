# UAT-DEF-018A — Unified catalog center

## Previous state

The sidebar exposed separate product, service, package, category and brand entries. The main products-and-services entry opened the products list rather than a shared catalog center.

## Implemented state

- Added `GET /catalog` (`catalog.index`) as a permission-safe, company-scoped overview.
- The sidebar now has one `المنتجات والخدمات` entry and remains active across all existing catalog routes.
- Existing product, service, package, category and reference routes remain unchanged.
- Added reusable catalog navigation to list, create, edit and service detail screens.
- Added permission-aware quick actions, responsive tabs and active/total summary cards.
- Overview sections load the latest ten records only with eager-loaded relations.

## Records and pricing

- Products show `default_sale_price`; `standard_cost` is never presented as a selling price.
- Services show the current generic branch price, then the branch default price, or `غير مسعّرة`.
- Packages show the current generic branch package price or `غير مسعّرة`.
- Service packages are a first-class card and tab with active, total and unpriced counts.
- Package creation supports multiple unique services with quantities and one branch price in a single transaction.
- The package list shows service quantities, current branch/vehicle-size price, standalone total, saving, minimum price, duration, validity and availability.
- Quotation package selection is filtered by accessible branch, vehicle size and price validity. The authoritative package price is used; standalone service totals are informational only.
- Categories and brands show record counts and status without merging any database tables.

## Permissions and isolation

Each tab, action and query is controlled by the existing permissions. Direct access to the old routes remains protected by their current middleware. Catalog queries are restricted to the authenticated company and the selected accessible branch.

## Filters and performance

Product filters now include category, brand, status and sellable state. Service and package lists use the current/selected branch for live pricing. Package search and status filters were added. Full lists retain pagination and query strings; overview relations are eager loaded.

## Verification

Automated coverage: `UatDef018AUnifiedCatalogCenterTest` — 5 passed; `ServicePackagesCoreModuleTest` — 5 passed.

- Catalog, Product, Service, ServicePackage and Navigation filters: passed.
- Full Laravel suite: 387 passed.
- Pint check: passed.
- Vite production build: passed with existing unresolved website font/image runtime-reference warnings.
- Blade view cache: passed.
- `git diff --check`: passed; Git reported line-ending notices only.
- PHP 8.4 continues to emit dependency deprecation warnings from the Laravel 9-era Symfony/Termwind stack.

Manual UAT with the existing owner account verified `/catalog`, the unified sidebar link, all visible tabs, six summary cards, quick actions and current-branch context. No catalog record was created.

## Data safety

No tables were merged, no permissions were expanded, no migrations were added, and no product, service, package or historical record was created or modified.

**READY — Products, services, service packages, categories and brands are visible and manageable from one permission-safe catalog center with clear creation actions**
