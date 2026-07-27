# Phase 16 — Egypt Localization Report

> ملاحظة إغلاق: تقرير Phase 16B هو المرجع الأحدث لحالة الجاهزية وسلامة الـMigrations:
> [`phase-16-closure-report.md`](phase-16-closure-report.md). تمت إعادة الـMigrations التاريخية إلى محتواها السابق، وأصبحت Defaults المصرية محصورة في Migration أمامية جديدة غير مدمرة.

تاريخ التنفيذ: 2026-07-27

## 1. الملخص التنفيذي

كان النظام سعوديًا جزئيًا في الإعدادات الافتراضية وبيانات الـSeed/QA وبعض أمثلة الواجهة والاختبارات، وليس في بنية المحاسبة الأساسية. البنية الحالية تدعم تعدد العملات والضرائب القابلة للإعداد.

- كشف الـAudit الأولي 38 ملفًا و26 مجموعة نتائج مصنفة، منها 4 Critical.
- أصبحت Defaults للإنشاء الجديد مصرية، مع EGP وAfrica/Cairo ودعم الهاتف المصري.
- أضيف EGP وVAT14-EG كبيانات مرجعية idempotent، دون فرض الضريبة على الأصناف.
- بقيت SAR عملة إضافية صالحة ولم تُحذف.
- أضيف فحص حقيقي للتاريخ المالي يمنع تغيير العملة الأساسية عند وجود مستندات أو قيود مرحلة.
- لم تُعدل أي مبالغ أو مستندات أو قيود أو ضرائب تاريخية.
- لم يُنفذ ETA أو ZATCA.
- لا يمكن حسم حالة البيانات التشغيلية الحالية قبل استعادة MariaDB من عطلها بصورة غير مدمرة وبعد Backup.

## 2. حالة الدولة

| الإعداد | الحالة النهائية للبيانات الجديدة |
|---|---|
| Country | Egypt / مصر |
| ISO2 / ISO3 | EG / EGY |
| Currency | EGP — Egyptian Pound — جنيه مصري |
| Symbol | `ج.م` بالعربية و`EGP` بالإنجليزية |
| Tax setup | `VAT14-EG` مرجع 14% قابل للتغيير، وليس ضريبة مفروضة تلقائيًا |
| Timezone | `Africa/Cairo` |
| Locale | `ar` للتطبيق و`ar_EG` للـOpenGraph/Faker |
| Phone code | `+20`؛ يدعم E.164 والمحمول والأرضي المصري |
| Address model | حقول عامة للشركة والفروع؛ لا يوجد اعتماد على National Address سعودي |
| Language / direction | Arabic / RTL |

إعدادات الشركة داخل قاعدة البيانات تظل مصدر الحقيقة أثناء التشغيل، وقيم البيئة مجرد Defaults.

## 3. نتائج البحث

الجدول الكامل قبل التعديل موجود في
[`egypt-localization-audit.md`](egypt-localization-audit.md)، ويغطي النتائج EG-001 إلى EG-026. أهم النتائج:

| النتيجة | التصنيف | الخطورة | القرار |
|---|---|---|---|
| Defaults الشركة `SA/SAR/Asia/Riyadh` | `hardcoded_country`, `hardcoded_currency`, `saudi_timezone` | Critical | تغيير Defaults فقط دون Backfill للصفوف |
| Seeder يجبر Seven Ways على SAR | `hardcoded_currency` | Critical | EGP فقط عند غياب التاريخ المرحل |
| حماية تغيير العملة كانت تعيد false دائمًا | `historical_data_do_not_change` | Critical | استبدالها بفحص فعلي للقيود والفواتير |
| حالة DB التاريخية غير قابلة للفحص | `requires_business_decision` | Critical | انتظار MariaDB وبدء Audit read-only |
| VAT15 ثابت في Seed/Forms | `hardcoded_tax` | High | VAT14-EG مرجعية وDefault الواجهة من الشركة |
| PhoneNormalizer سعودي افتراضيًا | `saudi_phone_validation` | High | مصر Default مع بقاء الدعم الدولي |
| Website/Treasury QA سعودي | `saudi_seed_data`, `saudi_test_fixture` | High | توطين مصر كامل |
| SAR كعملة إضافية | `valid_multi_currency_support` | Low | الإبقاء عليها |
| تقارير قديمة تذكر ZATCA خارج النطاق | `saudi_documentation` | Low | الإبقاء كتاريخ قرار |

البحث اللاحق لم يجد قيم Treasury QA السعودية المحظورة في الـSeeder أو الاختبار أو دليلي Phase 15C. ظهور SAR المتبقي مقصور على دعم multi-currency، فحص التاريخ، توثيق الـMigrations التاريخية، واختبارات السلامة.

## 4. العملات

