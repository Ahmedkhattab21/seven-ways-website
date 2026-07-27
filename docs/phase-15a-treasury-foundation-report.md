# Phase 15A — Banking and Treasury Foundation Report

## Scope

تم تنفيذ الأساس البنكي والخزينة فقط، بدون تنفيذ Bank Reconciliation أو Statement Import أو Transfer Posting أو أي قيود تحويل فعلية.

## Bank Design and Lifecycle

- يدعم `banks` بنوك النظام المشتركة وبنوك الشركة، مع كود فريد حسب النطاق ومنع حذف البنك المستخدم.
- دورة الحساب البنكي: `draft → active ↔ suspended → closed`.
- التفعيل، التعليق والإغلاق تتم داخل Transactions مع `lockForUpdate`.
- الحساب المغلق لا يقبل عمليات جديدة، والتحويلات المعلقة تمنع الإغلاق.
- يتم منع تغيير Currency أو GL Account بعد وجود نشاط مالي.
- يوجد Primary Bank Account واحد لكل Company/Currency.
- IBAN مشفر في قاعدة البيانات، ويستخدم hash للتحقق من التفرد داخل الشركة، ولا يظهر كاملًا في العرض العام.
- رقم الحساب يعرض Masked فقط، ولا توجد أرصدة مخزنة في `bank_accounts`.

## GL Linkage and Opening Balance

- الحساب البنكي يقبل GL Account من نفس الشركة فقط، Active وPosting وموسومًا `is_bank_account`.
- الخزينة تقبل GL Account من نفس الشركة فقط، Active وPosting وموسومًا `is_cash_account`.
- الرصيد الافتتاحي والحركة الدفترية مصدرهما Journal Engine الحالي؛ لم تتم إضافة Stored Balance أو Journal تلقائي.
- `TreasuryBalanceService` يحسب:
  - `book_balance` من Posted Journal Lines فقط.
  - `unposted_receipts`.
  - `unposted_payments`.
  - `pending_transfers`.
  - `available_book_balance`.
  - `last_reconciled_date`.
- الرصيد المتاح معلومة تشغيلية وليس Bank Statement Balance.

## Branch Access

- جدول `bank_account_branch_access` يربط الحساب البنكي بالفروع المصرح لها.
- يمنع تكرار Bank Account + Branch.
- يتم التحقق Backend من `can_view`, `can_receive`, `can_pay`, `can_transfer` ومن الحدود اليومية.
- Branch-specific Mapping يتقدم على Company Default.
- إلغاء الوصول يعطل السجل ويحافظ على التاريخ.

## Cash Boxes and Custodians

- دورة الخزينة: `draft → active ↔ suspended → closed`.
- Primary Cash Box واحدة لكل Branch/Currency.
- يتم رفض مشاركة GL Account بين خزائن فعالة.
- لا يوجد رصيد مخزن في `cash_boxes`.
- يتم حساب Cash Box Balance من GL مع Branch Dimension.
- يمنع الإغلاق مع Custodian فعال أو Transfer معلق.
- يتم منع تداخل فترات أمناء الخزينة، مع Primary Custodian واحد فعال في الوقت نفسه.
- الصلاحية العامة لا تكفي للعمل على الخزينة؛ يجب Custodian Assignment صالح أو Override مصرح.
- الإلغاء يسجل `revoked_by` و`revoked_at` ولا يحذف التاريخ.

## Payment Routing

- تم توسيع `payment_method_account_mappings` لدعم:
  - Bank Account أو Cash Box أو Direct GL Account، واحد فقط لكل Mapping.
  - `receipt`, `payment`, `refund`, `deposit`, `withdrawal`, `transfer`, `merchant_settlement`.
  - Branch-specific وCompany Default.
  - Clearing Account وFees Account وSettlement Days.
- `TreasuryAccountResolver` يتحقق من الشركة، الفرع، المستخدم، العملة، الحالة والصلاحيات قبل إعادة الحساب.
- تم ربط Customer Receipts وSupplier Payments وRefunds بالـresolver بدون تغيير القيود السابقة بأثر رجعي.

## Treasury Transfer Foundation

- تم إنشاء Draft Transfer Foundation بين Bank/Cash Box مع التحقق من اختلاف المصدر والوجهة والشركة والعملة والمبلغ.
- الحالات المنفذة: `draft`, `pending_approval`, `approved`, `cancelled`.
- Separation of Duties تمنع المنشئ من اعتماد التحويل.
- لا تعديل بعد الاعتماد.
- لا يتم إنشاء Journal Entry، ولا توجد حالة تنفيذ أو اكتمال فعلي في Phase 15A.

## Permissions, Policies and Audit

- أضيفت صلاحيات مستقلة للبنوك، الحسابات البنكية، البيانات الحساسة، الوصول للفروع، الخزائن، الأمناء، الربط، الأرصدة والتحويلات.
- تم توزيع الصلاحيات على الأدوار الموجودة؛ الأدوار التشغيلية غير المالية لا تحصل على صلاحيات Treasury افتراضيًا.
- أضيفت Policies لكل مورد وتم تسجيلها في `AuthServiceProvider`.
- Requests تمنع Browser من إرسال `company_id`, status, actors, timestamps, `journal_entry_id`, balances أو `document_number`.
- أحداث الإنشاء وتغيير الحالة والوصول والأمناء والربط والتحويلات تصدر بعد Commit.

## Migration and Seeder

- أضيفت migration:
  - `2026_07_26_070000_create_treasury_foundation_tables.php`
