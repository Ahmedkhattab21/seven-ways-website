# Phase 19 — Executive Dashboards, Analytics and Final Reports

## 1. Scope

تم تنفيذ طبقة Analytics موحدة فوق مصادر البيانات الحالية، بدون Migration جديدة وبدون Stored
Balances أو تعديل مستندات وقيود تاريخية. يشمل النطاق:

- لوحة تنفيذية ولوحة مقارنة فروع.
- مركز تقارير موحد لعشر مجموعات تقارير.
- فلاتر موحدة وآمنة للشركة والفروع والفترة والعملات والكيانات التشغيلية.
- CSV وXLSX وPrint/Browser-PDF.
- صلاحيات وRole mappings عبر Seeder idempotent.
- اختبارات دقة وأمن وتصدير وعزل فروع.

خارج النطاق: ETA وZATCA، تحويل العملات التلقائي، إعادة تقييم التاريخ، وتغيير Posting Engine.

## 2. Existing Reports Audit

| Module | Existing Report/Dashboard | Data Source | Scope | Filters | Export | Accuracy/Gap |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | `DashboardController` | Placeholder cards | Company | None | No | `replace safely`: بقي Route القديم للتوافق، وأضيفت لوحة تنفيذية فعلية |
| Accounting | Trial Balance | `TrialBalanceService` / posted GL | Company/branch | Period, branch, account | CSV | `reuse`: opening/movement/closing and balance checks صحيحة |
| Accounting | General Ledger | `GeneralLedgerService` | Company/branch/account | Period, branch, currency | CSV | `reuse`: running balance مشتق |
| Accounting | Income Statement | `IncomeStatementService` | Company/branch | Period/comparison | CSV | `reuse`: account types/report mappings |
| Accounting | Balance Sheet | `BalanceSheetService` | Company/branch | As-of/comparison | CSV | `reuse`: يعرض فرق التوازن |
| Accounting | Cash Flow | `CashFlowStatementService` | Company/branch | Period | CSV | `reuse`: يعتمد على mappings الحالية |
| Accounting | Closing reports | Closing services/controllers | Company/period | Period/status | UI | `reuse`: readiness, locks, unposted sources |
| Sales/AR | Invoice/payment/statement pages | Official invoice snapshots and allocations | Company/branch | Customer/status/date | Print/UI | `extend`: أضيف summary/aging موحد وتصدير |
| Purchasing/AP | Registers, supplier statement, aging | Supplier documents and allocations | Company/branch | Supplier/status/date | UI | `extend`: أضيف summary/commitments موحد |
| Inventory | Stock/transfer/count/adjustment pages | `stock_balances`, movements and cost snapshots | Company/branch/warehouse | Product/warehouse/status | UI | `extend`: valuation/reorder/slow-moving summary |
| Treasury | Dashboard and operation reports | Posted journal lines plus operational workflow tables | Company/branch | Date/status/account | CSV/UI | `extend`: cash/bank ledger position and pending operations |
| Employee finance | Phase 17 reports | Accruals, settlements, claims and advances | Company/branch/employee | Date/status/employee | UI | `extend`: consolidated employee summary |
| Approvals | `CentralWorkflowReportController` | Approval tasks/actions | Company/branch | Status/date/module | UI | `extend`: aging and turnaround summary |
| Audit | `UnifiedAuditController` | Immutable masked audit events | Company/branch | Date/action/module | UI | `extend`: protected sensitive export |
| Notifications | Phase 18 operational report | Notifications | User/company | Status/type/severity | UI | `reuse`; لم يكرر داخل مركز التقارير |

لم تُحذف أو تُعدّل Routes التقارير المحاسبية القديمة. لا توجد تقارير مكررة تعتمد على أرصدة
مخزنة. الخطر الذي تم اكتشافه أثناء الاختبار كان استخدام أسماء أعمدة غير صحيحة للتحويلات
(`branch_id`, `destination_branch_id`) وتم إصلاحه إلى `from_branch_id`, `to_branch_id`.

## 3. Architecture

| Component | Responsibility |
| --- | --- |
| `ReportRegistry` | تعريف code/name/module/permission/filters/columns/sort/export/scope/currency/source/limits/sensitivity |
| `ReportFilterData` | اشتقاق company من `TenantContext`، حصر الفروع، الفترة السابقة، ومنع cross-branch |
| `AnalyticsReportService` | Queries ومؤشرات مشتقة من المصادر الرسمية |
| `ExecutiveDashboardService` | Current/previous snapshots ونسب التغير الآمنة |
| `ReportResult` | Summary + rows + source metadata |
| `ReportExportService` | CSV/XLSX آمن، metadata، ومنع formula injection |
| Controllers | فصل dashboard/report/export عن بعضها |

