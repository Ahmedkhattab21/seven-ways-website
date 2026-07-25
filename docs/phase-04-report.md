# تقرير المرحلة الرابعة — الإعدادات والبيانات المرجعية

## الحالة قبل التنفيذ

كان المشروع يحتوي على `Company` و`Branch` و`BranchSetting` و`TenantContext` والصلاحيات الأساسية وثيم Seven Ways، بدون جداول العملات والضرائب والوحدات وطرق الدفع ومراجع السيارات والسنوات المالية وتسلسل المستندات. تم الحفاظ على `currency_code` مؤقتًا للتوافق، مع اعتماد `currency_id` في الإعدادات الجديدة. لم تُنفذ أي موديولات تجارية.

## قاعدة البيانات

أضيفت ثلاث migrations آمنة:

- `2026_07_25_110000_create_reference_data_tables.php`: تنشئ `currencies`, `taxes`, `units`, `payment_methods`, `vehicle_brands`, `vehicle_models`, `vehicle_sizes`, `vehicle_types`, `fiscal_years`, `document_sequences`.
- `2026_07_25_110100_add_operational_settings_to_companies.php`: تضيف العملة والضريبة الافتراضيتين وصيغ التاريخ والوقت والدقة واللغة والاتجاه، مع backfill للعملة الحالية.
- `2026_07_25_110200_extend_branch_settings_table.php`: تضيف الضرائب وطرق الدفع والبادئات وساعات العمل وأيام الإجازة.

النسب والمبالغ تستخدم `decimal`، وحقول UUID تتبع نمط المشروع. أضيفت المفاتيح الخارجية والفهارس وقيود uniqueness. يستخدم تسلسل المستندات `scope_key` فريدًا مشتقًا من الشركة والفرع والنوع والفترة لمعالجة `NULL` بأمان في MySQL.

## Models والعلاقات

أضيفت Models: `Currency`, `Tax`, `Unit`, `PaymentMethod`, `VehicleBrand`, `VehicleModel`, `VehicleSize`, `VehicleType`, `FiscalYear`, `DocumentSequence`. وُسعت علاقات `Company` و`BranchSetting`. لا توجد `company_id` أو `branch_id` ضمن mass-assignable fields للبيانات الحساسة؛ الخدمات تضبط السياق.

## Services

- `TaxService`: transaction و`lockForUpdate` وضريبة افتراضية واحدة لكل نوع داخل الشركة.
- `FiscalYearService`: يمنع التداخل ويضمن سنة حالية واحدة.
- `BranchSettingsService`: يثبت أن الضريبة وطريقة الدفع نشطتان ومن نفس الشركة.
- `CompanySettingsService`: يتحقق من العملة والضريبة ويوفر نقطة منع تغيير العملة عند إضافة الحركات المرحلة مستقبلًا.
- `DocumentNumberService`: يتحقق من `TenantContext`، ويستخدم transaction و`lockForUpdate`، ويدعم `never/yearly/monthly` وtokens: `{COMPANY}`, `{BRANCH}`, `{TYPE}`, `{YYYY}`, `{YY}`, `{MM}`. لا يستخدم `MAX` أو عدد الصفوف. يجب استدعاؤه داخل transaction إنشاء المستند حتى يرجع العداد تلقائيًا عند فشل العملية.

## Validation والصلاحيات

أضيف `ReferenceDataRequest` و`BranchSettingsRequest`، ووُسع `CompanyUpdateRequest`. أضيفت `ReferenceDataPolicy` و`BranchSettingPolicy` وسُجلتا في `AuthServiceProvider`.

أضيفت الصلاحيات المطلوبة: `settings`, `taxes`, `units`, `payment_methods`, `vehicle_references`, `fiscal_years`, `document_sequences`, `branch_settings` بنوعي `view/manage`. وُزعت على `company_owner`, `general_manager`, `branch_manager`, `accountant` بدون منح مدير الفرع إدارة إعدادات الشركة العامة. العملات والماركات والموديلات global ولا يعدلها إلا System Admin، والبيانات النظامية تظهر read-only للشركات.

## Routes والواجهات

أضيفت routes محمية بـ`auth`, `active.user`, `tenant`:

- `GET|PUT settings/branch`
- CRUD بدون حذف عبر `settings/reference/{section}` للأقسام العشرة.

أضيفت صفحات RTL مشتركة للقوائم والنماذج، Search وStatus Filter، وإخفاء actions والروابط حسب الصلاحية. وُسعت شاشة الشركة، وأضيفت شاشة الفرع وروابط Sidebar. الفرع لا يُقبل من request في إعدادات الفرع؛ يؤخذ من `TenantContext`.

