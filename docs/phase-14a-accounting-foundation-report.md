# Phase 14A — Accounting Foundation Report

## Scope

تم تنفيذ Foundation المحاسبة فقط، مع عزل Company/Branch عبر `TenantContext`.
لا توجد جداول أو خدمات أو Routes للقيود اليومية أو دفتر الأستاذ أو التقارير
المالية أو الترحيل الآلي.

## Schema and Migration

تم إنشاء Forward Migration:

```text
2026_07_26_030000_create_accounting_foundation_tables.php
```

الجداول الجديدة:

```text
account_types
account_groups
accounts
accounting_periods
cost_centers
accounting_settings
branch_accounting_settings
posting_profiles
posting_profile_lines
opening_balance_documents
opening_balance_lines
```

كان جدول `fiscal_years` موجودًا من Phase 4، لذلك تم توسيعه بأعمدة `code`
وبيانات الفتح وإعادة الفتح بدل إنشاء جدول مكرر. تم backfill للأكواد الحالية
بصيغة ثابتة، مع Unique داخل الشركة.

كل Foreign Keys تحفظ التاريخ بـ `restrictOnDelete`، عدا child rows الحقيقية
لـPosting Profile وOpening Balance التي تستخدم cascade. توجد Indexes على
Company وCode وParent وStatus وDate Ranges وSource Type.

Rollback يحذف بيانات وجداول Phase 14A ويزيل أعمدة Fiscal Year الجديدة فقط.
لا يحذف أو يغير بيانات المبيعات أو المشتريات أو المخزون.

## Account Types and Groups

- الأنواع النظامية العالمية: `ASSET`, `LIABILITY`, `EQUITY`, `REVENUE`,
  `EXPENSE`.
- طبيعة Asset/Expense هي Debit، وطبيعة Liability/Equity/Revenue هي Credit.
- الأنواع النظامية محمية، والأنواع المملوكة للشركة معزولة.
- مجموعات الحسابات مملوكة للشركة، مرتبطة بنوع واحد، وتدعم Parent/Level/Path.
- Parent يجب أن يكون من نفس الشركة والنوع.
- النقل يستخدم transaction و`lockForUpdate` ويمنع Circular References.
- لا يمكن تعطيل مجموعة بها أبناء أو حسابات فعالة.

## Chart of Accounts

- دليل مستقل لكل شركة، والفروع تستخدم دليل الشركة.
- `account_code` فريد داخل الشركة.
- الحساب إما Header أو Posting، ولا يمكن أن يكون الاثنين أو لا هذا ولا ذاك.
- لا يمكن إنشاء Child تحت Posting Account.
- Level وPath يملكهما Backend ويتم تحديث الأبناء بعد النقل.
- Parent من نفس Company وAccount Type.
- System Accounts محمية من تغيير Code/Type/Nature.
- الحسابات الحساسة لا تظهر لمن لا يملك `accounting.accounts.view_sensitive`.
- Control Types وDimension Requirements وCurrency Foundation موجودة.
- لا يوجد عمود Balance في جدول `accounts`.
- تعطيل حساب مستخدم في Branch Mapping مرفوض حتى توفير بديل.

الـSeeder ينشئ هيكلًا موثقًا من `100000` إلى `650000`، ويفصل Header عن
Posting Accounts، مع Control Accounts للعملاء والموردين والمخزون والضريبة
والدفعات المقدمة.

## Fiscal Years and Accounting Periods

- السنوات لا تتداخل، ولها Current واحدة لكل شركة.
- Draft فقط قابلة لتعديل التواريخ.
- حالات السنة: `draft`, `open`, `soft_closed`, `closed`, `locked`.
- فتح السنة يتطلب Periods متصلة تغطي كامل السنة.
- Soft Close وReopen Foundation يسجلان Actor/Time/Reason فقط؛ لا توجد Closing
  Entries أو Financial Reversal.
- الفترات داخل حدود السنة ولا تتداخل.
- Monthly generation تدعم بداية غير يناير وLeap Year، وتنتج 12 فترة صحيحة.
- التوليد Idempotent ولا يعيد بناء فترات موجودة.
- Adjustment Period و`locked_modules` موجودان كـFoundation فقط.

## Cost Centers

- شجرة مستقلة مملوكة للشركة، مع Parent/Level/Path وحماية من Cycles.
- Branch Dimension منفصلة عن Cost Center.
- Header لا يستخدم للترحيل مستقبلًا وPosting فقط قابل للاختيار.
- Seeder ينشئ مركز شركة System ومركزًا System لكل فرع فقط، دون Departments
  وهمية.
