# UAT-DEF-029 — Document sequences 500

## التشخيص

- Error reference: `c1cc51d5-537f-44b3-967c-9aa6ad70575b`
- الاستثناء الفعلي: `Illuminate\Database\QueryException` برمز `SQLSTATE[23000]`.
- السبب الجذري: محاولة إنشاء تسلسل سنوي مكرر اصطدمت بالقيد الفريد `document_sequences_scope_key_unique`.
- القيمة المكررة: `2:5:cash_box_session:2026`.
- الاستعلام الفاشل: `INSERT` في `document_sequences` للشركة `2` والفرع `5` والنوع `cash_box_session`.
- Route: `POST /settings/reference/document-sequences`.
- Controller action: `ReferenceDataController@store` ثم `save`.
- المستخدم: `owner@sevenways.test` (`userId=16`).
- الشركة: Seven Ways (`company_id=2`).
- الفرع المستهدف في الطلب: `ALEX — فرع الإسكندرية` (`branch_id=5`).
- سياق الفرع الحالي لم يُسجل داخل الـException، والمستخدم لا يملك فرعًا افتراضيًا؛ لذلك لم يتم استنتاجه. الـPayload نفسه كان يستهدف الإسكندرية صراحة.
- الـStack trace بدأ من `ReferenceDataController.php:167` ثم معاملة الحفظ عند السطر `148`.

كان تسلسل الإسكندرية موجودًا بالفعل بالسجل `#198` بالقالب
`{BRANCH}-CS-{YYYY}-`، والرقم الحالي `0`، وعدد الخانات `6`،
والتصفير `yearly`، والحالة نشطة. لم تُعدّل أو تُحذف أي بيانات UAT.

## الإصلاح

- إضافة تحقق مقفول داخل Transaction يمنع وجود تسلسل نشط مكرر لنفس الشركة والفرع والنوع.
- تحويل تعارض القيد الفريد المتوقع إلى Validation عربية:
  `يوجد تسلسل فعال بالفعل لهذا النوع في الفرع المحدد.`
- إبقاء أخطاء قاعدة البيانات الأخرى ظاهرة دون تحويلها إلى رسالة عامة.
- عرض الأنواع القديمة والفروع المفقودة وفترات التصفير غير المدعومة كتحذيرات إدارية بدل كسر الصفحة.
- إظهار كود الفرع واسمه في نموذج التسلسل.
- طلب GET للصفحة للعرض فقط ولا ينشئ أو يعدّل أي سجل.

## نتيجة تسلسل ALEX

الإعداد الموجود يطابق المطلوب، وأثبت الاختبار أن أول رقم مولد هو:

`ALEX-CS-2026-000001`

## الملفات

- `app/Http/Controllers/ReferenceDataController.php`
- `resources/views/reference/index.blade.php`
- `resources/views/reference/form.blade.php`
- `tests/Feature/DocumentSequencePageSafetyTest.php`
- `docs/testing/uat-def-029-document-sequences-500.md`

## الاختبارات

يغطي `DocumentSequencePageSafetyTest`:

- فتح الصفحة بدون بيانات ومع أكثر من فرع وسياق الإسكندرية.
- عرض النوع القديم والفرع المفقود بأمان.
- منع التكرار برسالة عربية وبدون تغيير عدد السجلات.
- توليد أول رقم ALEX الصحيح.
- عدم تعديل البيانات عبر GET.
- رفض المستخدم غير المصرح له بـ403.

### النتائج الفعلية

- `php artisan optimize:clear --env=uat.local`: ناجح.
- `php artisan test --filter=DocumentSequence`: ناجح، 11 اختبارات.
- `php artisan test --filter=ReferenceData`: لا توجد اختبارات مطابقة.
- `php artisan test --filter=CashBoxSession`: ناجح، 6 اختبارات.
- `php artisan test --filter=SetupCompletion`: ناجح، 8 اختبارات.
- الاختبار الكامل: 474 ناجح، وفشل اختبار قديم واحد في `PhaseNineQuotationAppointmentTest` لأن موعده خارج نطاق الشهر الحالي.
- Pint للملفات المعدلة: ناجح. الفحص الكامل متوقف على تنسيق سابق في `AccountingPostingService.php`.
- Vite build وBlade view cache و`git diff --check`: ناجحة.
- تظهر تحذيرات Deprecation من مكتبات Laravel 9 مع PHP 8.4.

READY — Document sequences page opens safely for the owner and supports adding the Alexandria cash-session sequence without a 500 error
