# Phase 13 Stabilization Report

## Scope

هذا Patch يثبت دورة المشتريات والمخزون الخاصة بـ Phase 13 فقط قبل بدء
Phase 14 Accounting. لم تتم إضافة قيود يومية أو دفتر أستاذ أو ZATCA أو أي
موديول جديد، ولم تتغير Dependencies.

## Costing and Inventory

- أصبحت دالة التقريب المركزية تستخدم decimal arithmetic مع `PHP_ROUND_HALF_UP`
  بصورة دقيقة، بما في ذلك القيم السالبة.
- الاستلام بكمية مدفوعة `8` وكمية مجانية `1` وتكلفة إجمالية `80.0000`
  ينتج متوسط تكلفة `8.8889`.
- تحتفظ حركة المخزون بالتكلفة الإجمالية الأصلية `80.0000` بدل إعادة حسابها
  من المتوسط المقرب، ولذلك لا يظهر فرق `0.0001`.
- أضيف `inventory_batches.total_cost` لحفظ تكلفة الدفعة الأصلية، وأصبح متوسط
  تكلفة الدفعة مبنيًا على إجمالي التكلفة الفعلي.
- توزيع تكلفة الرولات يستخدم التقريب المركزي ويضع remainder التقريب على آخر
  رول، بحيث يظل مجموع تكاليف الرولات مساويًا لتكلفة السطر.
- Posting الاستلام والإرجاع محمي بـ transaction، row locks، `posted_at`،
  وحركات مرجعية فريدة، لذلك Retry لا يكرر المخزون أو التكلفة.
- تم اختبار rollback بعد فشل متعمد داخل transaction، ولم تبقَ حركة أو Batch
  أو تعديل مخزون جزئي.

## Supplier Type Contract

المصدر المركزي هو `config/purchasing.php`، والقيم المسموحة فقط:

```text
manufacturer
distributor
wholesaler
service_provider
other
```

يستخدم نفس العقد كل من `SupplierRequest` و`SupplierService` ونموذج المورد.
لا توجد قيمة `materials` في العقد أو الاختبارات المعدلة، ولا ينشئ Seeder
موردين بقيمة مختلفة.

## Attachments

- اختبار الرفع يستخدم ملف صورة حقيقي، ويتحقق من التخزين الخاص ومسار UUID.
- الوصول من Company أو Branch أخرى مرفوض.
- تم اختبار ملف نصي باسم `.jpg` وMIME مدعى `image/jpeg`، وتم رفضه.
- تم اختبار Category غير مسموح بها، وتم رفضها.

## Receipt, Batch, Roll, and Return Rules

- الكمية المجانية تدخل المخزون بتكلفة السطر المدفوعة من دون رفع إجمالي التكلفة.
- الكمية المرفوضة لا تدخل المخزون ولا fulfillment.
- الاستلام التراكمي الزائد يُرفض، والـ tolerance والـ override يعملان صراحة.
- المنتجات التي تتطلب Batch/Expiry لا تُرحل بدونها.
- Batch مطابق يتجمع بدل التكرار، والمرتجع يخصم منه، والمنتهي الصلاحية غير متاح.
- الرولات تُنشأ مرة واحدة بربطها بسطر الاستلام؛ مجموع المساحة والتكلفة مطابق.
- إرجاع رول غير مستخدم مسموح، والمستخدم مرفوض.
- Purchase Return يستخدم تكلفة الاستلام الأصلية، يمنع over-return، ويحافظ على
  التاريخ بعد Retry.

## Matching, Credits, Payments, and Status

- 3-way matching يقارن PO وGRN وInvoice داخل نفس Company/Branch، ويحسب الكمية
  المتاحة بعد الفواتير السابقة.
- لا يمكن Posting لفاتورة بها variance غير معتمد أو match ناقص عند تفعيل
  `require_matching`.
- Posting الفاتورة لا يغير المخزون ولا ينشئ Journal Entry.
- Credit Note المرتبطة بمرتجع تتحقق من أن المرتجع Posted ولنفس
  Company/Branch/Supplier.
