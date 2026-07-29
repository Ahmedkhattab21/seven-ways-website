# SCOPE-RESET-001A — Stabilization Report

## النتيجة

**READY — Scope Reset is stable, disabled modules are consistently protected, enabled accounting modules have no regressions, and the full suite passes with zero failures.**

تم الإبقاء على الموديولات التالية معطلة افتراضيًا:

`leads`, `appointments`, `work_orders`, `technicians`, `quality`, `rework`, `delivery`, `warranties`, `advanced_roll_inventory`.

لم يتم حذف Routes أو Controllers أو Models أو جداول أو بيانات تاريخية. الاختبارات القديمة التي تختبر منطق هذه الموديولات تفعّل الـFeature Flags داخل الاختبار فقط.

## الـ26 Failure الأصلية

| # | Test | التصنيف | المعالجة |
|---:|---|---|---|
| 1 | `AppointmentCheckInWorkOrderTest::branch default accepts only an eligible work order warehouse` | Migration / schema | تطبيق الـMigration الآمنة في Test DB وتفعيل الموديولات داخل الاختبار |
| 2 | `AppointmentCheckInWorkOrderTest::check in requires a configured default warehouse and rolls back` | Disabled module expectation | تفعيل `appointments` و`work_orders` داخل الاختبار |
| 3 | `AppointmentCheckInWorkOrderTest::check in atomically creates one work order without inventory or accounting` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 4 | `AppointmentCheckInWorkOrderTest::appointment recovery preserves arrival data and is idempotent` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 5 | `AppointmentCheckInWorkOrderTest::missing work order sequence rolls back check in data` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 6 | `EmployeeCreatePrefillTest::create page works with and without valid prefill without writing data` | Disabled module expectation | تفعيل `technicians` و`work_orders` داخل الاختبار |
| 7 | `EmployeeCreatePrefillTest::dynamic skill state and old input are rendered for the browser` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 8 | `EmployeeCreatePrefillTest::employee can be saved with multiple distinct branch services` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 9 | `EmployeeCreatePrefillTest::missing branch or service ids do not cause server error` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 10 | `EmployeeCreatePrefillTest::cross company branch and service prefill are forbidden` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 11 | `EmployeeCreatePrefillTest::service unavailable in selected branch is not prefilled` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 12 | `EmployeeCreatePrefillTest::only internal return urls are kept` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 13 | `EmployeeCreatePrefillTest::employee and skill are saved then return to work order as qualified` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 14 | `EmployeeTechnicianManagementTest::employee can be created without user with branch skill and no uat record` | Disabled module expectation | تفعيل `technicians` و`work_orders` داخل الاختبار |
| 15 | `EmployeeTechnicianManagementTest::employee service skill duplicate and service unavailable in branch are rejected` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 16 | `EmployeeTechnicianManagementTest::employee list and record are company and branch scoped` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 17 | `EmployeeTechnicianManagementTest::work order page only lists qualified technicians and hides cost without permission` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 18 | `PhaseElevenQualityDeliveryWarrantyTest::public warranty verification is rate limited and does not leak sensitive data` | Disabled module expectation | تفعيل موديولات الورشة المطلوبة داخل الاختبار |
| 19 | `PhaseElevenQualityDeliveryWarrantyTest::cross company quality warranty and claim access is forbidden` | Disabled module expectation | تفعيل موديولات الورشة المطلوبة داخل الاختبار |
| 20 | `PhaseNineQuotationAppointmentTest::quotation conversion defaults from active quotation snapshots` | Test environment / time fixture | موعد ثابت صالح داخل ساعات عمل الفرع وتفعيل الموديولات |
| 21 | `PhaseNineQuotationAppointmentTest::calendar is scoped and filtered` | Disabled module expectation | تفعيل `appointments` و`work_orders` داخل الاختبار |
| 22 | `PhaseNineQuotationAppointmentTest::appointment create validates branch service availability and conflicts` | Disabled module expectation | تفعيل الموديولين داخل الاختبار |
| 23 | `PhaseNineQuotationAppointmentTest::operational deposit and check in create one work order without accounting effect` | Migration / disabled module | تحديث Test DB وتفعيل الموديولين |
| 24 | `PhaseTenWorkOrderExecutionTest::inspection photos are private and cross branch download is forbidden` | Disabled module expectation | تفعيل `work_orders`, `technicians`, `quality` داخل الاختبار |
| 25 | `PhaseTenWorkOrderExecutionTest::unprivileged and cross company users cannot view work order or cost` | Disabled module expectation | تفعيل الموديولات داخل الاختبار |
| 26 | `TenantFoundationTest::user form renders accessible branch and role checkboxes without duplicate role names` | Navigation / legacy role expectation | تحديث التوقع لعدم إظهار دور `cashier` القديم ضمن الإسناد الجديد |