- قبل Phase 16: كانت Defaults الجديدة وSeven Ways Seed تستخدم SAR.
- بعد Phase 16: الشركة الجديدة وSeeders المصرية تستخدم EGP.
- العملات المرجعية: EGP وSAR وUSD وAED؛ لم تُحذف أي عملة.
- التنسيق الديناميكي متاح عبر `MoneyFormatter` ويعرض مثلًا `1,250.00 ج.م`.
- أزيل افتراض `currency_id = 1` من QuotationFactory.
- لم تُحدث أي `currency_id` لمستند أو قيد.
- لم يتم تحويل أي مبلغ تلقائيًا.
- مستندات وقيود SAR الحالية: غير معلوم بسبب توقف MariaDB.

إذا كشف الأمر لاحقًا معاملات SAR مرحلة، لا تُغير العملة الأساسية تلقائيًا. القرار الآمن هو شركة مصرية جديدة أو Opening Migration معتمدة، وليس تحويل المستندات القديمة.

## 5. الضرائب

- قبل Phase 16: كان Seeder ينشئ VAT15 كافتراضي، وبعض Forms/Factories تستخدم 15 مباشرة.
- بعد Phase 16: `VAT14-EG` مرجع مصري idempotent، وVAT صفرية/معفاة أو نسب أخرى تظل قابلة للإنشاء من جدول الضرائب.
- Forms تأخذ Default من ضريبة الشركة الفعلية أو صفر، وليس 14/15 ثابتة.
- الحسابات تستخدم Tax/Mapping المرتبط فعليًا؛ لم تتغير القيود أو snapshots التاريخية.
- استخدام VAT15 في مستندات مرحلة: غير معلوم حتى عودة MariaDB.

## 6. البيانات المصرية

- Defaults: EG، EGP، Africa/Cairo، Arabic، RTL، +20.
- PhoneNormalizer يقبل `01000000000` و`+201000000000` وأرقام الخط الأرضي المصرية، ولا يقصر هاتف الشركة على Mobile.
- بيانات الموقع العامة أصبحت مصرية، وفرع مدينة نصر هو المرجع المنشور.
- Factories واختبارات CRM تستخدم أرقامًا مصرية وهمية.
- لم تُعد تسمية أي شركة أو فرع تشغيلي قائم تلقائيًا.

## 7. ZATCA

لم يوجد Model أو Service أو Job أو Event أو Route أو Table أو Column أو Permission أو Config/Environment key أو QR/XML تنفيذي خاص بـZATCA. الموجود فقط توثيق تاريخي يقرر أنها خارج النطاق. لم يُحذف أو يُعطل شيء ولم يُنشأ ETA code وهمي. ZATCA ليست ضمن نطاق النظام المصري.

## 8. الملفات

### ملفات جديدة

- `config/localization.php`: Defaults مصرية مركزية.
- `app/Services/MoneyFormatter.php`: تنسيق مبالغ حسب عملة المستند/الشركة.
- `app/Services/FinancialHistoryInspector.php`: Audit read-only للتاريخ المالي.
- `app/Console/Commands/AuditEgyptLocalization.php`: الأمر `localization:audit-egypt`.
- `database/migrations/2026_07_27_000000_set_egypt_company_column_defaults.php`: تغيير Defaults الأعمدة فقط، Forward-only ولا يحدث صفوفًا.
- `tests/Feature/EgyptLocalization/EgyptLocalizationStaticTest.php`
- `tests/Feature/EgyptLocalization/EgyptLocalizationDatabaseTest.php`
- `docs/localization/egypt-localization-audit.md`
- `docs/localization/egypt-localization-report.md`
- ملفات Phase 15C الجديدة الموجودة في الـworktree: `TreasuryManualQaSeeder.php` و`TreasuryManualQaTest.php` ودليلا Treasury QA.

### ملفات Phase 16 المعدلة

- `.env.example`, `config/app.php`, `config/website.php`: Defaults مصرية والموقع المصري.
- `Company.php`, `CompanySettingsService.php`, `PhoneNormalizer.php`: Defaults وحماية التاريخ والهاتف.
- الـMigrations التاريخية للشركة: أُعيدت إلى محتواها السابق ولم تعد ضمن التغييرات؛ Defaults المصرية موجودة في Migration أمامية جديدة فقط.
- `ReferenceDataSeeder`, `SevenWaysTenantSeeder`, `SevenWaysOperationalSeeder`, `TreasuryFoundationSeeder`, `TreasuryManualQaSeeder`: EGP/Egypt/VAT configurable وQA مصرية.
- Factories الخاصة بالعملاء وCRM والعملات والمبيعات والمشتريات: Fixtures مصرية ودون Currency ID ثابت.
- Purchase/Supplier Forms وQuotation print وCompany settings وWebsite layout: ضريبة/عملة/هاتف/Locale ديناميكية.
- اختبارات Phase 4/5/9/10/11/12/13 وPublicWebsite وCrmNormalizer وTreasuryManualQa: Fixtures وتوقعات مصرية.
- `docs/testing/phase-15c-treasury-manual-cycle.md` و`phase-15c-treasury-qa-report.md`: دورة QA مصرية بالكامل.

