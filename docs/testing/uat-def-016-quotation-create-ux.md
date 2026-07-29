# UAT-DEF-016 — Quotation create UX

## Result

READY — Quotation creation is discoverable, defaults to the company currency, and shows customer-linked vehicles with clear labels.

## Findings and fixes

- The create button used Laravel Gate syntax (`@can`) with a permission string, while this project resolves permissions through `User::hasPermission()`. The button now uses the same custom permission check as routes and the sidebar.
- The currency select previously relied on the alphabetic currency query order, so AED appeared first. Selection now follows: validation `old()` value, stored quotation currency during edit, then the current company's `currency_id` for a new quotation.
- Customers now display `customer_code — name`.
- Vehicles now display plate/VIN, brand, model, customer code and customer name. Raw `customer_id` is not shown.
- Vehicle relations (`customer`, `brand`, `model`) are eager loaded.
- Lightweight JavaScript filters vehicle options by the selected customer and shows an Arabic empty-state message.
- `QuotationRequest` and `QuotationService` both preserve company/customer/vehicle ownership validation.
- Raw quotation statuses in the list and filter are translated to Arabic.

## Safety

- No quotation was created through SQL, a command or a seeder.
- No UAT record, permission, role, currency or historical document was modified.
- No migration or destructive database command was used.

## Verification

- Focused regression class: `Tests\Feature\UatDef016QuotationCreateUxTest`.
- Covers permission visibility and 403 behavior, currency priority, readable labels, eager loading, client filtering hooks, customer/company vehicle rejection, and Arabic statuses.
- `php artisan optimize:clear --env=uat.local`: passed.
- `php artisan test --filter=UatDef016QuotationCreateUxTest`: passed.
- `php artisan test --filter=Quotation`: passed.
- `php artisan test --filter=Permission`: passed.
- `php artisan test`: full suite passed.
- `vendor/bin/pint --test`: passed.
- `npm.cmd run build`: passed.
- `php artisan view:cache`: passed.
- `git diff --check`: passed.
- Remaining warnings are PHP 8.4 deprecation notices from Laravel 9-era dependencies.
