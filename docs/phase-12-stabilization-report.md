# تقرير تثبيت Phase 12

## النتيجة

تم تثبيت Phase 12 على PHP 8.2 قبل بدء المشتريات، بدون إضافة Purchasing أو General Ledger أو Journal Entries أو ZATCA، وبدون ترقية Laravel أو أي dependency.

## أسباب أعطال Full Suite

- `AuthenticationUiTest`: الاختبار القديم كان يعتبر المبيعات موديولًا غير مفعل، بينما Phase 12 فعّل صفحات المبيعات. تم الحفاظ على حد الصلاحية والتأكد أن المستخدم غير المصرح له لا يرى روابط فواتير المبيعات، مع بقاء المشتريات "قريبًا".
- اختبارات Phase 11 وPhase 5 وبعض اختبارات Phase 10: كانت تستخدم `UploadedFile::fake()->image()` الذي يحتاج GD، بينما PHP 8.2 المستخدم لا يحتوي على GD. أضيف `fakeImage()` بملف PNG صالح بدون GD، مع بقاء اختبارات الخصوصية والعزل كما هي.
- Phase 9 وPhase 10: كانت تتوقع عدم وجود جدول `sales_invoices` قبل Phase 12. تم تغيير التوقع إلى عدم إنشاء أي فاتورة من الـflow الجاري اختباره، مع الحفاظ على نفس Business Boundary.
- Global Pint: تم تنسيق `lang/en/website.php` فقط، وإزالة importين غير مستخدمين نتجا عن استبدال صور الاختبار.

## Concurrency وIdempotency

- إصدار Direct Sale داخل transaction ويقفل الفاتورة والعناصر، مع guard على حالة الفاتورة و`issued_movement_id`. إعادة الإصدار لا تخصم المخزون ولا تنشئ `sales_issue` ثانية.
- تحويل العربون يقفل `appointment_deposits` ويتحقق من الحالة و`converted_payment_id`. يوجد unique constraint على `customer_payments.appointment_deposit_id`، والـevent بعد commit.
- تخصيص الدفع يقفل Payment وInvoice. الإجماليات لا تعتمد على increment/decrement؛ يعاد تجميعها من allocations الفعالة. تجاوز الرصيد وإعادة العكس مرفوضان.
- Credit Note يعيد فحص القيمة والكمية عند الإصدار بعد قفل الفاتورة، لذلك لا يمكن إصدار مسودتين متداخلتين تتجاوزان الأصل.
- Refund يقفل السجل وCredit Note، ويعيد تجميع المبالغ المستردة من refunds المعالجة. إعادة `process` مرفوضة ولا تنشئ أثرًا ثانيًا.
- Product Return أصبح له سجل `sales_product_returns` ومفتاح idempotency فريد لكل شركة. إعادة نفس الطلب تعيد نفس Credit Note بدون Stock Movement أو كمية مرتجعة إضافية.

## Rebuild أرصدة الفاتورة

`SalesInvoiceBalanceService` يعيد حساب:

- `paid_amount` من allocations غير المعكوسة.
- `credited_amount` من Credit Notes الصادرة.
- `refunded_amount` من refunds المعالجة.
- `balance_due = total - paid - credited`.
- الحالة بأولوية الحالات النهائية ثم `credited` ثم `paid` ثم `partially_paid` أو `overdue` أو `issued`.

الـRefund التشغيلي يستهلك رصيد Credit Note ويحدث `refunded_amount` للمعلومة، لكنه لا يعكس Allocation ولا يزيد `balance_due` ولا ينشئ Stock Movement أو Journal Entry.

## الاختبارات

اختبارات Phase 12 تغطي retry/double invocation للإصدار والإرجاع وتحويل العربون، منع double allocation وdouble reversal، تجاوز كمية الإرجاع، refund retry، rebuild من المصادر الرسمية، ومنع إصدار Credit Notes متداخلة، بجانب snapshot والعزل Cross-Company وعدم إنشاء GL.

النتيجة النهائية: **133 tests passed**.

## نتائج الأوامر

| الأمر | النتيجة |
|---|---|
| `php artisan optimize:clear` | نجح |
| `php artisan test` | نجح: 133 اختبار |
| `vendor/bin/pint --test` | نجح على 596 ملفًا |
| `composer validate` | نجح؛ `composer.json` صالح |
| `npm.cmd run build` | نجح؛ توجد تحذيرات Vite غير حاجبة لمسارات website assets المطلقة |
| `php artisan route:list` | نجح؛ 264 route |
| `php artisan view:cache` | نجح |
| `git diff --check` | نجح؛ تحذيرات LF/CRLF فقط |
| `git status --short` | لا توجد `.env` أو logs أو dumps أو `public/build` أو ملفات مؤقتة/مشوهة ضمن التغييرات |

## ملفات هذا الـPatch

- `app/Http/Controllers/SalesInvoiceController.php`
- `app/Http/Requests/SalesProductReturnRequest.php`
- `app/Models/SalesCreditNoteItem.php`
- `app/Models/SalesProductReturn.php`
- `app/Services/CustomerRefundService.php`
- `app/Services/DirectSaleInventoryService.php`
- `app/Services/PaymentAllocationService.php`
- `app/Services/SalesCreditNoteService.php`
- `app/Services/SalesInvoiceBalanceService.php`
- `app/Services/SalesProductReturnService.php`
- `database/migrations/2026_07_26_000000_create_sales_product_returns_table.php`
- `lang/en/website.php`
- `tests/TestCase.php`
- `tests/Feature/AuthenticationUiTest.php`
- `tests/Feature/PhaseFiveCrmTest.php`
- `tests/Feature/PhaseNineQuotationAppointmentTest.php`
- `tests/Feature/PhaseTenWorkOrderExecutionTest.php`
- `tests/Feature/PhaseElevenQualityDeliveryWarrantyTest.php`
- `tests/Feature/PhaseTwelveSalesReceivablesTest.php`

المهاجر الجديد additive فقط، لا يغير أو يحذف بيانات موجودة، و`down()` يحذف جدول سجل الإرجاع فقط. تم تشغيله على قاعدة الاختبارات، ولم يتم تشغيله على قاعدة التطوير أو الإنتاج.
