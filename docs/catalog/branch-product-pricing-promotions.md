# Branch product pricing and promotions

## Ownership model

`products` remains the company-owned product master. A product is enabled for a branch through one
`branch_products` row, which controls availability, sellability, the default sales warehouse and
branch stock thresholds. The same product record and SKU are reused by every branch.

`branch_product_prices` is append-only commercial price history. A current price is resolved by
company, branch, product and document date, then by the highest priority and newest effective date.
Overlapping active periods with the same priority are rejected.

## Price resolution

`ProductPricingService` is the single runtime resolver used by quotations, direct sales invoices and
the catalog:

1. Validate company and branch scope.
2. Require an active, sellable product and an available/sellable branch link.
3. Resolve the effective branch price.
4. Resolve an eligible branch/general product promotion.
5. Return base price, promotion discount, final price, minimum price, warehouse and source IDs.

Saved quotation and invoice lines remain snapshots. Later price or promotion changes never recalculate
historical documents.

## Product promotions

Promotions may target one or more company products and optionally branches. Supported product discounts
are percentage, fixed discount and fixed promotional price. Product promotion date/branch/product
overlap is rejected. A fixed promotional price cannot increase the effective branch price.

## Existing products

Preview missing branch links without changing data:

```bash
php artisan catalog:provision-branch-products {branch_id}
```

Apply only the missing availability links:

```bash
php artisan catalog:provision-branch-products {branch_id} --apply
```

Use `--unavailable` to create disabled links for later review. The command is idempotent and does not
create prices, stock, accounting entries or sales documents.

## Permissions

- `products.manage_branch_availability`
- `products.manage_branch_prices`

Branch managers are limited by their branch access. Accountants retain read-only product access.

## Deployment

The migration is forward-only and additive. Review and back up the database before running the normal
deployment migration. Do not use `migrate:fresh` or `db:wipe`.

## Verification

- `php artisan test`: 478 passed.
- `php artisan test --filter=BranchProductPricingTest`: 3 passed.
- `vendor/bin/pint --test`: passed for all changed PHP files.
- `npm.cmd run build`: passed; existing unresolved public font/image URL warnings remain.
- `php artisan view:cache`, `composer validate`, route inspection and `git diff --check`: passed.
- The additive migration was applied successfully to the isolated `seven_ways_testing` database only.
- PHP 8.4 still emits dependency deprecation warnings from the Laravel 9 toolchain.

## Manual UAT

Manual UAT remains pending after the migration and permissions are deployed to `uat.local`. No UAT
products, historical quotations, invoices, stock balances or accounting entries were changed
automatically.