ملفات Controllers/Services/Views الأخرى الظاهرة في `git status` كانت تغييرات Phase 15C موجودة قبل Phase 16 وتم الحفاظ عليها دون تنظيف أو Reset.

## 9. الاختبارات وأوامر الجودة

| الأمر | النتيجة |
|---|---|
| `php artisan optimize:clear` | Passed، مع PHP 8.4 deprecation warnings |
| `php artisan route:list` | Passed، 482 route |
| `php artisan migrate:status` | Blocked: MariaDB رفضت الاتصال، SQLSTATE 2002 |
| `php artisan localization:audit-egypt` | Blocked: MariaDB رفضت الاتصال، ولم تتغير بيانات |
| `php artisan test` | Timed out بعد 300 ثانية بسبب بيئة DB المتوقفة؛ أوقفت عمليتي الاختبار اللتين شغلهما الأمر فقط |
| `php artisan test --filter=EgyptLocalization` | 3 Static passed، 4 DB tests blocked بـSQLSTATE 2002 |
| `vendor/bin/pint --test` | Passed: 1282 files |
| `composer validate` | Passed |
| `npm.cmd run build` | Passed؛ توجد warnings قديمة عن assets تُحل وقت التشغيل |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed |

اختبارات Phase 16 تغطي EGP/SAR، formatter، Egypt phones، عدم ثبات الضريبة في Blade، Treasury QA المصرية، idempotency، حماية posted SAR history، read-only command، ورفض fallback إلى SAR. اختبارات DB لم تصل إلى assertions بسبب توقف MariaDB.

## 10. Treasury QA

| العنصر | قبل | بعد |
|---|---|---|
| الفروع | `QA-RUH`, `QA-DMM` | `QA-CAI`, `QA-GIZ` |
| المدن | Riyadh, Dammam | القاهرة، الجيزة |
| العملة | SAR | EGP |
| المستخدمون | Riyadh/Dammam Cashier | Cairo/Giza Cashier |
| الصناديق | أكواد RUH/DMM | `QA-CAI-MAIN`, `QA-CAI-SALES`, `QA-GIZ-MAIN` |
| GL الوصفي | RUH/DMM | `QA-CASH-CAI-M`, `QA-CASH-CAI-S`, `QA-CASH-GIZ-M` |
| البنك | Saudi QA names | `QA Egypt Bank`, `QA-BANK-CAI`, `QA-BANK-GIZ` |

Seeder يبحث عن EGP نشطة صراحة ويفشل برسالة واضحة دون fallback إلى SAR. لا ينشئ مستندات أو قيودًا أو أرصدة، ويظل local/testing وidempotent. المحاسب يملك create/submit/review/post/reverse حسب صلاحيات النظام، ولا يملك approve؛ الاعتماد للمدير لتحقيق Separation of Duties. اختبارات عزل القاهرة/الجيزة في الاتجاهين موجودة لكنها تنتظر MariaDB.

## 11. المخاطر والقرارات

- يلزم Backup وإصلاح MariaDB خارج هذا Patch قبل معرفة أعداد SAR/VAT15 التاريخية.
- Migration الجديدة لم تُشغل لأن قاعدة البيانات متوقفة؛ هي تغير Defaults schema فقط ولا تحدث صفوفًا.
- صفوف البنوك السعودية التي قد تكون موجودة تاريخيًا لم تُحذف. بعد عودة DB يلزم قرار هل تُعطل بعد إثبات عدم استخدامها.
- تقارير المراحل القديمة والمطبوعات المحفوظة تاريخيًا لم تُعدّل.
- Laravel 9 على PHP 8.4 يظهر deprecation warnings من Dependencies؛ لم تُرق Laravel أو Dependencies.
- إذا وجدت معاملات SAR مرحلة، يلزم قرار إداري بين شركة مصرية جديدة أو Opening Migration معتمدة.

## 12. النتيجة النهائية

- [x] الدولة الافتراضية مصر
- [x] العملة الافتراضية EGP للبيانات الجديدة
- [x] المنطقة الزمنية Africa/Cairo
- [x] الهاتف المصري مدعوم
- [x] لا يوجد +966 ثابت كـDefault
- [x] لا يوجد SAR ثابت في واجهات EGP المعدلة
- [x] لا يوجد VAT 15% ثابت في منطق التشغيل المعدل
- [x] الضرائب Configurable
- [x] لا يوجد اعتماد تنفيذي على ZATCA
- [x] المستندات التاريخية لم تتغير
- [x] العملات الأخرى، ومنها SAR، ما زالت مدعومة
- [ ] اختبارات قاعدة البيانات ناجحة — تنتظر استعادة MariaDB بعد Backup

بعد استعادة MariaDB، الترتيب الآمن هو: `migrate:status`، ثم `localization:audit-egypt` بدون Flag، مراجعة التقرير، تشغيل الاختبارات، ثم فقط عند ثبوت عدم وجود تاريخ مالي مرحل يمكن اتخاذ قرار تشغيل `--apply-safe-defaults`.
