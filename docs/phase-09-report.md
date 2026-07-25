# تقرير المرحلة التاسعة — عروض الأسعار والحجوزات

## التصميم وSnapshot

- عرض السعر مملوك للشركة وفرع واحد، ويرتبط بعميل وسيارة من نفس الشركة مع تحقق أن السيارة للعميل.
- العرض الناتج من Lead يتطلب تحويل الـLead أولًا عبر دورة CRM الحالية، ثم يثبت `lead_id` و`converted_customer_id` ويحدث حالته إلى `proposal_requested`؛ يصبح `won` عند قبول العرض.
- كل عنصر يحفظ الوصف والكمية والسعر والخصم والضريبة والإجمالي ومصدر السعر والحد الأدنى والمدة والتكلفة التقديرية كـSnapshot.
- المواد التقديرية تنسخ إلى `quotation_item_materials` ولا تتغير لاحقًا مع تعديل كتالوج الخدمة.
- لا توجد أي حركة أو حجز مخزون، ولا Work Order أو Invoice أو Journal Entry.

## قاعدة البيانات

Migration: `2026_07_25_160000_create_quotation_appointment_tables.php`.

أضافت الجداول:

- `quotations`
- `quotation_items`
- `quotation_item_materials`
- `appointments`
- `appointment_services`
- `appointment_deposits`

كل المبالغ `DECIMAL(19,4)`، مع مفاتيح خارجية ونطاقات فهرسة للشركة/الفرع/الحالة/التاريخ، وUnique لعائلة العرض وإصداره وأرقام الحجز والإيصال. التغيير Additive ولا يعدل أو يحذف بيانات قديمة. الـrollback يحذف جداول المرحلة فقط بالترتيب العكسي.

## التسعير والخصم والضريبة

`QuotationPricingService` يعيد الحساب كاملًا في Backend:

1. يحل سعر الخدمة من `ServicePricingService` أو سعر الباقة أو المنتج القابل للبيع.
2. يسمح بالسعر اليدوي فقط مع `quotations.manual_price`.
3. يطبق خصم العنصر أو Promotion مؤهل ومختار.
4. يحسب صافي العناصر.
5. يوزع الخصم العام نسبيًا على العناصر، ويضع فرق التقريب على آخر عنصر.
6. يعيد حساب الضريبة بعد التوزيع.
7. يحسب الإجمالي والتكلفة والهامش التقديري.

`MoneyRoundingService` يستخدم Half-up وعدد المنازل الخاص بالعملة. سياسة الكتالوج الحالية Tax-exclusive؛ يتم رفض `price_includes_tax=true` بدل حساب غير صحيح.

## الإصدارات والحالات

- Draft فقط يعدل مباشرة.
- `QuotationVersionService` يقفل السجل، ينسخ العناصر والمواد، يزيد `version_number`، يحافظ على `quotation_number`، ويضع السابق `superseded`.
- دورة الاعتماد: `draft → pending_approval → approved/rejected`.
- منشئ العرض غير المالك لا يعتمد عرضه بنفسه.
- بعد الاعتماد: `sent`, `accepted`, `rejected`, `cancelled`, `expired`, `converted`.
- القبول يمنع العرض المنتهي، ويمنع قبول إصدارين من نفس العائلة، ويضع الإصدارات الأخرى `superseded`.
- `quotations:expire` idempotent للعروض `approved/sent` فقط، ومسجل يوميًا الساعة 00:10 دون تفعيل Cron إنتاجي من داخل المشروع.

## الحجز والجدولة

- `QuotationToAppointmentService` يقفل العرض المقبول، ينشئ حجزًا واحدًا فقط، ينسخ الخدمات والباقات كسعر ومدة Snapshot، ثم يضع العرض `converted`.
- الحجز المباشر يدعم Walk-in والهاتف وواتساب والموقع.
- `AppointmentSchedulingService` يتحقق من نشاط وساعات وإجازات الفرع، البداية والنهاية، minimum notice، daily capacity، تداخل الفني، فرع الفني، ومهارته النشطة عند وجود قواعد مهارات.
- عدم تعيين فني مسموح للإسناد لاحقًا.
- Check-in يسجل الوقت والعداد والملاحظات فقط، ولا ينشئ أمر عمل.
- الإلغاء وNo-show يحفظان السبب وقرار العربون التشغيلي دون Refund مالي.

