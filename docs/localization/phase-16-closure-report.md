# Phase 16B — Egypt Localization Closure and Readiness Gate

تاريخ المراجعة: 2026-07-27

## 1. Readiness Decision

**NO-GO — Phase 17 is blocked**

## 2. Executive Summary

تمت مراجعة سلامة Migrations، إعدادات الموقع، Defaults المصرية، تنسيق العملات، حماية التاريخ المالي، Treasury Foundation وTreasury QA، وأي اعتماد حكومي سعودي أو مصري. أُعيدت Migrations التاريخية إلى محتواها المعتمد، وحُصرت Defaults الجديدة في Migration أمامية غير مدمرة. كما أضيف بنك نظامي محايد، وأُغلقت ثغرات فحص Opening Balances وحماية أمر الـAudit.

الكود والاختبارات الساكنة واختبارات الموقع سليمة. البوابة ما زالت مغلقة لأن MariaDB لا تبدأ بسبب تلف Aria/InnoDB، ولا توجد نسخة Backup موثقة، وبالتالي لم تُطبق Migration الجديدة ولم يمكن فحص البيانات التاريخية أو تشغيل اختبارات قاعدة البيانات.

### Git snapshot قبل تعديلات Phase 16B

- الفرع: `main`.
- Working tree كان يحتوي تغييرات Phase 15C وPhase 16 غير ملتزمة.
- 58 ملفًا tracked معدلًا، مع ملفات Phase 16 وTreasury QA جديدة untracked.
- `git diff --check` كان نظيفًا.
- تم الحفاظ على كل تغييرات Phase 15A/15B/15C ولم يُستخدم reset أو checkout أو stash.

## 3. Migration Integrity

- كانت Phase 16 قد عدلت Migrationين تاريخيتين متتبعتين سبق اعتمادهما:
  - `2026_07_25_100000_create_companies_table.php`
  - `2026_07_25_110100_add_operational_settings_to_companies.php`
- أُعيدت فقط تعديلات Phase 16 داخلهما إلى القيم التاريخية `SA/SAR/Asia/Riyadh`. لا يظهر الملفان الآن في `git status` أو `git diff`.
- Migration الجديدة `2026_07_27_000000_set_egypt_company_column_defaults.php`:
  - تغير schema defaults فقط إلى `EG/EGP/Africa/Cairo`.
  - لا تحتوي `UPDATE` أو `INSERT` أو `DELETE` أو `DROP` أو `RENAME`.
  - تتحقق من وجود الجدول والأعمدة قبل التنفيذ.
  - تستخدم SQL متوافقًا مع MySQL/MariaDB.
  - لا تغير أي شركة موجودة تلقائيًا.
  - `down()` مقصود أن يكون no-op؛ الرجوع إلى Defaults سعودية يحتاج Migration أمامية وقرارًا صريحًا.
- `migrate:status`: **Blocked** — `SQLSTATE[HY000] [2002]`.
- `migrate --pretend`: **Blocked** قبل توليد SQL — `SQLSTATE[HY000] [2002]`.
- التطبيق الفعلي: **لم يُنفذ**؛ ممنوع قبل Backup وعودة MariaDB والتحقق من الـAudit.

## 4. Website Configuration

- `config/website.php` ما زال مستخدمًا ولم يصبح فارغًا.
- مصدر الحقيقة التشغيلي هو بيانات الشركة، والـConfig يوفر Fallback آمنًا عند غياب DB.
- Defaults المتاحة: `EG`, `EGP`, `Africa/Cairo`, `ar_EG`، وفرع مصري عام بمدينة نصر.
- لا يوجد رقم هاتف حقيقي أو `+966` أو عنوان/فرع سعودي نشط.
- أُزيل عمود السعودية الفارغ من Footer، وأصبحت Meta descriptions مصرية.
- قصة تأسيس الشركة التي تذكر السعودية بقيت كنص تاريخي؛ تغييرها يحتاج اعتمادًا تجاريًا.
- `PublicWebsite`: **12 passed** بدون اتصال قاعدة بيانات.
- `view:cache`: **Passed**.

