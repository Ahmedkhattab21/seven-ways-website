# UAT-DEF-015 — Customer document sequence

## النتيجة

READY — Customer document sequences can be configured from the UI and setup progress detects missing required branch sequences

## سبب المشكلة

`CustomerService` يطلب رقمًا من نوع `customer` للفرع الحالي، بينما نموذج تسلسل المستندات كان يستخدم قائمة منفصلة لا تعرض `customer` أو `lead`. كذلك كانت خطوة تجهيز النظام تعتبر وجود أي تسلسل نشط كافيًا.

## الإصلاح

- أصبح `config/document_sequences.php` المصدر الموحد للأنواع والأسماء العربية والنطاق ومتطلبات التجهيز.
- تستخدم Validation والنماذج وقائمة العرض ومؤشر التجهيز نفس المصدر.
- تعرض صفحة الإعداد `customer — العملاء` و`lead — العملاء المحتملون`.
- يعرض مؤشر التجهيز الأنواع المكتملة والناقصة والفرع المتأثر.
- عند غياب التسلسل تظهر رسالة عربية تحدد نوع المستند والفرع ومسار الإصلاح.
- لم يُضف أي Fallback صامت بين مستوى الشركة والفرع.

## سلامة البيانات

لم يُنشأ تسلسل أو عميل UAT عبر Seeder أو SQL، ولم تتغير أرقام أو تسلسلات قائمة. إنشاء العميل واستهلاك الرقم داخل Transaction واحدة، لذلك لا يُستهلك الرقم عند فشل الحفظ.

## الاختبارات

- `php artisan optimize:clear --env=uat.local`: ناجح.
- `php artisan test --filter=DocumentSequence`: 6 ناجحة.
- `php artisan test --filter=Customer`: 7 ناجحة.
- `php artisan test --filter=CompanySetupProgress`: 1 ناجح.
- Full suite: 359 ناجحة، واختبار واحد تعطل بسبب سجل `AED` متبقٍ من تشغيلين متوازيين بعد Timeout؛ إعادة الاختبار المتأثر منفردًا: 6 ناجحة.
- `vendor/bin/pint --test` للملفات المعدلة: ناجح.
- `npm.cmd run build`: ناجح مع تحذيرات أصول الموقع القديمة التي تُحل وقت التشغيل.
- `php artisan view:cache`: ناجح.
- `git diff --check`: ناجح.

## تحذير البيئة

PHP 8.4 يعرض Deprecation Warnings من Laravel 9 وحزم Symfony/Pint الحالية؛ لم تُغيّر Dependencies ضمن هذا الإصلاح.
