# Phase 16 — Egypt Localization Audit (قبل التعديل)

> تحديث Phase 16B: أُعيدت أي تعديلات Localization داخل الـMigrations التاريخية إلى محتواها المعتمد، وأُضيفت Migration أمامية مستقلة لتغيير Defaults فقط. راجع [`phase-16-closure-report.md`](phase-16-closure-report.md).

تاريخ الفحص: 2026-07-27  
النطاق: `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `tests/`, `docs/`, `public/`, `.env.example`, وملفات Composer/Vite/NPM.

## ملخص ما قبل التعديل

أظهر البحث النصي ومراجعة مسارات التنفيذ 38 ملفًا يحتوي على مرجع سعودي أو توثيق ZATCA أو fixture سعودي. النظام متعدد العملات والضرائب في الأساس، لكن defaults وSeeders وبيانات الموقع وPhoneNormalizer كانت سعودية جزئيًا. توجد 4 نتائج Critical: defaults في schema، إجبار Seven Ways على SAR، hook حماية تغيير العملة الذي يعيد `false` دائمًا، وعدم إمكانية فحص التاريخ المالي الفعلي بسبب توقف MariaDB.

سيتم تعديل defaults الجديدة، Seeders/Factories/QA والتوثيق والواجهات ذات القيم الثابتة، مع إبقاء SAR كعملة إضافية. لن تُعدل أي Currency/Tax IDs أو مبالغ أو مستندات أو قيود تاريخية. إشارات ZATCA في تقارير المراحل تعني أنها لم تُنفذ وليست مكونات تشغيلية.

## النتائج المصنفة

| ID | الملف | السطر أو الجزء | القيمة الحالية | التصنيف | الخطورة | الإجراء المقترح |
|---|---|---|---|---|---|---|
| EG-001 | `database/migrations/2026_07_25_100000_create_companies_table.php` | defaults | `SA`, `SAR`, `Asia/Riyadh` | `hardcoded_country`, `hardcoded_currency`, `saudi_timezone` | Critical | تغيير defaults للإنشاء الجديد وإضافة Migration لتغيير default الأعمدة فقط دون لمس الصفوف |
| EG-002 | `database/migrations/2026_07_25_110100_add_operational_settings_to_companies.php` | currency backfill | fallback إلى `SAR` | `hardcoded_currency` | High | استخدام `EGP` في الإنشاء الجديد؛ عدم إعادة تشغيل backfill على بيانات قائمة |
| EG-003 | `database/seeders/SevenWaysTenantSeeder.php` | إنشاء Seven Ways | `SA/SAR/Asia/Riyadh` | `saudi_seed_data` | Critical | `EG/EGP/Africa/Cairo` للبيانات الجديدة فقط |
| EG-004 | `database/seeders/SevenWaysOperationalSeeder.php` | بداية `run()` | يجبر الشركة على SAR | `hardcoded_currency` | Critical | EGP فقط عند عدم وجود تاريخ مالي مرحل، وإلا تقرير بدون تعديل |
| EG-005 | `app/Services/CompanySettingsService.php` | `hasPostedFinancialMovements()` | يعيد `false` دائمًا | `historical_data_do_not_change` | Critical | فحص `journal_entries` والمستندات المرحلة فعليًا قبل تغيير العملة |
| EG-006 | `database/seeders/ReferenceDataSeeder.php` | العملات/backfill | SAR أولًا وfallback SAR | `saudi_seed_data`, `valid_multi_currency_support` | High | جعل EGP المرجع الافتراضي مع إبقاء SAR/USD/AED |
| EG-007 | `database/seeders/SevenWaysOperationalSeeder.php` | `VAT15` | 15% default | `hardcoded_tax` | High | إنشاء `VAT14-EG` كمرجع configurable؛ عدم تغيير ضريبة تاريخية |
| EG-008 | Purchase/Supplier Blade forms | قيمة `tax_rate` | `value="15"` | `hardcoded_tax` | High | أخذ default من إعدادات الشركة أو 0 بدون تثبيت النسبة |
| EG-009 | Sales/Purchasing factories | totals/rates | `tax_rate=15` و`total=115` | `saudi_test_fixture` | Medium | Fixtures مصرية 14% أو zero tax حسب غرض الاختبار |
| EG-010 | `app/Services/PhoneNormalizer.php` | default/regex | `SA`, `05`, `966` | `saudi_phone_validation` | High | default من Config = EG ودعم `01...`, `+20`, والأرضي بدون قصر الحقل على mobile |
| EG-011 | `CustomerFactory`, `LeadFactory` | phone | `05...`, `966...` | `saudi_test_fixture` | Medium | أمثلة مصرية وهمية |
| EG-012 | `config/website.php` | branches/phones | فروع وهواتف السعودية | `hardcoded_country` | High | إبقاء بيانات مصر فقط وفق نطاق النظام |
| EG-013 | `resources/views/website/layouts/app.blade.php` | OpenGraph locale | `ar_SA` | `saudi_locale` | Medium | `ar_EG` |
| EG-014 | `.env.example`, `config/app.php` | timezone default | `UTC` | `requires_business_decision` | Medium | default بيئة مصرية `Africa/Cairo` مع بقاء Company timezone مصدر التشغيل |
| EG-015 | `database/seeders/TreasuryFoundationSeeder.php` | system banks | 4 بنوك سعودية | `saudi_seed_data` | High | إضافة مراجع بنوك مصرية وعدم حذف صفوف قد تكون مستخدمة في بيانات قائمة |
| EG-016 | `database/seeders/TreasuryManualQaSeeder.php` | كل QA branches/accounts | `QA-RUH`, `QA-DMM`, Riyadh/Dammam | `saudi_test_fixture` | High | القاهرة/الجيزة وEGP صراحة، بدون fallback |
| EG-017 | `tests/Feature/TreasuryManualQaTest.php` | fixtures/assertions | Riyadh/Dammam scope | `saudi_test_fixture` | Medium | تحديث عزل القاهرة/الجيزة وإضافة فحوص static للـSeeder |
| EG-018 | Treasury QA docs | الحسابات والدورات | SAR/Riyadh/Dammam | `saudi_documentation` | Medium | استبدال كامل بقيم مصرية؛ يسمح ذكر القديم داخل Audit فقط |
| EG-019 | Phase 9–15 tests | currencies | SAR fixtures | `saudi_test_fixture` | Medium | EGP حيث الاختبار يختبر default؛ إبقاء اختبار عملة أجنبية مستقل عند الحاجة |
| EG-020 | `tests/Feature/PhaseFourSettingsTest.php` | timezone | `Asia/Riyadh` | `saudi_test_fixture` | Low | `Africa/Cairo` |
| EG-021 | `tests/Unit/CrmNormalizerTest.php` | phone | `+966...` | `saudi_test_fixture` | Medium | أرقام مصرية مع اختبار أن الرقم الدولي غير المصري يظل مدعومًا |
| EG-022 | `database/factories/QuotationFactory.php` | `currency_id` | ID ثابت `1` | `hardcoded_currency` | High | عدم افتراض ID؛ state/relationship أو قيمة تُمَرر من الاختبار |
| EG-023 | `TreasuryFoundationSeeder` وCurrency tables | SAR والبنوك الأجنبية | عملات/بنوك إضافية | `valid_multi_currency_support` | Low | لا حذف تلقائي ولا تعديل للمرجع المستخدم تاريخيًا |
| EG-024 | `docs/phase-*.md` | ZATCA | توثيق أنه خارج النطاق | `saudi_documentation` | Low | الإبقاء كتاريخ قرار؛ لا توجد Models/Routes/Tables/Config تنفيذية لـZATCA |
| EG-025 | `docs/phase-04-report.md`, `phase-05-report.md`, `phase-15a-*` | defaults سعودية قديمة | SAR/VAT15/Phone/بنوك | `saudi_documentation` | Low | إبقاء التقرير التاريخي مع توضيح superseded في تقرير Phase 16 |
| EG-026 | DB التشغيلية الحالية | العملات والضرائب والمستندات | غير متاح | `historical_data_do_not_change`, `requires_business_decision` | Critical | Read-only command؛ انتظار إصلاح MariaDB بعد Backup |

## ما سيبقى

- صف عملة SAR وأي عملات أخرى لأنها دعم multi-currency صالح.
- Currency/Tax snapshot على المستندات والقيود التاريخية.
- حقول عامة مثل `tax_number`, `commercial_registration`, `postal_code`, `building_number`, `iban` إن وجدت؛ ليست سعودية بطبيعتها.
- إشارات تقارير المراحل التي توثق صراحة أن ZATCA لم تُنفذ.
- تخزين timestamps الحالي؛ التغيير فقط في default العرض/الشركة.

## حالة البيانات التاريخية قبل التعديل

MariaDB المحلية متوقفة وتقرير المحرك السابق يشير إلى مشاكل Aria/InnoDB. لذلك لم يمكن إثبات عدد مستندات SAR أو قيود SAR أو استخدام VAT 15% في مستندات مرحلة. لن يتم استنتاج أن البيانات آمنة ولن يتم تعديل أي صف تشغيلي. سيقدم `localization:audit-egypt` نفس الفحص Read-only عند عودة قاعدة البيانات، ولن يطبق defaults إلا بـFlag صريح وبعد ثبوت عدم وجود تاريخ مالي مرحل.

## ZATCA

البحث لم يجد Models أو Services أو Jobs أو Routes أو Tables أو Columns أو Config/Environment keys أو QR/XML خاصًا بـZATCA. النتائج الموجودة توثيق تاريخي يقول إن التكامل خارج النطاق. لا حذف ولا ETA/ZATCA code في Phase 16.