## Seeders

`ReferenceDataSeeder` قابل لإعادة التشغيل ويضيف SAR/USD/AED/EGP والوحدات النظامية وأحجام وأنواع السيارات، ويعمل backfill للشركات. `SevenWaysOperationalSeeder` يضيف SAR وVAT 15% وطرق الدفع الخمس والسنة الحالية و15 sequence لكل فرع نشط وإعدادات الفرع الافتراضية. ترتيب `DatabaseSeeder` يضمن سلامة الاعتماديات.

## الاختبارات

أضيف `PhaseFourSettingsTest` وفيه 14 اختبارًا يغطي عزل الشركات، read-only للبيانات النظامية، إنشاء الوحدات وطرق الدفع، rate validation، ضريبة افتراضية واحدة، تداخل وحالية السنوات، سنوات موديلات السيارات، إعدادات الفرع cross-company، الصلاحيات والـSidebar، تزايد وترميز وإعادة ضبط الأرقام، استقلال تسلسل الفرع والشركة، منع cross-tenant، وrollback لرقم المستند داخل transaction.

نتيجة الترقيم: الأرقام المتتالية وyearly reset وtokens نجحت، وقيد uniqueness مع `lockForUpdate` يمنع التكرار. تم اختبار rollback فعليًا باستخدام savepoint. لم يُنفذ stress test بعمليتين متوازيتين مستقلتين على بيئة Windows الحالية.

## نتائج الأوامر

- `php artisan migrate --force`: نجح، 3 migrations.
- `php artisan db:seed --force`: نجح، كل Seeders.
- `php artisan test`: نجح، **40 passed**.
- `php artisan route:list`: نجح، **41 routes**.
- `composer validate`: نجح؛ `composer.json is valid`.
- `npm.cmd run build`: نجح؛ Vite 4.5.14، 58 modules.
- `php artisan view:cache`: نجح.
- `git diff --check`: نجح.
- `vendor/bin/pint --test`: تغييرات المرحلة نفسها نظيفة، لكن الفحص الكامل يفشل بسبب 4 ملفات قديمة خارج النطاق:
  - `app/Console/Kernel.php`
  - `app/Http/Controllers/UserController.php`
  - `app/Http/Controllers/WelcomeController.php`
  - `app/Http/Middleware/RedirectIfAuthenticated.php`

## الملفات المعدلة

### موجودة وتم تعديلها

- `app/Http/Controllers/CompanyController.php`
- `app/Http/Requests/CompanyUpdateRequest.php`
- `app/Models/BranchSetting.php`
- `app/Models/Company.php`
- `app/Providers/AuthServiceProvider.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/FoundationPermissionSeeder.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/settings/company.blade.php`
- `routes/web.php`

### جديدة

- `app/Http/Controllers/BranchSettingsController.php`
- `app/Http/Controllers/ReferenceDataController.php`
- `app/Http/Requests/BranchSettingsRequest.php`
- `app/Http/Requests/ReferenceDataRequest.php`
- Models العشرة المذكورة أعلاه.
- `app/Policies/BranchSettingPolicy.php`
- `app/Policies/ReferenceDataPolicy.php`
- Services الخمسة المذكورة أعلاه.
- migrations الثلاث المذكورة أعلاه.
- `database/seeders/ReferenceDataSeeder.php`
- `database/seeders/SevenWaysOperationalSeeder.php`
- `resources/views/reference/index.blade.php`
- `resources/views/reference/form.blade.php`
- `resources/views/settings/branch.blade.php`
- `tests/Feature/PhaseFourSettingsTest.php`
- `docs/phase-04-report.md`

## المخاطر والمؤجلات

- PHP CLI الحالي 8.4 ويصدر deprecation warnings من Laravel 9/Symfony/Termwind/Pint القديمة. PHP 8.2 يظل الإصدار الموصى به، ولم تُرق Laravel أو dependencies.
- قفل تغيير عملة الشركة جاهز داخل Service، لكن لا توجد جداول حركات مرحلة في المرحلة الرابعة لربطه بها بعد.
- الإقفال المحاسبي الفعلي وحذف البيانات المرجعية والموديولات التجارية كلها مؤجلة عمدًا.

تم تأكيد عدم إنشاء Customers أو Vehicles أو Products أو Inventory أو Services أو Documents فعلية أو Accounting أو أي موديول خارج المرحلة الرابعة.
