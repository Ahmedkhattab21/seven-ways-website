# تقرير المرحلة الثامنة — كتالوج الخدمات

## النطاق والتصميم

- تعريف التصنيف والخدمة مملوك للشركة، بينما التوافر والسعر مملوكان للفرع مع تطبيق `TenantContext` وصلاحيات الوصول للفروع.
- لا ترتبط الخدمة بمخزن. المواد والرولات تقديرات فقط ولا تنشئ حجزًا أو حركة أو خصمًا مخزنيًا.
- التعطيل مستخدم بدل حذف السجلات التشغيلية، وكل الأسعار والتكاليف محفوظة كـ `DECIMAL`.
- لم تُنفذ عروض أسعار أو حجوزات أو أوامر عمل أو استهلاك فعلي أو فواتير أو مدفوعات أو مشتريات أو محاسبة أو ضمانات.

## قاعدة البيانات

أضيفت الجداول:

`service_categories`, `services`, `branch_services`, `service_prices`,
`service_material_requirements`, `service_roll_consumption_profiles`,
`service_material_substitutes`, `employee_service_skills`,
`service_commission_rules`, `service_packages`, `service_package_items`,
`branch_service_packages`, `promotions`, `promotion_services`,
`promotion_packages`, `promotion_branches`.

كانت قاعدة التطوير تحتوي جدول موقع قديم باسم `services` به 8 سجلات ولا يحتوي `company_id`.
حافظت الـmigration عليه كاملًا باسم `legacy_website_services` قبل إنشاء جدول ERP الجديد.
الـrollback يعيد الاسم القديم بعد إزالة جداول المرحلة. لم يُستخدم `migrate:fresh` أو `db:wipe`.

## Models والعلاقات

أضيف Model لكل جدول، وعلاقات ثنائية مع:
`Company`, `Branch`, `Employee`, `Product`, `VehicleSize`, `VehicleType`,
`Tax`, `Unit`, `Role`. القوائم تستخدم eager loading حيث تحتاج العلاقات.

## التسعير والضريبة

`ServicePricingService` يتحقق من الشركة والفرع والمراجع النشطة ومنع تداخل نفس النطاق والأولوية.
الاختيار يبدأ بالفرع والخدمة والتاريخ، ثم الحجم والنوع الأكثر تحديدًا، ثم الأولوية.
عند غياب سعر متخصص يُستخدم سعر الفرع، وعند غيابه تكون النتيجة `custom_quote`.
السعر المخزن قبل الضريبة؛ تُحسب الضريبة من `default_tax_id` ثم يعاد `subtotal`, `tax`, و`total`.

## المواد والرولات والتكلفة

- `ServiceMaterialRequirementService` يتحقق من المنتج والوحدة والنطاق ويدير البدائل المتوافقة دون استبدال مخزني تلقائي.
- `ServiceMaterialEstimator` يحسب الكمية والمساحة والهالك والتكلفة التقديرية دون اختيار رول فعلي أو تغيير المخزون.
- تكلفة المنتجات العادية من المتوسط المرجح الحالي، وتقدير الفيلم من التكلفة المتاحة.
- `ServiceCostEstimator` يجمع تكلفة المواد والهالك ويحسب الهامش التقديري.
- المستخدم دون `services.view_cost` لا يرى حقول التكلفة أو الهامش، بينما تبقى الكميات والهالك ظاهرة.

## المهارات والعمولات والباقات والعروض

- مهارة الفني فريدة للخدمة وتلتزم بشركة وفرع الموظف، مع بيانات مستوى واعتماد وانتهاء.
- `ServiceCommissionRuleResolver` يرجع القاعدة الأكثر تحديدًا حسب الفني/الدور/الفرع والأولوية والتاريخ فقط، ولا ينشئ مستحقًا ماليًا.
- الباقات تمنع تكرار الخدمة وتتحقق أن كل الخدمات من الشركة نفسها، وتدعم سعر الفرع والحجم والفترة.
- `PromotionResolver` يرجع العرض المحتمل المطابق للخدمة/الباقة/الفرع والتاريخ فقط؛ لا يطبقه على مستند ولا يسجل redemption.

## الأمان والتدقيق