- System Centers محمية ولا تتكرر عند تشغيل Seeder.

## Accounting Settings and Branch Mappings

- سجل إعدادات واحد لكل شركة.
- Base Currency يجب أن تكون فعالة، وCurrent Fiscal Year من نفس الشركة.
- تغيير Base Currency يُمنع بعد وجود مستندات محاسبية Foundation.
- Auto-post flags مثبتة على `false` ولا تنفذ أي ترحيل.
- سجل Mapping واحد لكل فرع.
- الحسابات المرتبطة يجب أن تكون Active وPosting ومن نفس الشركة.
- AR/AP/Inventory/VAT/Advances تتحقق من `control_type`.
- Cash وBank يتحققان من نوع الحساب.
- Default Cost Center من نفس الشركة ومتاح للفرع.

## Posting Profiles Foundation

- Source Types وAccount Sources وAmount Sources محكومة بعقد مركزي.
- Fixed Account يجب أن يكون Active وPosting ومن نفس الشركة.
- التفعيل يتطلب Debit Line وCredit Line.
- Active Profile لا يعدل؛ إنشاء نفس Code ينتج Version جديدة.
- Profile افتراضي Active واحد لكل Source Type، والسابق يصبح Superseded.
- لا توجد Posting Service ولا Journal Entry نتيجة التفعيل.

## Opening Balances Foundation

- الحالات: `draft`, `pending_approval`, `approved`, `ready_for_posting`,
  `cancelled`.
- لا توجد حالة `posted` ولا زر Post.
- Document Number من `DocumentNumberService`.
- كل Line يستخدم Posting Account فقط، وDebit XOR Credit بقيمة موجبة.
- Currency/Exchange Rate وBranch/Cost Center/Customer/Supplier/Employee/Vehicle
  تتحقق من الشركة والصلاحية ومتطلبات الحساب.
- الإجماليات Backend-owned، والمستند غير المتزن لا يُرسل أو يصبح Ready.
- Separation of Duties تمنع منشئ المستند من اعتماده عند تفعيل السياسة.
- `ready_for_posting` غير قابل للتعديل ولا يغير Account Balance أو
  Customer/Supplier operational statements.

## Domain Events and Audit

أضيفت أحداث الحسابات، Hierarchy، السنوات، الفترات، مراكز التكلفة، الإعدادات،
Branch Mappings، Posting Profiles، وOpening Balances. كل الأحداث المهمة
تستخدم `DB::afterCommit`، واختبار rollback يثبت عدم إرسال Event وعدم بقاء
السجل عند فشل المعاملة.

كل العمليات الحساسة تسجل Audit محدودًا بالحدث والسبب أو Parent الجديد دون
Payload شخصي كامل.

## Security

- أضيفت Permissions المطلوبة وربطها بالأدوار الحالية بصورة Idempotent.
- `company_owner` و`system_admin` لهما كامل الصلاحيات.
- `accountant` له صلاحيات التشغيل مع تطبيق Separation of Duties.
- `general_manager` و`branch_manager` لهما صلاحيات العرض حسب النطاق.
- Sales/Receptionist/Technician/Warehouse/KQuality لا يحصلون على صلاحيات
  محاسبية افتراضيًا.
- أضيفت Policies لكل Account Type/Group/Account/Fiscal Year/Period/Cost
  Center/Settings/Posting Profile/Opening Balance.
- `company_id`, Status, Level, Path, Actor IDs, Approval Fields, Totals,
  Document Number و`posted_at` محمية من Requests وMass Assignment.

## Routes and UI

أضيفت 37 Route تحت `/accounting` فقط للـFoundation. الواجهات RTL وتستخدم
Seven Ways Theme وتشمل:

```text
نظرة عامة
أنواع ومجموعات الحسابات
دليل الحسابات Tree/List/Search/Filters
السنوات والفترات
مراكز التكلفة
الإعدادات وحسابات الفروع
قوالب الترحيل
الأرصدة الافتتاحية
```

لا توجد Routes للقيود أو الأستاذ العام أو ميزان المراجعة أو القوائم المالية
أو الإقفال الفعلي.

## Seeder and Factories

`AccountingFoundationSeeder` ينشئ الأنواع والمجموعات والحسابات ومراكز التكلفة
والإعدادات وBranch Mappings وتسلسلات `opening_balance` و`posting_profile`.
تشغيله مرتين لا يكرر أي سجل ولا ينشئ Opening Balance Document.

