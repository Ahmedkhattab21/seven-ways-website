# Final role-based navigation

## Source of truth

`SidebarNavigationService` is the only navigation builder. It combines:

- enabled module flags;
- active role profile;
- actual permissions;
- existing named routes;
- request route activity.

Blade renders the resolved structure only. Empty sections, disabled modules, unauthorized links, and duplicate URLs are removed before rendering.

## Role profiles

### Branch manager

Sees branch operations only: dashboard, customers, catalog, quotations, sales invoices, customer payments and returns, purchasing and basic inventory, cash boxes and sessions, and cash expenses/payments.

Vehicles remain available from the customer profile, but have no standalone sidebar entry. Standalone cash receipts, inventory movements, supplier invoices/payments, banking, accounting, administration, and setup progress are hidden.

### Accountant

Sees the accounting dashboard, receivables/payables, inventory financial views, full treasury and banking areas permitted to the role, accounting reports, and financial settings. Company legal data, branches, users, roles, and the full setup card are hidden.

A compact financial alert is shown only when relevant fiscal-year, sequence, accounting-setting, mapping, or opening-balance requirements are incomplete.

### General manager / company owner

Sees executive and branch dashboards, broad enabled business modules, financial areas, administration, and the complete setup-progress card.

### System administrator

Uses the manager navigation profile while remaining protected by actual route permissions and tenant rules.

## Vehicle access

Customer profiles show related vehicles, allow adding a vehicle with the customer preselected, and expose vehicle edit links when authorized. Quotations and invoices continue to treat the vehicle as optional.

## Safety

Navigation visibility never replaces backend authorization. Disabled modules remain blocked by module middleware, and hidden routes still enforce their existing permission, company, and branch policies.

## Payment-path audit

The standalone cash-receipt link is hidden from branch managers. The current customer-payment implementation records `CustomerPayment` and supports a separate allocation action, but it does not yet atomically create an invoice allocation, `CashReceipt`, active cash-session link, and journal entry. That workflow was not represented as complete by this navigation change and requires a separate finance-safe patch.

## Verification

- Sidebar role-profile regression tests: 7 passed.
- Three-role branch isolation tests: 4 passed.
- Customer and vehicle focused tests: 10 passed.
- Pint (changed PHP files): passed.
- Blade view cache: passed.
- `git diff --check`: passed, with line-ending notices only.
- Full suite: attempted twice, but exceeded both 120-second and 300-second execution limits without a final result.

## UAT result

The sidebar is centralized, role-aware, module-aware, permission-aware, deduplicated, and removes empty groups. Vehicle access is available from the customer profile with customer preselection. Manual browser UAT remains required for the three named accounts.
