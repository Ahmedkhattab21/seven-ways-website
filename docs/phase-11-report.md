# Phase 11 — Quality, Delivery and Warranty

## النتيجة

اكتملت دورة التشغيل من `awaiting_quality` حتى التسليم والضمان والمطالبات، مع عزل الشركة والفرع، صلاحيات مستقلة للتكلفة، مرفقات خاصة، وأحداث بعد نجاح الـtransaction. لم تتم إضافة فواتير أو مدفوعات أو محاسبة أو رواتب أو مشتريات أو ZATCA أو مطالبات موردين.

## الجودة والقوالب

- القوالب Versioned وقابلة للتخصيص للخدمة أو النوع أو الاستخدام العام، مع Default واحد لكل نطاق.
- بدء الفحص مسموح فقط لأمر `awaiting_quality`، برقم من `DocumentNumberService` وجولة متزايدة وSnapshot ثابت للقالب وعناصره.
- يمنع نجاح الفحص مع عنصر مطلوب غير محسوم، أو Critical failed، أو صورة فشل مطلوبة غير موجودة.
- الفحص المكتمل غير قابل للتعديل؛ التصحيح يتم بجولة جديدة.
- فصل المهام يمنع الفني المنفذ من اعتماد عمله.
- Pass ينقل الأمر إلى `ready_for_delivery`، وFail ينشئ Rework ويعيد الأمر إلى `in_progress`.

## Rework والتكلفة

- Rework يحتفظ بسبب المسؤولية والموظف والخدمات المتأثرة والتاريخ الكامل.
- المواد يعاد استخدامها من مسار Work Order الحالي عبر `rework_order_id` ومرجع حركة `rework_order`.
- الحجز لا يخصم، والإصدار/الرول/القصاصة يخصم مرة واحدة فقط، والحركات Append-only.
- تكلفة المواد والهالك والعمل الإضافية تجمع في Rework ثم تدخل إجمالي أمر العمل دون تكرار المصدر.
- إكمال Rework العادي يعيد الأمر إلى `awaiting_quality` ويتطلب جولة جديدة قبل التسليم.

## التسليم

- فحص التسليم لا ينشأ قبل `ready_for_delivery` ولا يكتمل دون الصورة النهائية.
- التسليم داخل Transaction يقفل الأمر، ويتحقق من Quality Pass والفحص والصورة والتوقيع الخاص وتسوية المواد والحجوزات.
- يسجل المستلم والتوقيع ووقت ومنفذ التسليم، ثم يحدّث أمر العمل إلى `delivered` والموعد إلى `completed`.
- لا يتم تخزين التوقيع Base64 ولا يتم إنشاء Invoice أو Payment.

## الضمان والتحقق

- الإصدار Idempotent للخدمات المؤهلة فقط، بأرقام `WAR` وToken عشوائي فريد بطول 64.
- عناصر الضمان Snapshot للخدمة والمنتج والرول/الدفعة والفني والتواريخ والشروط.
- `GET /warranty/verify/{token}` عام، محدود بـ`throttle:30,1`، ولا يعرض IDs أو VIN كاملًا أو بيانات شخصية أو تكاليف.
- شهادة HTML للطباعة توضح Active/Void/Expired ورابط التحقق. تم تجهيز QR foundation بالـtoken والرابط؛ رسم QR فعلي لم يضف حتى لا تدخل Dependency جديدة.

## المطالبات

- المطالبة تتحقق من الضمان وحالته والشركة والفرع، ومن تبعية العناصر لنفس الضمان.
- الفحص يتطلب صورة خاصة، والقرار يدعم Covered/Partial/Not covered/Goodwill.
- فصل المهام يمنع الموظف المسند إليه الفحص من اعتماد مطالبته بنفسه.
- Claim Rework مستقل ولا يعيد أمر العمل الأصلي من `delivered`.
- انتهاء Rework لا يغلق المطالبة تلقائيًا؛ تصبح `under_review`، ولا تصبح `resolved` إلا بصلاحية مستقلة وبعد اكتمال كل Rework ووجود `rework_after`.

