# UAT-DEF-025 — Account usage-rule checkbox cards

## النتيجة

READY — Account usage-rule cards are visibly selectable, keyboard accessible, persist boolean values correctly, and allow CASH-ALEX-111 to be marked as a branch-required cash account.

## الإصلاح

- تم توحيد تركيب بطاقات قواعد الاستخدام الثلاث عشرة بمكوّن Blade قابل لإعادة الاستخدام.
- البطاقة كاملة قابلة للنقر، والحالة المختارة لها علامة وحد وحدود وخلفية حمراء واضحة.
- عنصر `checkbox` الأصلي ما زال قابلًا للوصول بالـTab والتبديل بمفتاح Space مع مؤشر تركيز واضح.
- كل قيمة ترسل `0` عند عدم الاختيار و`1` عند الاختيار، وتحترم `old()` بعد أخطاء التحقق.
- تمت ترجمة أنواع الحساب الرقابي الظاهرة للمستخدم مع بقاء القيم التقنية كما هي.

## قواعد الأعمال

- الحساب النقدي يجب أن يكون نشطًا، من نوع الأصول، وحساب حركة.
- لا يجوز أن يكون الحساب النقدي رقابيًا أو بنكيًا في الوقت نفسه.
- الحساب ذو العملة المحددة يتطلب تفعيل متعدد العملات؛ عملة الشركة لا تتطلب ذلك.
- قاعدة `requires_branch` محفوظة وتُفرض عند الترحيل بواسطة `JournalEntryValidationService`.

## سلامة البيانات

- لم يتم تعديل الحساب رقم 88 أو أي بيانات تشغيلية.
- لم يتم تشغيل `migrate:fresh` أو `db:wipe`.

## التحقق

- `php artisan optimize:clear --env=uat.local`: ناجح.
- `php artisan test tests/Feature/PhaseFourteenAccountingFoundationTest.php`: ناجح، 13 اختبارًا.
- `php artisan test --filter=Account`: ناجح، 38 اختبارًا.
- `php artisan test --filter=CashBox`: ناجح، 3 اختبارات.
- `php artisan test`: 456 ناجحًا، وفشل اختبار تقويم حجوزات قديم لا يتعلق بالحسابات لأنه لم يجد موعدًا خارج الفترة المعروضة.
- `npm.cmd run build`: ناجح، مع تحذيرات مسارات خطوط وصور الموقع القديمة.
- `php artisan view:cache`: ناجح.
- `git diff --check`: ناجح.
- `vendor/bin/pint --test`: التعديلات الحالية سليمة بعد تنسيقها؛ الفحص الشامل ما زال يرصد مشكلة قديمة في `AccountingPostingService.php`.
- تظهر تحذيرات Deprecation من Dependencies الحالية عند التشغيل على PHP 8.4.