أضيفت Policies للتصنيفات والخدمات وتوافر الفرع والأسعار والمواد والباقات والعروض والمهارات والعمولات.
أضيفت صلاحيات العرض والإدارة والتعطيل وإدارة السعر والتوافر والمواد والرولات والمهارات والعمولات والتكلفة، مع توزيعها حسب الأدوار.
كل تغييرات الكتالوج المهمة تسجل أحداث Audit مختصرة. لا يُقبل `company_id` من النماذج، و`branch_id` يُتحقق منه عبر السياق.

## Routes والواجهة

فُعلت صفحات RTL ضمن Seven Ways Theme للتصنيفات والخدمات وتفاصيل الخدمة والحاسبة والباقات والعروض.
صفحة الخدمة تشمل توافر الفروع والأسعار والمواد والرولات والبدائل والمهارات والعمولات.
لم تُفعّل روابط عروض الأسعار أو الحجوزات أو أوامر العمل.

## Seeders وFactories

`ServiceCatalogSeeder` idempotent ويضيف:

- الصلاحيات وتوزيع الأدوار.
- ستة تصنيفات وعشر خدمات عربية أولية من نوع `custom_quote` بلا أسعار إنتاج.
- sequences: `SRV`, `PKG`, `PRM`.

لا ينشئ منتجات وهمية أو عروضًا نشطة. أضيفت الـFactories السبعة المطلوبة للاختبارات.

## الاختبارات

`PhaseEightServiceCatalogTest` يغطي 11 سيناريو تشمل:
العزل بين الشركات، دورات التصنيفات، نطاق الفرع وتعطيله، دقة السعر والكمية والضريبة والتداخل،
المواد والبدائل والمتوسط المرجح وعدم تغيير المخزون، تقدير الرول، Resolver العمولات،
سلامة الباقات، Resolver العروض، الصلاحيات وإخفاء التكلفة، وidempotency للـSeeder.

## الملفات

- Migration: `database/migrations/2026_07_25_150000_create_service_catalog_tables.php`
- Seeder: `database/seeders/ServiceCatalogSeeder.php`
- Factories: `database/factories/{ServiceCategory,Service,BranchService,ServicePrice,ServiceMaterialRequirement,ServicePackage,Promotion}Factory.php`
- Models: ملفات `Service*`, `BranchService*`, `EmployeeServiceSkill`, و`Promotion` داخل `app/Models`
- Services: خدمات الكتالوج والتوافر والتسعير والمواد والتقدير والمهارات والعمولات والباقات والعروض داخل `app/Services`
- HTTP: Controllers وForm Requests وPolicies الخاصة بالمرحلة داخل `app`
- UI: `resources/views/services/**` وتحديث `resources/views/partials/sidebar.blade.php`
- Wiring: `routes/web.php`, `app/Providers/AuthServiceProvider.php`, العلاقات في Models الحالية، و`database/seeders/DatabaseSeeder.php`
- Tests: `tests/Feature/PhaseEightServiceCatalogTest.php`

## المخاطر والمؤجلات

- Laravel 9 على PHP 8.4 يصدر Deprecation Warnings من dependencies؛ PHP 8.2 هو الإصدار الموصى به حاليًا. لم تتم ترقية Laravel أو dependencies.
- التسعير والتكلفة والعمولة والعرض نتائج تأسيسية/تقديرية حتى وجود مستندات المراحل اللاحقة.
- الاستهلاك الفعلي، حجز المخزون، اختيار الرول، المستحقات المالية، تطبيق العروض، والـredemption مؤجلة صراحة.

## نتائج الأوامر

- `php artisan migrate --force`: نجح؛ الـmigration مطبقة، وإعادة التحقق أعادت `Nothing to migrate`.
- `php artisan db:seed --force`: نجح؛ `ServiceCatalogSeeder` وباقي Seeders اكتملت.
- `php artisan test`: نجح، 82 اختبارًا.
- `vendor/bin/pint --test`: نجح، 320 ملفًا بلا مخالفات.
- `composer validate`: نجح، `composer.json is valid`.
- `npm.cmd run build`: نجح، 58 module وتحضير Vite assets.
- `php artisan route:list`: نجح، 144 route.
- `php artisan view:cache`: نجح، وتم cache لكل Blade templates.
- `git diff --check`: نجح بلا whitespace errors؛ ظهرت فقط تنبيهات Windows المتوقعة عن تحويل LF إلى CRLF.
