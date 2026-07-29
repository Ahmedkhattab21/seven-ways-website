# SCOPE-RESET-001 — Accounting and invoicing core

## الهدف

تركيز Seven Ways على العملاء، الكتالوج، عروض الأسعار، فواتير المبيعات، التحصيل،
المشتريات، المخزون الأساسي، الخزينة، البنوك والمحاسبة، بدون اشتراط دورة الورشة.

## الموديولات

المفعلة مركزيًا في `config/modules.php`:

- Sales
- Purchasing
- Basic inventory
- Accounting
- Treasury

المعطلة بدون حذف كود أو جداول أو بيانات:

- Leads
- Appointments and calendar
- Work orders and their material operations
- Employees and technicians
- Vehicle inspections and quality
- Rework
- Delivery
- Warranties and claims
- Rolls, scraps and advanced inventory reservations

`RejectDisabledModules` يمنع الوصول المباشر للمسارات المعطلة بـ404، و
`SidebarNavigationService` يستخدم نفس السجل لإخفائها من القائمة.

## القائمة الجديدة

- الرئيسية
- المبيعات
- المشتريات والمخزون
- المالية
- الإعدادات

كل رابط ما زال يخضع للصلاحية الحالية وعزل الشركة والفروع.

## دورة الفاتورة

يمكن إنشاء فاتورة مباشرة بدون حجز أو أمر عمل. النموذج يدعم عدة عناصر من:

- Product
- Service
- Service package
- Custom item

الخدمات تستخدم سعر الفرع الفعلي، والباقات تستخدم السعر الساري والمتاح للفرع
والسيارة، والمنتجات تستخدم مخزن بيع عادي فقط. الخصومات والضرائب والإجماليات
تُحسب في Backend. إصدار المنتج يستخدم خدمات المخزون والتكلفة والترحيل الحالية،
ولا تخصم الخدمة أو الباقة مخزونًا تلقائيًا.

يمكن تحويل عرض سعر معتمد/مرسل/مقبول مباشرة إلى فاتورة واحدة idempotent. تُنقل
كل snapshots والعناصر والخصومات والضرائب، ولا يُنشأ حجز أو أمر عمل.

## التحصيل والترحيل

خدمات المشروع الحالية ما زالت مسؤولة عن الدفعات الجزئية والكاملة، التخصيص،
سندات القبض، تحديث الرصيد والحالة، القيود المحاسبية وحركات المخزون. لم تُضف
أرقام حسابات ثابتة ولم تتغير بيانات تاريخية.

## الطباعة والإرسال

صفحة الطباعة العربية الحالية محفوظة. تنزيل PDF، إرفاقه بالبريد، ورابط WhatsApp
المؤقت لم يكتملوا في هذا التغيير: الحزمة الآمنة `barryvdh/laravel-dompdf` v3.1
متوافقة مع Laravel 9 وPHP 8.2، لكن تنزيل dependencies توقف لعدم الوصول إلى
GitHub، وتم التراجع عن تعديل Composer بالكامل حتى لا يبقى اعتماد ناقص.

## الحماية والبيانات

- لم تُشغل migrations.
- لم تُحذف جداول أو بيانات أو ملفات UAT.
- لا يوجد `migrate:fresh` أو `db:wipe`.
- الوصول للموديولات المعطلة ممنوع من Backend وليس من الـSidebar فقط.

## الاختبارات

- `SidebarNavigationTest`: 4 passed.
- `PhaseTwelveSalesReceivablesTest`: 9 passed.
- `UatDef018AUnifiedCatalogCenterTest`: 5 passed.
- Full suite: `391 passed`, `26 failed`. أغلب الإخفاقات اختبارات قديمة تتوقع إتاحة مسارات الورشة التي أصبحت معطلة عمدًا، مع إخفاق مستقل بسبب Migration غير مطبقة لحقل `default_work_order_warehouse_id`.
- PDF والإرسال بالبريد وWhatsApp لم تُنفذ؛ تعذر تنزيل مكتبة PDF المتوافقة لأن PHP ZIP غير متاح واتصال GitHub فشل، وتم التراجع عن تغييرات Composer بالكامل.
- تغطية: ظهور الموديولات الأساسية، إخفاء ومنع الموديولات المعطلة، عزل الفروع،
  البيع المباشر، المنتج/الخدمة/الباقة/العنصر المخصص، تحويل العرض idempotently،
  التحصيل الجزئي، المخزون والمرتجعات.

## الحالة

Core scope: **READY**.

Invoice PDF/email/WhatsApp distribution: **BLOCKED BY DEPENDENCY DOWNLOAD** ويحتاج
استكمالًا بعد عودة الوصول إلى GitHub، ثم إضافة اختبارات PDF والرابط المؤقت والبريد.