## 5. Treasury Foundation

- أُزيلت Seed records السعودية لأنها كانت Defaults خاصة بدولة وليست ضرورة للنظام.
- يحتاج عدد من اختبارات وواجهات الخزينة وجود بنك نظامي واحد؛ لذلك أُضيف بنك محايد idempotent:
  - Code: `OTHER`
  - Arabic: `بنك آخر`
  - English: `Other Bank`
  - بلا حساب أو IBAN أو SWIFT حقيقي.
- Seeder لا يحذف أو يعطل أي بنك موجود.
- `TreasuryManualQaSeeder` مستقل وينشئ `QA Egypt Bank` والحسابين `QA-BANK-CAI` و`QA-BANK-GIZ`.

## 6. Egypt Defaults

| Setting | Value |
|---|---|
| Country | `EG` / Egypt |
| Currency | `EGP`؛ مع بقاء `SAR`, `USD`, والعملات الأخرى |
| Timezone | `Africa/Cairo` |
| Locale | `ar`، و`ar_EG` للموقع/OpenGraph |
| Phone | `+20` افتراضيًا؛ يدعم المحمول والأرضي وE.164 والأرقام الدولية الصريحة |
| Tax | `VAT14-EG` مرجع configurable وغير مفروض تلقائيًا |

`MoneyFormatter` يفضل عملة المستند ثم الشركة، ويحترم decimal places، ويدعم العملة غير المعروفة بدون crash، ولا يغير القيمة ولا يحول سعر صرف.

## 7. Financial History Audit

| Check | Result |
|---|---|
| مستندات SAR مرحلة | غير معلوم — DB متوقفة |
| قيود SAR مرحلة | غير معلوم — DB متوقفة |
| VAT15 تاريخية | غير معلوم — DB متوقفة |
| Opening Balances مرحلة | غير معلوم — DB متوقفة |
| عدد النتائج | غير متاح |
| بيانات تاريخية تغيرت | **لا** |

`FinancialHistoryInspector` أصبح read-only، company-scoped، ويتحقق من وجود الجداول/الأعمدة ويميز Draft عن Posted ويشمل Opening Balances. لا يجوز تغيير العملة الأساسية أو الضرائب قبل تشغيل `localization:audit-egypt` ومراجعة النتائج. إذا ظهر تاريخ SAR/VAT15، المطلوب قرار Migration تجاري ولا يُسمح بتحويله تلقائيًا.

## 8. MariaDB

- الإصدار الظاهر في السجل: MariaDB `10.4.32`.
- لا Listener على `3306`، ولا process عالق باسم MySQL/MariaDB، ولا Windows service مسجلة.
- `my.ini` يشير بصورة متسقة إلى `D:/xxamp/mysql` و`D:/xxamp/mysql/data`.
- مساحة قرص D المتاحة نحو 129 GB، ومجلد البيانات قابل للقراءة والكتابة.
- السجل يثبت:
  - `Aria recovery failed`
  - Missing checkpoint
  - `InnoDB: Missing MLOG_CHECKPOINT`
  - فشل تهيئة InnoDB
- Root cause الحالي تلف/فشل recovery في ملفات المحرك، وليس Port أو process أو path بسيطًا.
- لم تُصلح المشكلة، ولم يُعدل أو ينقل أو يحذف أي ملف داخل data directory.
- لم يؤخذ Backup في هذه المهمة.
- **Database recovery requires DBA/manual intervention** بعد Copy Backup كامل ومتحقق منه.
- قاعدة الاختبار معرفة باسم `laravel_test_project_testing`، و`tests/TestCase.php` يمنع RefreshDatabase على قاعدة غير testing، لكنها غير قابلة للاتصال ما دام السيرفر متوقفًا.
- خطوات الاستعادة غير المدمرة موثقة في `docs/environment/mariadb-safe-recovery-runbook.md`.

