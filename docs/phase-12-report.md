# تقرير Phase 12 — المبيعات والتحصيل التشغيلي

## النطاق المنفذ

تم تنفيذ أساس تشغيلي كامل للفواتير والتحصيل بدون أي General Ledger أو قيود يومية أو ZATCA:

- فواتير المبيعات وعناصرها مع Snapshot ثابت للعميل والمركبة والخدمة والسعر والضريبة والتكلفة.
- إنشاء فاتورة Draft من أمر عمل `delivered` فقط، مع رسوم الـRework المحملة على العميل، ومنع أكثر من فاتورة نهائية لنفس أمر العمل.
- البيع المباشر للمنتجات والخدمات المخصصة، مع منع Roll/Scrap، وعدم خصم المخزون في Draft.
- حساب السعر والخصم على مستوى السطر والفاتورة، ثم الضريبة، ثم التقريب من الـbackend فقط.
- دورة الحالات: `draft → pending_approval → approved → issued → partially_paid/paid/overdue/credited`، مع `cancelled` و`void`.
- فصل منشئ الفاتورة عن المعتمد حسب `config/sales.php`.
- إصدار البيع المباشر يخصم المخزون مرة واحدة فقط، والإرجاع يعيد المخزون وينشئ Credit Note Draft.
- تحويل العربون التشغيلي إلى Payment مرة واحدة، مع تخصيص تلقائي اختياري وبقاء الفائض Unallocated.
- Payments، الاعتماد، التخصيص الجزئي والكامل، وعكس التخصيص Append-only مع row locks.
- Credit Notes تعكس السعر والضريبة من Snapshot ولا تتجاوز الكمية أو القيمة الأصلية.
- Refund تشغيلي من رصيد Credit Note متاح، بدون قيد محاسبي.
- Customer Statement متعدد العملات مع الفواتير والمدفوعات والتخصيصات وعكسها والإشعارات والمبالغ المستردة.
- Aging حسب العملة وBuckets، وأمر `invoices:mark-overdue` يومي وidempotent.
- طباعة الفاتورة، Credit Note، الإيصال، وكشف الحساب.
- Events بعد Commit، Audit، Policies، Form Requests، صلاحيات، Routes، Sidebar، Seeder وFactories.

## تصميم البيانات

Migration:

`2026_07_25_190000_create_sales_receivables_tables.php`

ينشئ:

- `sales_invoices`
- `sales_invoice_items`
- `customer_payments`
- `payment_allocations`
- `sales_credit_notes`
- `sales_credit_note_items`
- `customer_refunds`

ويضيف إلى `appointment_deposits`:

- `converted_payment_id`
- `converted_at`

كل المستندات التشغيلية مربوطة بـCompany/Branch/Customer/Currency، والأرقام من `DocumentNumberService`. لا يتم حذف مستند مالي معالج؛ الإلغاء والعكس يحتفظان بالتاريخ.

## قواعد المخزون والحساب

- فاتورة Work Order لا تنشئ أي Stock Movement.
- البيع المباشر ينشئ `sales_issue` عند الإصدار فقط.
- `issued_movement_id` يمنع الخصم المكرر.
- Product Return ينشئ `sales_return` ويحدث `returned_quantity`.
- Roll/Scrap ممنوعان في Direct Sale.
- لا توجد جداول أو Services أو Events للـJournal Entries.

## الأمان

- جميع صفحات وإجراءات المبيعات داخل `auth + active.user + tenant`.
- Policies تتحقق من Company والفرع المتاح والصلاحية الدقيقة للإجراء.
- `submit`, `approve`, `issue`, `cancel`, `void`, `process` لا تعتمد على صلاحية عرض عامة.
- المدخلات لا تقبل Company أو Status أو Totals أو تكلفة أو أرقام مستندات من المستخدم.
- Invoice creator لا يعتمد نفس الفاتورة عند تفعيل Separation of Duties.
- التكلفة لا تظهر إلا لصلاحية `sales_invoices.view_cost`.

## الاختبارات المضافة

`PhaseTwelveSalesReceivablesTest` يغطي:

1. منع الفاتورة قبل تسليم أمر العمل.
2. Snapshot ثابت وعدم حركة مخزون لفاتورة أمر العمل.
3. منع فاتورتين نهائيتين لنفس أمر العمل.
4. حساب الخصم والضريبة من backend.
5. خصم Direct Sale مرة واحدة وإرجاع المنتج للمخزون.
6. الدفع والتخصيص الجزئي والكامل والعكس مع حفظ التاريخ.
7. تحويل العربون مرة واحدة والفائض Unallocated.
8. Credit Note والضريبة والRefund.
9. Statement وAging وOverdue.
10. العزل Cross-Company.
11. عدم وجود `journal_entries`.

النتيجة: **6 tests passed**.

## نتائج الأوامر

| الأمر | النتيجة |
|---|---|
| `php artisan migrate --force` | نجح على قاعدة التطوير |
| `php artisan db:seed --force` | نجح، و`SalesReceivablesSeeder` نجح |
| Migration rollback ثم migrate على DB اختبار | نجح |
| `php artisan test --filter=PhaseTwelveSalesReceivablesTest` عبر PHP 8.2 | نجح: 6 tests |
| `php artisan test` | لم يكتمل؛ أظهر failures في اختبارات Dirty Worktree سابقة: `AuthenticationUiTest`, Phase 11 وPhase 5 attachment، ثم توقف بسبب تشغيلات ملفات متزامنة |
| Pint لملفات Phase 12 | نجح |
| `vendor/bin/pint --test` | فشل في ملف خارج Phase 12 فقط: `lang/en/website.php` (`single_quote`) |
| `composer validate` | نجح |
| `npm.cmd run build` | نجح؛ تحذيرات Vite لمسارات website assets المطلقة فقط |
| `php artisan route:list` | نجح: 264 routes |
| `php artisan view:cache` | نجح |
| `git diff --check` | نجح |

البيئة:

- Laravel `9.52.21`
- PHP الافتراضي `8.4.21` ويعرض Deprecation Warnings من Dependencies القديمة.
- PHP المستخدم للاختبار المعزول الناجح: `8.2.12`.

## الملفات

### جديدة

- `app/Console/Commands/MarkInvoicesOverdue.php`
- Events الخاصة بالفواتير والمدفوعات والتخصيص والـCredit/Refund/Deposit.
- Controllers: `SalesInvoiceController`, `CustomerPaymentController`, `SalesCreditNoteController`, `CustomerRefundController`, `SalesReportController`.
- Requests الخاصة بالفواتير والمدفوعات والتخصيص والعكس والـCredit Note والRefund وProduct Return.
- Models: `SalesInvoice`, `SalesInvoiceItem`, `CustomerPayment`, `PaymentAllocation`, `SalesCreditNote`, `SalesCreditNoteItem`, `CustomerRefund`.
- Policies الخاصة بهذه النماذج.
- Services المذكورة في نطاق Phase 12.
- `config/sales.php`.
- Migration و`SalesReceivablesSeeder`.
- Factories الست المطلوبة.
- Views داخل:
  - `resources/views/sales-invoices`
  - `resources/views/customer-payments`
  - `resources/views/sales-credit-notes`
  - `resources/views/customer-refunds`
  - `resources/views/customer-statements`
  - `resources/views/sales-reports`
- `tests/Feature/PhaseTwelveSalesReceivablesTest.php`

### معدلة

- `app/Console/Kernel.php`
- `app/Models/AppointmentDeposit.php`
- `app/Providers/AuthServiceProvider.php`
- `config/inventory.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/customers/show.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/work-orders/show.blade.php`
- `routes/web.php`

## المخاطر والمؤجلات

- Approval مطبق بصورة أكثر تحفظًا: كل فاتورة تمر بالاعتماد، بينما تحليل thresholds التفصيلي يمكن توسيعه لاحقًا.
- أعمدة Quotation/Warranty Claim موجودة، وفاتورة Work Order تشمل Rework المحمل؛ adapters مستقلة للفوترة المباشرة من accepted quotation أو warranty claim مؤجلة.
- الـfull suite وPint العام ما زالا محجوبين بتغييرات Website/Phase 11 الموجودة مسبقًا خارج نطاق Phase 12، ولم يتم تعديلها.

## تأكيد النطاق

لم يتم تنفيذ General Ledger أو Chart of Accounts أو Journal Entries أو Bank Reconciliation أو ZATCA أو المشتريات أو الرواتب.