## العربون التشغيلي

`AppointmentDepositService` يسجل إيصالًا Append-only بحالات `recorded/cancelled/refunded/forfeited`.
يتحقق من وسيلة الدفع والشركة والفرع والمبلغ والمرجع، ويحدث حالة العربون بالحجز.
لا ينشئ Cash Balance أو Payment أو Journal Entry، وتعرض الواجهة Banner واضحًا بذلك.

## Domain Events

أضيفت Events:

`QuotationCreated`, `QuotationSubmitted`, `QuotationApproved`, `QuotationSent`,
`QuotationAccepted`, `QuotationRejected`, `QuotationExpired`,
`QuotationConvertedToAppointment`, `AppointmentCreated`, `AppointmentConfirmed`,
`AppointmentCheckedIn`, `AppointmentCancelled`, `AppointmentMarkedNoShow`, `DepositRecorded`.

الأحداث الناتجة من معاملات الخدمات تستخدم `DB::afterCommit`، ولا تحتوي Business Logic أو إرسال Email/WhatsApp.

## الأمان والواجهة

- Policies: `QuotationPolicy`, `QuotationItemPolicy`, `AppointmentPolicy`, `AppointmentDepositPolicy`.
- أضيفت كل صلاحيات quotations/appointments/deposits المطلوبة وتوزيع الأدوار.
- لا يُقبل `company_id` أو `status` أو الإجماليات من Request.
- أضيفت Form Requests الثمانية، وControllers خفيفة، وAudit لكل Action مهم.
- واجهات RTL: قوائم ونماذج وتفاصيل وطباعة عروض الأسعار، قائمة/تقويم/نموذج/تفاصيل الحجوزات، Timeline تشغيلي عبر البيانات والأحداث، ورابط من Lead.
- الطباعة HTML آمنة بلا PDF dependency أو ZATCA QR.

## Seeders وFactories والاختبارات

- `QuotationAppointmentSeeder` idempotent للصلاحيات وSequences كل فرع:
  `{BRANCH}-QT-{YYYY}-`, `{BRANCH}-APT-{YYYY}-`, `{BRANCH}-DEP-{YYYY}-`.
- لا ينشئ عروض أسعار أو حجوزات أو عربونًا وهميًا.
- Factories: `Quotation`, `QuotationItem`, `Appointment`, `AppointmentService`, `AppointmentDeposit`.
- `PhaseNineQuotationAppointmentTest`: 11 سيناريو تغطي Snapshot والحساب والثبات، الإصدارات، الاعتماد والفصل، القبول، التحويل مرة واحدة، تداخل الفني، العربون وCheck-in، الإلغاء وNo-show، الانتهاء idempotent، Seeder، والصلاحيات والعزل.

## نتائج الأوامر

- `php artisan migrate --force`: نجح على قاعدة التطوير، وكذلك Migration قاعدة الاختبار.
- `php artisan db:seed --force`: نجح، ومنها `QuotationAppointmentSeeder`.
- `php artisan test`: نجح، 93 اختبارًا.
- `vendor/bin/pint --test`: نجح بعد تنسيق ملفات المرحلة.
- `composer validate`: نجح.
- `npm.cmd run build`: نجح، 58 module.
- `php artisan route:list`: نجح، 173 route.
- `php artisan view:cache`: نجح.
- `git diff --check`: نجح بلا whitespace errors، مع تنبيهات LF/CRLF فقط على Windows.

## الملفات والمخاطر والمؤجلات

الملفات الجديدة داخل:
`app/Events`, `app/Console/Commands`, `app/Models`, `app/Services`,
`app/Http/{Controllers,Requests}`, `app/Policies`, `database/{migrations,seeders,factories}`,
`resources/views/{quotations,appointments}`, و`tests/Feature`.

تم تحديث العلاقات و`AuthServiceProvider`, `Console/Kernel`, `routes/web.php`,
`DatabaseSeeder`, Sidebar، وصفحة Lead.

المؤجل صراحة: Work Orders، الفحص والجودة، الحجز/الاستهلاك الفعلي للمخزون، الفواتير، المحاسبة، بوابة الدفع، Refund المالي، ZATCA، الضمان، والإرسال الفعلي Email/WhatsApp.
تحذيرات Deprecation من Laravel 9 dependencies مستمرة مع PHP 8.4؛ PHP 8.2 هو الموصى به.