- Currency الخاصة بالـ Credit Note أصبحت مملوكة للسيرفر ومثبتة في السجل.
- Payment allocation وreversal يستخدمان locks وإعادة بناء الرصيد الرسمي،
  ويمنعان over-allocation وdouble reversal وcross-supplier/currency/branch.
- أولوية الحالة هي: `void/cancelled` ثم `fully_credited` ثم `paid` ثم
  `overdue` ثم `partially_paid` ثم `posted`؛ الفاتورة المدفوعة أو المخصومة
  بالكامل لا تعود `overdue`.
- Supplier statement يفصل العملات والفروع المصرح بها، ويعرض الدفعات غير
  المخصصة ضمن رصيد المورد من دون احتساب allocations/reversals مرتين.
- Aging buckets وoverdue command تمت مراجعتهما؛ التشغيل المتكرر idempotent.
- أحداث Phase 13 تُجدول عبر `DB::afterCommit`، وRetry يتوقف قبل إنشاء callback
  جديد بسبب حالة السجل و`posted_at`.

## Database Migration Verification

أضيف Forward Migration فقط:

```text
2026_07_26_020000_add_total_cost_to_inventory_batches.php
```

يضيف `inventory_batches.total_cost` و`supplier_credit_notes.currency_id` مع
backfill وforeign key قابلين للعكس. تم التنفيذ على قاعدة الاختبار
`laravel_test_project_testing` فقط:

```text
020000 rollback: PASS
010000 rollback: PASS
010000 migrate: PASS
020000 migrate: PASS
```

لم يُستخدم `migrate:fresh` أو `db:wipe`، ولم تتغير قاعدة التطوير.

## Tests Added or Expanded

- `MoneyRoundingServiceTest`: التقريب half-up وعدم float drift.
- `PhaseThirteenPurchasingTest`: 13 سيناريو تغطي costing، free/rejected qty،
  idempotency، rollback، batches/expiry، rolls، returns، tolerance،
  3-way matching، credits، payments، status، statements، aging، tenant
  isolation، supplier types، وprivate/spoofed attachments.

## Command Results

```text
php artisan optimize:clear          PASS
php artisan test                    PASS (147 tests)
vendor/bin/pint --test              PASS (724 files)
composer validate                   PASS
npm.cmd run build                   PASS (Vite 4.5.14, 60 modules)
php artisan route:list              PASS (318 routes)
php artisan view:cache              PASS
git diff --check                    PASS
git status --short                  PASS (expected patch files only)
```

## Remaining Warnings

- Vite ما زال يعرض تحذيرات المشروع الحالية عن بعض مسارات الصور والخطوط
  المطلقة في `website`; تُترك للـ runtime ولم تتأثر بهذا Patch.
- Git على Windows يعرض تحذير تحويل `LF` إلى `CRLF` عند لمس الملفات؛ لا توجد
  whitespace errors.
- MariaDB المحلي لم يبدأ بإعداد XAMPP الافتراضي بسبب تلف سابق في Aria log.
  تم تشغيل الاختبارات بأمان باستخدام Aria runtime log directory منفصل من دون
  حذف أو تعديل ملفات البيانات الأصلية. يلزم إصلاح تشغيل MariaDB الافتراضي
  قبل الاعتماد على restart عادي من XAMPP.

## Changed Files

```text
app/Http/Requests/SupplierRequest.php
app/Models/InventoryBatch.php
app/Models/SupplierCreditNote.php
app/Services/GoodsReceiptPostingService.php
app/Services/InventoryService.php
app/Services/MoneyRoundingService.php
app/Services/PurchaseReturnPostingService.php
app/Services/PurchaseRollReceivingService.php
app/Services/RollService.php
app/Services/SupplierCreditNoteService.php
app/Services/SupplierInvoiceMatchingService.php
app/Services/SupplierInvoicePostingService.php
app/Services/SupplierInvoiceService.php
app/Services/SupplierPaymentAllocationService.php
app/Services/SupplierService.php
app/Services/SupplierStatementService.php
config/purchasing.php
database/migrations/2026_07_26_020000_add_total_cost_to_inventory_batches.php
resources/views/suppliers/form.blade.php
tests/Feature/PhaseThirteenPurchasingTest.php
tests/Unit/MoneyRoundingServiceTest.php
docs/phase-13-stabilization-report.md
```