ظهر كذلك 3 Failures مرتبطة بـ`ThreeRoleBranchOperatingModelTest` بعد دمج SCOPE-RESET-002: سببان كانا من Migration `responsible_user_id` غير المطبقة في Test DB، والثالث كان Assertion واجهة لا يمثل قاعدة العزل. تم تطبيق الـMigration وتثبيت الاختبار على `TenantContext` الفعلي ومنع تبديل الفرع.

## الحماية المركزية

- `RejectDisabledModules` يعمل قبل Authentication لمنع كشف المسار بتحويل أو 403.
- GET / POST / PUT / DELETE للموديول المعطل ترجع 404.
- Login وHealth والمسارات العامة لا تتأثر.
- POST المعطل لا يعدّل البيانات التاريخية.
- Data Provider يغطي كل Module Flag من المصدر المركزي `config/modules.php`.
- Sidebar يعتمد نفس `ModuleRegistry` ولا يعرض مجموعات فارغة أو روابط مكررة.

## قرار `default_work_order_warehouse_id`

الحقل ما زال جزءًا معتمدًا من دورة فحص الوصول القديمة، لذلك لم تُحذف الـMigration أو العلاقة. الـMigration Forward-only، nullable، وForeign Key يستخدم `nullOnDelete`. كانت مطبقة بالفعل على `uat.local` ضمن Batch 4، وتم تطبيقها على Test DB بدل أي تجاوز أو `try/catch`.

## Migration Status

- `2026_07_29_180000_add_default_work_order_warehouse_to_branch_settings`: **Ran — Batch 4**
- `2026_07_29_190000_add_responsible_user_to_branches`: تمت مراجعتها كإضافة nullable وآمنة ثم تطبيقها على `uat.local`: **Ran — Batch 5**
- لا توجد Migrations معلقة بعد التنفيذ.
- لم يتم تشغيل `migrate:fresh` أو `db:wipe`.

## الاختبارات المعدلة والمضافة

- إضافة `enableModules()` في `tests/TestCase.php`.
- تحديث اختبارات Appointment، Employee، Quality، Work Order لتفعيل الموديولات محليًا فقط.
- إضافة `DisabledModuleProtectionTest`.
- تحديث توقعات Tenant وThree-role لتوافق النطاق الفعلي.
- تثبيت مواعيد الاختبار داخل ساعات العمل لمنع الاعتماد على ساعة التشغيل.

## النتائج النهائية

| الأمر | النتيجة |
|---|---|
| `php artisan test --filter=SidebarNavigation` | 4 passed |
| `php artisan test --filter=DisabledModule` | 12 passed |
| `php artisan test --filter=Module` | 25 passed |
| `php artisan test --filter=Sales` | 14 passed |
| `php artisan test --filter=Inventory` | 15 passed |
| `php artisan test --filter=Treasury` | 27 passed |
| `php artisan test --filter=Accounting` | 23 passed |
| `php artisan test` | **433 passed, 0 failed** |
| `vendor/bin/pint --test` | Passed |
| `npm.cmd run build` | Passed |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed |

Pint ما زال يطبع Deprecation Warnings من dependencies المضمنة عند استخدام PHP 8.4، وVite يطبع تحذيرات لمسارات أصول عامة تُحل وقت التشغيل؛ كلا الأمرين انتهى بنجاح. تم تصحيح Style فقط في `AppointmentSchedulingService`.

## سلامة البيانات

لم يتم حذف أو إعادة إنشاء أي جدول، ولم يتم حذف أو تحويل أي بيانات تاريخية، ولم تُفعّل الموديولات المعطلة افتراضيًا، ولم يتم توسيع صلاحيات أي Role.