## 9. Tests

| Command | Passed | Failed | Blocked | Notes |
|---|---:|---:|---:|---|
| `php artisan test --filter=EgyptLocalizationStaticTest` | 4 | 0 | 0 | 0.57s على PHP 8.2 |
| `php artisan test --filter=EgyptLocalization` | 4 | 0 | 5 | DB tests: SQLSTATE 2002 |
| `php artisan test --filter=PublicWebsite` | 12 | 0 | 0 | 13.55s على PHP 8.2؛ يعمل بدون DB |
| `php artisan test --filter=TreasuryManualQa` | 0 | 0 | all | لم يُشغل بعد ثبوت توقف DB |
| `php artisan test --filter=PhaseFifteen` | 0 | 0 | all | لم يُشغل بعد ثبوت توقف DB |
| Full test suite | غير مكتمل | 0 functional | DB-dependent | المحاولة السابقة توقفت زمنيًا أثناء عطل DB |
| `vendor/bin/pint --test` | 1282 files | 0 | 0 | PHP 8.4 deprecation warnings فقط |
| `composer validate` | 1 | 0 | 0 | Valid |
| `npm.cmd run build` | 1 | 0 | 0 | نجح مع asset warnings موجودة مسبقًا |
| `php artisan view:cache` | 1 | 0 | 0 | Passed |
| `php artisan route:list` | 482 routes | 0 | 0 | Passed |
| `git diff --check` | 1 | 0 | 0 | Passed |
| PHP syntax lint | جميع ملفات Phase 16B الرئيسية | 0 | 0 | Passed |

الأوامر العامة السابقة شُغلت على PHP `8.4.21`. أُعيدت اختبارات Static وPublicWebsite وView Cache بنجاح على PHP XAMPP `8.2.12`، وهو الموصى به لدورة التحقق بعد عودة DB لتقليل Deprecations الخاصة بـLaravel 9.

## 10. Files

### ملفات جديدة

- `app/Console/Commands/AuditEgyptLocalization.php`: Audit read-only وخيار safe defaults محمي.
- `app/Services/FinancialHistoryInspector.php`: فحص تاريخ مالي آمن ومحدد بالشركة.
- `app/Services/MoneyFormatter.php`: تنسيق عملات ديناميكي.
- `config/localization.php`: Defaults المصرية العامة.
- `database/migrations/2026_07_27_000000_set_egypt_company_column_defaults.php`: تغيير Defaults أمامي غير مدمر.
- `database/seeders/TreasuryManualQaSeeder.php`: بيانات QA مصرية idempotent.
- `tests/Feature/EgyptLocalization/*`: اختبارات localization الساكنة وقاعدة البيانات.
- `tests/Feature/TreasuryManualQaTest.php`: QA وعزل القاهرة/الجيزة وSOD.
- `docs/environment/mariadb-safe-recovery-runbook.md`: Runbook الاستعادة.
- `docs/localization/*`: Audit وتقارير Phase 16/16B.
- `docs/testing/*`: أدلة Treasury QA المصرية.

### ملفات معدلة

- `.env.example`, `config/app.php`, `config/website.php`: Defaults مصرية وFallbacks.
- `app/Models/Company.php`, `CompanySettingsService.php`, `PhoneNormalizer.php`: Defaults، الهاتف، وحماية التاريخ.
- Controllers/Models/Services الخاصة بالخزينة: إزالة افتراضات SAR/VAT15 الثابتة مع الحفاظ على قواعد Phase 15.
- `ReferenceDataSeeder`, `SevenWaysOperationalSeeder`, `SevenWaysTenantSeeder`, `TreasuryFoundationSeeder`: EGP/VAT14-EG وبيانات مرجعية محايدة.
- Factories واختبارات المراحل السابقة: fixtures مصرية بدون تغيير سلوك تجاري.
- Views الخاصة بالإعدادات والمبيعات والمشتريات والخزينة والموقع: عملة وضريبة ديناميكية وبيانات مصرية.
- `lang/ar/website.php`, `lang/en/website.php`, وملفات الموقع: إزالة الـDefaults السعودية النشطة.
- `egypt-localization-report.md`, `egypt-localization-audit.md`: ملاحظة إغلاق وتصحيح سياسة الـMigrations.

