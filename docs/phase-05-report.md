# تقرير المرحلة الخامسة — Customers, Vehicles & CRM

## النطاق المنفذ

تم تنفيذ المرحلة الخامسة فقط: العملاء، جهات الاتصال، العناوين، السيارات، سجل الملكية، العملاء المحتملون، المتابعات، التحويل، الملاحظات، المرفقات الخاصة، وواجهة Empty State لسجل خدمات السيارة. لم يتم تنفيذ المنتجات أو المخزون أو الخدمات أو عروض الأسعار أو الحجوزات أو أوامر العمل أو الفواتير أو المحاسبة أو أي موديول خارج النطاق.

## ملكية العميل ونطاق الفروع

- العميل مملوك للشركة عبر `company_id`.
- `created_branch_id` ثابت لتسجيل فرع الإنشاء، و`assigned_branch_id` هو الفرع المسؤول القابل للتغيير بصلاحية.
- مدير الشركة يرى كل عملاء وLeads الشركة.
- مستخدم الفرع يرى العملاء المسندين لفروعه المصرح بها وLeads تلك الفروع فقط.
- الوصول Cross-Company مرفوض في Queries وPolicies وServices وForm Requests.
- لا يتم قبول `company_id` من Request؛ يتم أخذه من `TenantContext`.
- قاعدة “عميل مرتبط بمعاملة في الفرع” مؤجلة طبيعيًا حتى توجد معاملات في مراحل لاحقة.

## Migration والجداول

Migration: `database/migrations/2026_07_25_120000_create_customer_crm_tables.php`

الجداول:

- `customer_sources`
- `customers`
- `customer_contacts`
- `customer_addresses`
- `vehicles`
- `vehicle_ownership_history`
- `customer_notes`
- `leads`
- `lead_follow_ups`
- `attachments`
- `audit_logs`

تم استخدام Soft Delete، مفاتيح أجنبية، فهارس tenant/branch/search، `decimal(19,4)` للحد الائتماني، وUnique داخل الشركة للأكواد والرقم الضريبي والسجل التجاري وVIN واللوحة المنظّمة.

## Models والعلاقات

- `Customer`: الشركة، فرع الإنشاء، الفرع المسؤول، المصدر، جهات الاتصال، العناوين، الملاحظات، السيارات، المرفقات.
- `Vehicle`: العميل، الفرع، الماركة، الموديل، النوع، الحجم، سجل الملكية، المرفقات.
- `Lead`: الفرع، الماركة، الموديل، المصدر، المسؤول، العميل المحول، المتابعات.
- تمت إضافة علاقات Customers/Vehicles/Leads/Sources إلى `Company` وعلاقات العملاء وLeads إلى `Branch`.

## Services وNormalizers

- `CustomerService`: إنشاء/تحديث/تعطيل، كشف الهاتف المكرر، جهات الاتصال، العناوين، الملاحظات، Audit.
- `VehicleService`: عزل الشركة، توافق الماركة/الموديل، نطاق السنة، VIN واللوحة، والمراجع الخاصة بالشركة.
- `VehicleOwnershipService`: Transaction + row lock + سجل دائم قبل تغيير المالك + Audit.
- `LeadService`, `LeadFollowUpService`, `LeadConversionService`: ترقيم، حالات، Lost، متابعة، وتحويل Transactional لعميل موجود أو جديد وسيارة اختيارية.
- `AttachmentService`: تخزين Private بأسماء UUID، وعدم كشف المسار، وحذف آمن بعد نجاح Transaction.
- `PhoneNormalizer`: أرقام عربية/فارسية/إنجليزية، السعودية محليًا ودوليًا، مع حفظ قيمة العرض الأصلية.
- `PlateNormalizer`: توحيد الأرقام، إزالة الفراغات والشرطات، وتوحيد حالة الأحرف الإنجليزية.

## Policies والصلاحيات

Policies:

- `CustomerPolicy`
- `VehiclePolicy`
- `LeadPolicy`
- `AttachmentPolicy`

تمت إضافة الصلاحيات المطلوبة فقط وتوزيعها على `company_owner`, `general_manager`, `branch_manager`, `sales`, `receptionist`, `accountant`، مع عدم منح CRM إلى `technician`.

## منع التكرار

- العملاء: Warning مرن للهاتف المنظّم مع تأكيد صريح، وUnique داخل الشركة للرقم الضريبي والسجل التجاري، وفهارس للبحث بالبريد.
- السيارات: منع VIN واللوحة المنظّمة المكررين داخل الشركة.
- تحويل Lead: يبرز الهاتف المطابق، ويطلب ربط عميل موجود أو تأكيد إنشاء جديد رغم التطابق.
- جهة اتصال رئيسية واحدة، وعنوان افتراضي واحد لكل نوع، من خلال Services داخل Transactions.

## تحويل Lead

يتم داخل Transaction مع Lock للـLead، رفض التحويل المكرر، التحقق من tenant، ربط عميل موجود أو إنشاء عميل مرقّم، إنشاء سيارة اختيارية عند اكتمال الماركة والموديل، ثم تحديث `won`, `converted_customer_id`, `converted_at` وتسجيل Audit.