لا يقبل النظام `company_id` من الطلب. IDs التابعة للشركة تتحقق عبر validation، والفروع تتحقق
مرة أخرى مقابل `TenantContext`. Company-wide journal/audit rows لا تظهر إلا لمن يملك
`reports.view_all_branches`.

## 4. Data Sources and Accounting Accuracy

- Financial KPIs: `journal_entries` و`journal_entry_lines` بحالة `posted` فقط.
- Sales/purchases: document snapshots التي لها `accounting_posting_links.status=posted`.
- Credit notes تخصم في فترة الـcredit note وبنفس currency المختارة.
- Receivables/payables: الأرصدة الرسمية وإسنادات الدفع؛ Aging حسب `due_date`.
- Inventory valuation: `stock_balances.quantity * average_cost`، وليس سعر البيع.
- Cash/bank: `base_debit_amount - base_credit_amount` للحسابات النقدية والبنكية المرحلة.
- Employee finance: accruals/settlements/claims/advances؛ لا KPI balances.
- Approvals/audit: الجداول المركزية immutable من Phase 18.

التقرير المالي الموحد يعرض trial-balance check وbalance-sheet difference واسم
`Estimated Operating Result` حتى لا يدّعي صافي ربح قانوني. التقارير الرسمية التفصيلية
Trial Balance, General Ledger, Income Statement, Balance Sheet وCash Flow بقيت هي المرجع
لأنها تحتوي mappings وopening/running balance وreversal handling المختبرة.

## 5. Reports

| Module | Report route | Data Source | Filters | Export | Permission |
| --- | --- | --- | --- | --- | --- |
| Accounting | `/reports/financial` | Posted GL | Date/branch/currency | CSV/XLSX/Print/PDF | `reports.financial.view` |
| Sales | `/reports/sales` | Posted invoices/credits | Date/branch/customer/currency | Same | `reports.sales.view` |
| Receivables | `/reports/receivables` | Invoices/allocations | Branch/customer/currency | Same | `reports.receivables.view` |
| Purchasing | `/reports/purchases` | Posted supplier docs/open PO | Date/branch/supplier/currency | Same | `reports.purchases.view` |
| Payables | `/reports/payables` | Supplier balances/allocations | Branch/supplier/currency | Same | `reports.payables.view` |
| Inventory | `/reports/inventory` | Stock balances/cost | Branch/product/warehouse/N days | Same | `reports.inventory.view` |
| Treasury | `/reports/treasury` | Posted GL cash/bank | Date/branch | Same | `reports.treasury.view` |
| Employee finance | `/reports/employee-finance` | Phase 17 records | Date/branch/employee | Same | `reports.employee_finance.view` |
| Approvals | `/reports/approvals` | Approval tasks | Date/branch/status | Same | `reports.approvals.view` |
| Audit | `/reports/audit` | Unified audit events | Date/branch | Same, sensitive | `reports.audit.view` |

## 6. KPI Formulas

- Net sales before tax = invoice subtotal − invoice discounts − credit-note subtotal.
- Net sales after tax = invoice total − credit-note total.
- Average invoice = net invoice/credit total ÷ invoice count; `N/A` when count is zero.
- Estimated operating result = posted revenue − posted expense.
- Receivable/payable overdue = open balance whose due date is before the aging date.
- Stock valuation = on-hand quantity × official average cost snapshot.
- Cash/bank position = posted base debit − base credit.
- Commission outstanding = accrual − settled, excluding reversed/cancelled.
- Approval turnaround = completed time − requested time.
- Change % = `(current − previous) / abs(previous) × 100`; `N/A` when previous is zero.

Sales trend is aggregated at query level per month across all qualifying invoices and credit notes;
it is not calculated from the limited detail rows.

## 7. Filters and Currency

- Default range: current month; maximum: 366 days.
- Export row limit: 5,000.
- Slow-moving period: `movement_days`, default 90, range 1–3650.
- All-branch view requires `reports.view_all_branches`.
- Financial, inventory, cash and bank values are company-base currency.
- Sales/AR/AP/purchases use selected document currency without automatic conversion.
- Currency code/symbol comes from database and `MoneyFormatter`; no currency symbol is hardcoded in
  Blade or JavaScript.
- Sort fields are allow-listed by `ReportRegistry`.

## 8. Permissions and Seeder

Added permissions:

`dashboards.executive.view`, `dashboards.branch.view`,
`reports.financial.view`, `reports.sales.view`, `reports.purchases.view`,
`reports.inventory.view`, `reports.receivables.view`, `reports.payables.view`,
`reports.treasury.view`, `reports.employee_finance.view`, `reports.approvals.view`,
`reports.audit.view`, `reports.export`, `reports.export_sensitive`,
`reports.view_all_branches`.

`AnalyticsReportingSeeder` uses `updateOrCreate` and `syncWithoutDetaching`. It maps safe permissions
to existing role names, does not grant permissions directly to users, and creates no invoices,
journals, approvals, balances or treasury operations. Running it twice kept 15 analytics permissions
and zero journal/approval rows on clean-local.

## 9. Export Security

- Export re-runs the authorized backend query and never accepts browser result rows.
- Audit export additionally requires `reports.export_sensitive`.
- CSV/XLSX neutralize `=`, `+`, `-`, `@` formula prefixes while preserving valid negative numbers.
- XLSX is a real OpenXML ZIP workbook generated without a new dependency.
- No export is persisted under `public/`.
- Response is private/no-store.
- Print view is RTL, uses the existing local Cairo font and supports browser “Save as PDF”.
- Applied period, company, branches, currency, generation time and actor are included.

Server-side binary PDF generation was not added because the project has no compatible PDF package.
`format=pdf` returns the protected RTL print-to-PDF view and triggers browser printing.

## 10. UI and Routes

- `/dashboard/executive`: KPI cards, previous-period comparison, accessible monthly bar chart,
  operational alerts and report drill-down links.
- `/dashboard/branches`: per-authorized-branch comparison; branch managers cannot request another
  branch.
- `/reports/{report}` and `/reports/{report}/export`: report whitelist only.
- Routes are controller-backed and inherit `auth`, `active.user`, and `tenant`.
- RTL red/black theme, responsive tables/cards, empty states and print styles were added without a
  chart dependency.
- Existing `/dashboard` and accounting report URLs remain intact.

## 11. Performance and Cache Policy

- Queries filter company/branch/status/date before aggregation.
- Detail rows are capped; export is capped at 5,000.
- Aggregates avoid item/payment joins that duplicate document totals.
- Existing reporting indexes from Phase 14 are reused; no speculative index or migration was added.
- No cache was introduced. Therefore there is no stale-posting risk, permission-key leak or
  invalidation dependency. Cache can be evaluated later using measured production query plans.

## 12. Tests

| Command | Passed | Failed | Notes |
| --- | ---: | ---: | --- |
| `test --filter=PhaseNineteen` | 19 | 0 | KPI, GL, valuation, aging, export, route and scope |
| `test --filter=PhaseEighteen` | 12 | 0 | Workflow/audit regression |
| `test --filter=PhaseSeventeen` | 8 | 0 | Employee finance regression |
| `test --filter=PhaseFifteen` | 31 | 0 | Treasury/banking regression |
| `test --filter=EgyptLocalization` | 9 | 0 | Egypt/currency/tax regression |
| `test --filter=TreasuryManualQa` | 6 | 0 | QA and branch isolation |
| Full suite (latest) | 282 | 1 | Existing `PublicWebsiteTest`: services page now contains `muted` |
| Pint | 1373 files | 0 | Passed after scoped formatting |
| Composer validate | 1 | 0 | `composer.json is valid` |
| Vite | 60 modules | 0 | Build passed; existing runtime asset-resolution warnings |
| View cache | 1 | 0 | Passed |
| Route list | 513 routes | 0 | Four new routes |
| Schedule list | 6 jobs | 0 | Passed |
| Diff check | 1 | 0 | Passed |
| Smoke clean-local | 10 routes | 0 | All returned HTTP 200 |

Migrations on `seven_ways_clean_local@127.0.0.1:3307`: `--pretend`, `--force`, and status passed;
there was nothing pending. No command used port 3306.

## 13. Risks and Deferred Items

| Severity | Evidence | Required action | Blocks Phase 20 |
| --- | --- | --- | --- |
| Medium | PDF is browser print-to-PDF, not a server binary renderer | Add a reviewed Laravel 9/PHP 8.2-compatible PDF package only if automated server PDFs become mandatory | No |
| Medium | Detailed specialist registers remain in their original module pages instead of being duplicated in the unified center | Consolidate individual registers only after real usage/query profiling | No |
| Low | Export uses an in-memory XLSX writer with a 5,000-row cap | Add queued/chunked exports if measured volume exceeds the cap | No |
| Low | Vite reports existing unresolved runtime asset URLs in website CSS | Verify public website assets at deployment; smoke and existing asset tests pass | No |
| Low | No analytics cache | Profile production-sized data before introducing scoped invalidation-aware cache | No |
| High | Concurrent website changes made `PublicWebsiteTest` fail: the test forbids `muted`, while the current services HTML contains it | Reconcile the website video behavior and its test in the website change scope | Yes |

