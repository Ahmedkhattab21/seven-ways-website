# UAT-DEF-014 — Cash over/short adjustment UI

## النتيجة

READY — Approved cash count differences can be converted into permission-safe cash over/short adjustments and posted through the UI

## المشكلة والسبب

خدمة `CashOverShortAdjustment` ودورة `draft → pending_approval → approved → posted → reversed`
كانتا موجودتين، لكن صفحة جلسات الخزائن لم تعرض إنشاء التسوية أو حالتها أو إجراءاتها.

## الواجهة الجديدة

- العد المعتمد ذو الفرق غير الصفري يعرض عجز أو زيادة بقيمة واضحة.
- المستخدم الذي يملك `treasury.cash_over_short.view` يستطيع إدخال سبب وإنشاء التسوية.
- بطاقة التسوية تعرض النوع والمبلغ والحالة والسبب ورقم القيد بعد الترحيل.
- كل زر يظهر فقط في الحالة المناسبة وبحسب الصلاحية الحالية، بدون توسيع صلاحيات الأدوار.

## الصلاحيات ودورة الحالات

| الحالة | الإجراء | الصلاحية |
| --- | --- | --- |
| لا توجد تسوية | إنشاء التسوية | `treasury.cash_over_short.view` |
| `draft` | إرسال للاعتماد | `treasury.cash_over_short.view` |
| `pending_approval` | اعتماد | `treasury.cash_over_short.approve` |
| `approved` | ترحيل | `treasury.cash_over_short.post` |
| `posted` | عكس بسبب إلزامي | `treasury.cash_over_short.post` |

## القيد المحاسبي

- العجز: مدين حساب فروق الخزينة، دائن حساب الخزينة.
- الزيادة: مدين حساب الخزينة، دائن حساب فروق الخزينة.
- الترحيل يستخدم `TreasuryJournalService` لضمان قيد متوازن وIdempotent.
- عند غياب الربط تظهر رسالة عربية تطلب ضبط حساب الفروق، ولا يُستخدم حساب افتراضي.
- الرصيد لا يُخزن أو يُعدل يدويًا؛ يتغير من القيد المرحّل ويُحسب من دفتر الأستاذ.

## سلامة البيانات

لم تُعدّل الجلسة `CAI-MAIN-CS-2026-000006`، ولم تُنشأ لها تسوية، ولم يُعدل رصيد أو قيد مباشرة.

## التحقق

- `php artisan optimize:clear --env=uat.local`: ناجح.
- `php artisan test --filter=CashOverShort`: ناجح — 6 اختبارات.
- `php artisan test --filter=CashSession`: ناجح — 12 اختبارًا.
- `php artisan test --filter=Treasury`: ناجح — 27 اختبارًا.
- `php artisan test`: ناجح — 354 اختبارًا.
- `vendor/bin/pint --test`: ناجح، مع تحذيرات Deprecation من Dependencies على PHP 8.4.
- `npm.cmd run build`: ناجح، مع تحذيرات مسارات Assets قديمة غير مرتبطة بهذا التعديل.
- `php artisan view:cache`: ناجح.
- `git diff --check`: ناجح.
