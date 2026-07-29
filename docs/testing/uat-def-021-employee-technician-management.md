# UAT-DEF-021 — إدارة الموظفين والفنيين

## النتيجة

**READY — Employees and qualified technicians can be managed by branch and safely assigned to matching work-order services**

الوظيفة جاهزة للاختبار اليدوي المستهدف. لم يُنشأ الموظف `TECH-CAI-001` أو أي User أو بيانات UAT تلقائيًا.

## سبب المشكلة

أوامر العمل تسند التنفيذ إلى `employees` و`employee_service_skills`، بينما النظام كان يوفر إدارة Users والأدوار فقط. لذلك لم يكن إنشاء User كافيًا لظهوره كفني، ولم توجد واجهة لإنشاء Employee وربطه بفرع ومهارة خدمة.

## الفصل بين User وEmployee

- User حساب دخول وصلاحيات.
- Employee سجل تشغيلي مستقل تابع لشركة وفرع.
- ربط User اختياري، ولا يُنشأ User تلقائيًا.
- لا يمكن ربط User مستخدم بموظف آخر.
- ظهور الفني يعتمد على Employee نشط ومهارة خدمة نشطة، وليس على Role المستخدم.

## ما تم تنفيذه

- CRUD آمن على `/employees` مع البحث والتصفية والـPagination.
- عزل الشركة والفروع في الاستعلامات والـPolicy والـRequests.
- إضافة وتحديث وتعطيل الموظف دون حذف التاريخ.
- إدارة عدة مهارات، منع التكرار، والتحقق من إتاحة الخدمة في فرع الموظف.
- دعم مستويات المهارة الفعلية: `trainee`, `junior`, `intermediate`, `senior`, `expert`.
- فلترة قائمة فنيي كل خدمة حسب الشركة والفرع وحالة الموظف والمهارة وتاريخ انتهائها.
- إعادة التحقق في Backend وقت الإسناد ووقت بدء الخدمة.
- رسالة واضحة ورابط إضافة فني يحفظ `branch_id`, `service_id`, `return_url`.
- Checklist لجاهزية الفحص والفني والمواد، وترجمة حالات التنفيذ.
- عرض بيانات الفني المسند ودوره ومستوى مهارته ووقت الإسناد.
- إخفاء تكلفة الساعة إلا بصلاحية `work_orders.view_cost`.

## الصلاحيات

أضيفت بصورة idempotent:

- `employees.view`
- `employees.create`
- `employees.update`
- `employees.disable`
- `employees.manage_skills`

تُمنح لـ`system_admin`, `company_owner`, `general_manager`, `branch_manager`. لم تُمنح صلاحيات الإدارة تلقائيًا للمحاسب أو الكاشير. تم تشغيل `EmployeeManagementSeeder` على `uat.local` بنجاح.

## الاختبارات والتحقق

| الأمر | النتيجة |
|---|---|
| `optimize:clear --env=uat.local` | Passed |
| `db:seed --class=EmployeeManagementSeeder --env=uat.local` | Passed |
| `test --filter=Employee` | 16 passed |
| `test --filter=EmployeeServiceSkill` | 1 passed |
| `test --filter=WorkOrderTechnician` | 1 passed |
| `test --filter=PhaseTenWorkOrderExecution` | 14 passed |
| `npm.cmd run build` | Passed، مع تحذيرات assets قديمة للموقع العام |
| `view:cache --env=uat.local` | Passed |
| Pint للملفات المعدلة | 12 files passed |
| `git diff --check` | Passed |

الـFull Suite شُغّل لكنه غير ناجح بسبب بيئة الاختبار العامة: `404 failed / 4 passed` بعد فقدان `APP_ENV=testing` أثناء التشغيل. كما أن محاولة تطبيق Migration سابقة مطلوبة لاختبارات Appointment فشلت لأن جدول `branch_settings` في قاعدة testing بحالة read-only. فلتر WorkOrder أعطى `15 passed / 5 failed`؛ الاختبارات الخمسة تخص الـMigration السابقة ووقت عمل الفرع، وليست موديول الموظفين.

الفحص العام لـPint وجد مخالفة واحدة متبقية في `AppointmentSchedulingService.php` من تغييرات سابقة خارج هذا الـPatch. ملفات UAT-DEF-021 نفسها سليمة.

## Manual UAT

لم يُنفذ إنشاء الموظف عمدًا. تحقق قاعدة UAT: `TECH-CAI-001=0`.

الخطوات المتبقية للمستخدم:

1. إنشاء `TECH-CAI-001` يدويًا من `/employees/create`.
2. ربطه بفرع `CAI-MAIN` وخدمة إزالة الفيلم بمستوى خبير.
3. الرجوع إلى أمر العمل واختبار الإسناد ثم استكمال الفحص والمواد قبل البدء.