### ملفات لم تُعدل

- ملفات بيانات MariaDB: لحماية البيانات ومنع recovery مدمر.
- Migrations التاريخية: أُعيدت بالكامل إلى حالتها السابقة ولم تعد ضمن التغييرات.
- مستندات وقيود وفواتير وأرصدة تشغيلية: لم يُجر أي تعديل عليها.
- أي ملفات تخص Phase 17: لم تُنشأ.

## 11. Remaining Risks

| Severity | Description | Evidence | Required action | Blocks Phase 17 |
|---|---|---|---|---|
| Critical | MariaDB لا تبدأ ولا يوجد Backup موثق | Aria/InnoDB recovery errors وSQLSTATE 2002 | Copy Backup متحقق منه ثم DBA recovery | نعم |
| High | Migration المصرية الجديدة غير مطبقة | `migrate:status/--pretend` blocked | تطبيقها بعد الاستعادة والـAudit | نعم |
| High | حالة SAR/VAT15 التاريخية غير معروفة | تعذر تشغيل Audit | تشغيل الأمر read-only ومراجعة الإدارة | نعم |
| High | اختبارات DB وPhase15/Full suite لم تعمل | السيرفر متوقف | تشغيل التسلسل الكامل على DB testing | نعم |
| Medium | نص تاريخ تأسيس عام يذكر السعودية | محتوى موقع تاريخي، لا Default تشغيلي | قرار Content من الإدارة | لا |
| Low | PHP 8.4 deprecations مع Laravel 9 | مخرجات Pint/Artisan | استخدام PHP 8.2 حاليًا؛ ترقية Laravel خارج النطاق | لا |
| Low | Vite runtime asset warnings | مخرجات build | مراجعة أصول الواجهة لاحقًا | لا |

## 12. Final Checklist

- [x] لا توجد تعديلات غير آمنة في Migrations تاريخية
- [ ] Migration Phase 16 الجديدة مطبقة وناجحة
- [x] `config/website.php` سليم ولا توجد Keys مفقودة
- [x] `TreasuryFoundationSeeder` لا يعتمد على السعودية
- [x] Defaults المصرية صحيحة
- [x] `MoneyFormatter` ديناميكي
- [x] `PhoneNormalizer` مصري كـDefault
- [x] لا يوجد ETA أو ZATCA تشغيلي
- [ ] MariaDB تعمل
- [x] قاعدة Testing مستقلة ومحمية بالاسم، لكن الاتصال غير متاح
- [ ] `localization:audit-egypt` نجح
- [ ] EgyptLocalization tests كلها Passed
- [ ] TreasuryManualQa Seeder اشتغل مرتين
- [ ] TreasuryManualQa tests كلها Passed
- [ ] PhaseFifteen tests كلها Passed
- [ ] الاختبارات الكاملة كلها Passed
- [ ] لا توجد Critical أو High unresolved issues
- [x] لم تتغير بيانات مالية تاريخية
- [x] Pint وComposer وBuild وView Cache وRoute List وDiff Check ناجحة

## 13. Final Decision Explanation

- MariaDB المتوقفة وحدها تمنع GO حسب البوابة.
- لا يوجد Backup موثق يسمح بمحاولة recovery آمنة.
- Migration لم تُطبق، والـAudit التاريخي غير متاح.
- اختبارات قاعدة البيانات وTreasury QA وPhase 15 والـFull suite لم تُثبت.
- لذلك القرار النهائي هو: **NO-GO — Phase 17 is blocked**.