- الجداول:
  - `banks`
  - `bank_accounts`
  - `bank_account_branch_access`
  - `cash_boxes`
  - `cash_box_custodians`
  - `treasury_transfers`
- تم توسيع Payment Method Mappings بطريقة Forward-only.
- تم اختبار rollback/reapply بنجاح على Test DB فقط.
- `TreasuryFoundationSeeder` Idempotent ويضيف:
  - البنوك السعودية الأساسية كـSystem References.
  - Permissions وRole Mappings.
  - Primary Cash Box وحساب Cash GL مستقل لكل فرع عند عدم وجودهما.
- لا ينشئ Seeder أرصدة أو تحويلات أو Journals.

## Tests

أضيف `PhaseFifteenTreasuryFoundationTest` ويغطي:

- Schema وعدم وجود Stored Balances.
- GL validation وIBAN encryption/masking/uniqueness.
- Primary uniqueness وCurrency/GL locks.
- Branch access وعزل الشركات.
- Cash GL وPrimary Cash Box.
- Custodian overlap وBackend authorization.
- Mapping precedence.
- Treasury balance من Posted Journals.
- Transfer workflow foundation وSOD وعدم إنشاء Journal.
- Seeder idempotency وعدم إنشاء Journals أو Transfers.
- Policies/mass assignment وCross-company isolation.

النتائج:

- Phase 15A targeted: **7 passed**.
- Phase 14 Journal regression: **8 passed**.
- Full Suite: **187 passed**.

## Verification Commands

| Command | Result |
|---|---|
| `php artisan migrate --force` | Passed; migration 070 applied |
| `php artisan migrate:status` | Passed; accounting migrations 030–070 are Ran |
| Test DB rollback/reapply | Passed |
| `php artisan db:seed --force` | Passed twice; idempotency confirmed |
| `php artisan optimize:clear` | Passed |
| `php artisan test` | Passed — 187 tests |
| `vendor/bin/pint --test` | Passed — 1072 files |
| `composer validate` | Passed |
| `npm.cmd run build` | Passed — 60 modules transformed |
| `php artisan route:list` | Passed — 426 routes, including 22 Treasury routes |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed |
| `git status --short` | Reviewed; Phase 14 work remains present and preserved |

## Remaining Warnings and Explicit Exclusions

- Vite build ما زال يعرض تحذيرات assets موجودة مسبقًا لبعض fonts/images التي يتم حلها وقت التشغيل؛ الـbuild ناجح.
- Git يعرض تحذيرات LF/CRLF على بعض الملفات المعدلة؛ `git diff --check` ناجح.
- لم يتم تنفيذ Bank Statement Import أو Bank Reconciliation أو Automatic Matching.
- لم يتم تنفيذ Cheque Lifecycle أو Cash Deposit/Withdrawal أو Bank Fees/Interest Posting.
- لم يتم تنفيذ Transfer Posting أو Completion؛ التحويل الحالي Foundation واعتماد فقط.

## Phase 15A Files

### Modified

- `app/Http/Controllers/AccountingMappingController.php`
- `app/Http/Requests/AccountingFormRequest.php`
- `app/Models/PaymentMethodAccountMapping.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Services/AccountingPostingService.php`
- `app/Services/PostingAccountResolver.php`
- `database/seeders/AccountingPostingSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/partials/sidebar.blade.php`
- `routes/web.php`

### Added

- `app/Events/BankAccount*.php`
- `app/Events/CashBox*.php`
- `app/Events/Treasury*.php`
- `app/Http/Controllers/BankController.php`
- `app/Http/Controllers/BankAccountController.php`
- `app/Http/Controllers/CashBoxController.php`
- `app/Http/Controllers/TreasuryDashboardController.php`
- `app/Http/Controllers/TreasuryMappingController.php`
- `app/Http/Controllers/TreasuryTransferController.php`
- `app/Http/Requests/Bank*.php`
- `app/Http/Requests/CashBox*.php`
- `app/Http/Requests/Treasury*.php`
- `app/Models/Bank.php`
- `app/Models/BankAccount.php`
- `app/Models/BankAccountBranchAccess.php`
- `app/Models/CashBox.php`
- `app/Models/CashBoxCustodian.php`
- `app/Models/TreasuryTransfer.php`
- `app/Policies/BankPolicy.php`
- `app/Policies/BankAccountPolicy.php`
- `app/Policies/CashBoxPolicy.php`
- `app/Policies/CashBoxCustodianPolicy.php`
- `app/Policies/TreasuryMappingPolicy.php`
- `app/Policies/TreasuryTransferPolicy.php`
- `app/Services/BankService.php`
- `app/Services/BankAccountService.php`
- `app/Services/BankAccountAccessService.php`
- `app/Services/CashBoxService.php`
- `app/Services/CashBoxCustodianService.php`
- `app/Services/TreasuryAccountResolver.php`
- `app/Services/TreasuryBalanceService.php`
- `app/Services/TreasuryMappingService.php`
- `app/Services/TreasuryScopeService.php`
- `app/Services/TreasuryTransferService.php`
- `database/factories/BankFactory.php`
- `database/factories/BankAccountFactory.php`
- `database/factories/CashBoxFactory.php`
- `database/factories/CashBoxCustodianFactory.php`
- `database/factories/TreasuryTransferFactory.php`
- `database/migrations/2026_07_26_070000_create_treasury_foundation_tables.php`
- `database/seeders/TreasuryFoundationSeeder.php`
- `resources/views/treasury/*`
- `tests/Feature/PhaseFifteenTreasuryFoundationTest.php`