## نقل ملكية السيارة

يتم داخل Transaction مع `lockForUpdate`: التحقق من الشركة والمالك الجديد، منع النقل لنفس العميل، تسجيل `vehicle_ownership_history` قبل تحديث `customer_id`، ثم Audit. أي فشل يرجع العملية كاملة.

## المرفقات

- Disk: `local`، ومسار `private/attachments/{company_id}`.
- الحد الافتراضي: 10 MB من `config/attachments.php` ويمكن ضبطه بـ`ATTACHMENT_MAX_KB`.
- المسموح: JPEG, PNG, WebP, PDF فقط، مع فحص Extension وMIME.
- اسم التخزين UUID، والاسم الأصلي يمر عبر `basename`.
- Download يمر عبر Authentication و`AttachmentPolicy`; لا يوجد Public URL للمسار.

## Seeders وFactories

- تم Seed لمصادر العملاء العشرة الخاصة بـSeven Ways.
- تمت إضافة Document Sequences للـ`customer` والـ`lead` لكل فرع نشط.
- Factories: `CustomerFactory`, `VehicleFactory`, `LeadFactory`.
- لا يتم Seed لعملاء أو سيارات أو Leads وهمية في Production.

## Routes والواجهات

- تم تفعيل روابط العملاء والسيارات والعملاء المحتملين في Sidebar حسب الصلاحيات.
- تم إنشاء صفحات RTL للقائمة والإضافة والعرض والتعديل، مع الفلاتر وCRM dashboard والمتابعات والتحويل وLost والمرفقات وسجل الملكية.
- سجل خدمات السيارة Empty State فقط؛ لم يتم إنشاء جدول وهمي أو Work Orders/Invoices.
- إجمالي Routes بعد التنفيذ: 72.

## الاختبارات

الاختبارات الجديدة:

- `tests/Unit/CrmNormalizerTest.php`: اختبار الهاتف واللوحة.
- `tests/Feature/PhaseFiveCrmTest.php`: tenant/branch isolation، الترقيم، mass assignment، التكرار، العلاقات الافتراضية، السيارات، نقل الملكية والـrollback، Leads والمتابعات والتحويل، المرفقات والـ403.

النتائج:

- `php artisan migrate --force`: ناجح؛ Migration المرحلة الخامسة تم تطبيقه.
- `php artisan db:seed --force`: ناجح؛ كل Seeders اكتملت.
- `php artisan test`: ناجح — 53 tests.
- اختبار المرحلة الخامسة بعد آخر تعديل: ناجح — 11 tests.
- `vendor/bin/pint --test`: ناجح — 180 files.
- `composer validate`: ناجح؛ `composer.json is valid`.
- `npm.cmd run build`: ناجح — 58 modules transformed.
- `php artisan route:list`: ناجح — 72 routes.
- `php artisan view:cache`: ناجح.
- `git diff --check`: ناجح؛ تحذيرات line endings فقط بدون whitespace errors.

## الملفات المعدلة للمرحلة الخامسة

- Migration المذكور أعلاه.
- Models: `Customer*`, `Vehicle*`, `Lead*`, `Attachment`, `AuditLog`, بالإضافة إلى علاقات `Company` و`Branch`.
- Controllers: `CustomerController`, `VehicleController`, `LeadController`, `AttachmentController`.
- Requests: `CustomerRequest`, `CustomerRelatedRequest`, `VehicleRequest`, `LeadRequest`, `LeadActionRequest`, `AttachmentRequest`.
- Services: `CustomerService`, `VehicleService`, `VehicleOwnershipService`, `LeadService`, `LeadFollowUpService`, `LeadConversionService`, `AttachmentService`, `AuditService`, `PhoneNormalizer`, `PlateNormalizer`.
- Policies الأربعة، `AuthServiceProvider`, `routes/web.php`.
- Views: `resources/views/customers`, `vehicles`, `leads`, و`partials/sidebar.blade.php`.
- `config/attachments.php`.
- `FoundationPermissionSeeder`, `SevenWaysOperationalSeeder`, و`ReferenceDataRequest`.
- Factories والاختبارات المذكورة أعلاه.
- تم تنسيق `app/Console/Kernel.php`, `UserController.php`, `WelcomeController.php`, و`RedirectIfAuthenticated.php` آليًا فقط لإغلاق مشاكل Pint القديمة بدون تغيير سلوك.

## التحذيرات والمخاطر والمؤجلات

- PHP CLI الحالي `8.4.21` مع Laravel `9.52.21` يُظهر Deprecation Warnings من Dependencies القديمة في Symfony/Termwind/Collision/Pint. الإصدار الموصى به حاليًا للمشروع هو PHP 8.2. لم تتم ترقية Laravel أو Dependencies.
- وصول مستخدم الفرع إلى عميل بسبب “معاملة في فرعه” مؤجل لحين وجود جداول المعاملات.
- سجل خدمات السيارة مؤجل ليستخرج لاحقًا من أوامر العمل والفواتير والضمانات.
- لم يتم تنفيذ أي موديول خارج المرحلة الخامسة.