## الأحداث والصلاحيات

- الأحداث المطلوبة للجودة وRework والتسليم والضمان والمطالبة ترسل بـ`DB::afterCommit`.
- السياسات: `QualityChecklistTemplatePolicy`, `QualityCheckPolicy`, `ReworkOrderPolicy`, `WarrantyPolicy`, `WarrantyClaimPolicy`، مع منع Cross-Company/Cross-Branch.
- Seeder يضيف صلاحيات المرحلة وأرقام `QC/RW/WAR/WCL` وقوالب عامة وPPF وعازل بصورة Idempotent، دون Checks أو Claims وهمية.
- Factories أضيفت للجودة وRework والضمان والمطالبات.

## قاعدة البيانات

- Migration: `2026_07_25_180000_create_quality_delivery_warranty_tables.php`.
- الجداول الجديدة: قوالب وعناصر الجودة، الفحوص وعناصرها، Rework وخدماته، الضمان وعناصره، المطالبات وعناصرها.
- أضيف `rework_order_id` للمواد، وحقول التسليم/المطالبة للفحوص وأوامر العمل.
- أضيفت مفاتيح أجنبية وفهارس للنطاق والحالة والتواريخ والبحث، مع Unique للـUUID والأرقام والـQR token.
- لا يوجد Drop أو Rename لبيانات سابقة. تم اختبار rollback ثم migrate بنجاح على قاعدة الاختبار.

## الاختبارات والأوامر

- `php artisan migrate --force`: ناجح، Nothing to migrate بعد تطبيق Migration.
- `php artisan db:seed --force`: ناجح، بما فيه `QualityWarrantySeeder`.
- `php artisan test`: ناجح، **115 passed**.
- `vendor/bin/pint --test`: ناجح، **517 files**.
- `composer validate`: ناجح.
- `npm.cmd run build`: ناجح، Vite 4.5.14 و58 module.
- `php artisan route:list`: ناجح، **228 routes**.
- `php artisan view:cache`: ناجح.
- `git diff --check`: ناجح؛ لا توجد whitespace errors.
- Migration rollback/re-migrate على قاعدة الاختبار: ناجح.

## الملفات المعدلة للمرحلة

- Migration وSeeder: `database/migrations/2026_07_25_180000_create_quality_delivery_warranty_tables.php`, `database/seeders/QualityWarrantySeeder.php`, `database/seeders/DatabaseSeeder.php`.
- Models: ملفات `Quality*`, `Rework*`, `Warranty*` وتحديث علاقات Work Order/Material/Vehicle Inspection.
- Services: ملفات `Quality*`, `Rework*`, `Delivery*`, `Warranty*` وتكامل خدمات المخزون والتكلفة الحالية.
- Controllers/Requests/Policies/Events: ملفات المرحلة المقابلة تحت `app/`.
- Config: `config/quality.php`, `config/warranty.php`, `config/inventory.php`.
- Routes/UI: `routes/web.php`, `resources/views/{quality,rework,deliveries,warranties,warranty-claims}`, وتحديث Sidebar وWork Order.
- Tests/Factories: `tests/Feature/PhaseElevenQualityDeliveryWarrantyTest.php` وFactories المرحلة.

## التحذيرات والمؤجلات

- PHP 8.4 يعرض Deprecation warnings من Laravel 9/Symfony/Termwind/Collision/Pint vendor؛ PHP 8.2 ما زال الموصى به. لم تتم ترقية Laravel أو Dependencies.
- رسم QR كصورة لم يضف؛ الأساس الآمن والـtoken ورابط التحقق جاهزون.
- لا PDF dependency، ولا أي موديول مالي أو مشتريات أو رواتب أو ZATCA أو Supplier Claims.