لا توجد Critical risks تخص Phase 19. يوجد High regression خارج ملفات Phase 19 لكنه يمنع قرار
GO طبقًا لشرط نجاح الـFull Suite. قاعدة البيانات التاريخية على 3306 ما زالت Recovery Pending
وخارج نطاق Phase 19.

## 14. Files

### New

- `app/Analytics/ReportFilterData.php`
- `app/Analytics/ReportRegistry.php`
- `app/Analytics/ReportResult.php`
- `app/Http/Controllers/AnalyticsReportController.php`
- `app/Http/Controllers/BranchDashboardController.php`
- `app/Http/Controllers/ExecutiveDashboardController.php`
- `app/Http/Controllers/ReportExportController.php`
- `app/Http/Requests/AnalyticsReportRequest.php`
- `app/Services/AnalyticsReportService.php`
- `app/Services/ExecutiveDashboardService.php`
- `app/Services/ReportExportService.php`
- `database/seeders/AnalyticsReportingSeeder.php`
- `resources/views/analytics/_filters.blade.php`
- `resources/views/analytics/branch-dashboard.blade.php`
- `resources/views/analytics/executive-dashboard.blade.php`
- `resources/views/analytics/print.blade.php`
- `resources/views/analytics/report.blade.php`
- `tests/Concerns/BuildsAnalyticsContext.php`
- Five `tests/Feature/PhaseNineteen*.php` classes

### Modified

- `app/Services/FinancialReportViewDataService.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/css/app.css`
- `resources/views/partials/sidebar.blade.php`
- `routes/web.php`

## 15. KPI Appendix

| KPI | Formula | Source | Included statuses | Exclusions | Currency behavior |
| --- | --- | --- | --- | --- | --- |
| Net sales before tax | subtotal − discount − credits subtotal | Invoice/credit snapshots + posting links | Posted links | Draft, cancelled, unposted | Selected document currency; no conversion |
| Tax | invoice tax − credit tax | Same | Posted links | Unposted/reversed | Selected document currency |
| Average invoice | net total ÷ invoice count | Same | Posted links | Zero count → N/A | Selected document currency |
| Estimated operating result | revenue credit balance − expense debit balance | GL lines/account types | Posted journals | Draft/unposted | Company base currency |
| Assets | debit − credit through as-of date | GL/account types | Posted journals | Future/draft | Company base currency |
| Receivables | sum open invoice balance | Invoice balances | Issued/partial/overdue | Paid/cancelled | Selected document currency |
| Receivable aging | open balance grouped by due-date age | Invoice due date | Open statuses | Paid/cancelled | Selected document currency |
| Payables | sum supplier balance due | Supplier invoices | Posted/partial/overdue | Paid/cancelled | Selected document currency |
| Inventory valuation | quantity × average cost | Official stock balance snapshot | Current balances | Sale price | Company base currency |
| Reorder items | `0 < available <= minimum_stock` | Stock/product | Active balance rows | Negative/zero counted separately | Non-monetary |
| Slow moving | no movement for N days | `last_movement_at` | Current balances | None | Non-monetary |
| Cash position | base debit − base credit | Posted GL cash accounts | Posted journals | Draft/reversed net naturally | Company base currency |
| Bank position | base debit − base credit | Posted GL bank accounts | Posted journals | Stored bank balance | Company base currency |
| Commission outstanding | commission − settled | Accruals | Non-reversed/non-cancelled | Reversed/cancelled | Document currency; no conversion |
| Approval aging | now/completed − requested | Approval tasks | Requested in period | Other company/branch | Non-monetary |
| Change percentage | delta ÷ absolute prior | Current/previous report snapshots | Same scope/status | Prior zero → N/A | Same report currency |

## 16. Readiness Decision

```text
[x] Phase 19 tests كلها Passed
[ ] Full suite كلها Passed — latest: 282 passed, 1 unrelated website failure
[x] Dashboard totals validated with known fixtures
[x] Trial balance and balance sheet checks passed
[x] Cross-company/branch isolation passed
[x] Export security passed
[x] Seeder idempotent and creates no operational rows
[x] No Stored Balances
[ ] No Critical or High unresolved risks — website regression remains High
[x] Migrations/status passed on port 3307
[x] Pint, Composer, Build, Views, Routes, Schedule and Diff passed
```

**NO-GO — Phase 20 is blocked**

Phase 19 itself and all of its targeted tests are green. Re-run the full suite after reconciling the
concurrent website video/test change. The historical database recovery remains a separate issue.