أضيفت Factories للأنواع والمجموعات والحسابات والسنوات والفترات ومراكز التكلفة
وقوالب الترحيل ومستندات وسطور الأرصدة الافتتاحية.

## Migration Verification

على قاعدة التطوير:

```text
php artisan migrate --force       PASS — Nothing to migrate after apply
php artisan db:seed --force       PASS — run twice without duplication
php artisan migrate:status        PASS — 000000/010000/020000/030000 Ran
```

على `laravel_test_project_testing` فقط:

```text
030000 rollback                  PASS
030000 reapply                   PASS
```

أول تطبيق للـMigration الجديدة توقف بسبب تجاوز اسم Foreign Key حد MariaDB.
تم تشغيل `down()` لنفس Migration لتنظيف جداول Phase 14A الجزئية غير المسجلة،
ثم تقصير أسماء القيود وإعادة التطبيق بنجاح. لم تتأثر أي جداول أو بيانات سابقة.

## Tests and Commands

```text
php artisan optimize:clear        PASS
php artisan test                  PASS — 157 tests
vendor/bin/pint --test            PASS — 825 files
composer validate                 PASS
npm.cmd run build                 PASS — Vite 4.5.14, 60 modules
php artisan route:list            PASS — 355 routes
php artisan view:cache            PASS
git diff --check                  PASS
git status --short                PASS — expected Phase 14A files only
```

## Changed Files

```text
app/Events/Account*.php
app/Events/Accounting*.php
app/Events/BranchAccountingMappingsUpdated.php
app/Events/CostCenter*.php
app/Events/FiscalYear*.php
app/Events/OpeningBalance*.php
app/Events/PostingProfile*.php
app/Http/Controllers/Account*.php
app/Http/Controllers/Accounting*.php
app/Http/Controllers/CostCenterController.php
app/Http/Controllers/FiscalYearController.php
app/Http/Controllers/OpeningBalanceController.php
app/Http/Controllers/PostingProfileController.php
app/Http/Requests/Account*.php
app/Http/Requests/Accounting*.php
app/Http/Requests/BranchAccountingSettingsRequest.php
app/Http/Requests/CostCenter*.php
app/Http/Requests/Fiscal*.php
app/Http/Requests/OpeningBalance*.php
app/Http/Requests/PostingProfile*.php
app/Models/Account*.php
app/Models/Accounting*.php
app/Models/BranchAccountingSetting.php
app/Models/CostCenter.php
app/Models/FiscalYear.php
app/Models/OpeningBalance*.php
app/Models/PostingProfile*.php
app/Policies/Account*.php
app/Policies/Accounting*.php
app/Policies/Concerns/AccountingPolicyScope.php
app/Policies/CostCenterPolicy.php
app/Policies/FiscalYearPolicy.php
app/Policies/OpeningBalancePolicy.php
app/Policies/PostingProfilePolicy.php
app/Providers/AuthServiceProvider.php
app/Services/Account*.php
app/Services/Accounting*.php
app/Services/AuditService.php
app/Services/BranchAccountingSettingsService.php
app/Services/ChartOfAccountsService.php
app/Services/CostCenter*.php
app/Services/Fiscal*.php
app/Services/OpeningBalance*.php
app/Services/PostingProfile*.php
database/factories/Account*.php
database/factories/AccountingPeriodFactory.php
database/factories/CostCenterFactory.php
database/factories/FiscalYearFactory.php
database/factories/OpeningBalance*.php
database/factories/PostingProfileFactory.php
database/migrations/2026_07_26_030000_create_accounting_foundation_tables.php
database/seeders/AccountingFoundationSeeder.php
database/seeders/DatabaseSeeder.php
resources/views/accounting/**
resources/views/partials/sidebar.blade.php
routes/web.php
tests/Feature/PhaseFourteenAccountingFoundationTest.php
docs/phase-14a-accounting-foundation-report.md
```

## Deferred to Phase 14B or Later

تم تأجيل كل ما يلي صراحة:

```text
Journal Entries / Journal Entry Lines
General Ledger / Ledger Transactions
Trial Balance
Financial Statements
Automatic Posting
Closing Entries / Year-End Closing
Bank Reconciliation / ZATCA / Budgets
Depreciation / Payroll / Revaluation
```

تحذيرات Vite الحالية لمسارات صور وخطوط Website المطلقة ما زالت Runtime-only.
Git يعرض تحذير Windows الخاص بـLF/CRLF، ولا توجد whitespace errors. تشغيل
MariaDB المحلي ما زال يعتمد على Aria runtime log directory المنفصل بسبب
تلف سابق في Aria log الافتراضي لـXAMPP.
