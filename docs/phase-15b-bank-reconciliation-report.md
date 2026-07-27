# Phase 15B — Bank Statement Import and Reconciliation Report

## Scope and Accounting Principle

تم تنفيذ دورة المطابقة البنكية المحكومة:

`Upload → Validate → Parse → Detect Duplicates → Reconcile → Review → Approve → Complete`

كشف البنك يظل دليلًا خارجيًا، والأستاذ العام يظل الدليل المحاسبي. المطابقة تربطهما ولا تستبدل أيًا منهما، ولا يوجد Stored Bank Balance.

## File Security

- CSV فقط هو الـformat المنفذ فعليًا في Phase 15B.
- الملفات مخزنة على `local` disk داخل `storage/app/private/bank-statements`.
- اسم التخزين UUID آمن ولا يعتمد على اسم الملف الأصلي.
- اسم التنزيل يمر عبر `basename`.
- التنزيل يحتاج View وSensitive permission وسياسة الحساب البنكي.
- الحد الأقصى 10 MB، والحد الأقصى 100,000 سطر.
- يتم فحص Extension وMIME ومقدمة المحتوى وUTF-8 ورفض binary/HTML/XML والتنفيذي.
- Parsing بـ`SplFileObject` وبصورة streaming، بدون تحميل الملف كاملًا.
- لا OCR ولا External AI ولا تنفيذ لأي محتوى داخل الملف.
- CSV Export يضيف UTF-8 BOM ويحمي من Formula Injection.

## Import Profiles and CSV Parser

- Company default أو Bank-specific profile، والأخير يتقدم في النطاق.
- Default واحد لكل Company/Bank/Format عبر `default_scope_key`.
- يدعم Header mapping محكوم للحقول المطلوبة.
- يدعم:
  - Debit/Credit منفصلين.
  - Amount + Direction.
- يدعم Date Format وDecimal Separator وThousands Separator.
- الحسابات كلها Decimal باستخدام BCMath، بدون Float.
- Missing Headers وInvalid Date/Decimal وDebit/Credit conflict وNegative/Formula values تحفظ خطأ السطر وتفشل الاستيراد.
- `xlsx`, `mt940`, `camt053` غير مفعلة ولا يدعي النظام دعمها.

## Duplicate Detection

- Duplicate file: SHA-256 فريد داخل Company + Bank Account.
- تغيير اسم نفس الملف لا يسمح بإعادة استيراده.
- Duplicate line:
  - External ID له الأولوية.
  - وإلا Canonical raw hash يشمل الحساب، التواريخ، المرجع، المبلغ، الاتجاه والوصف المنظم.
- نفس التاريخ والمبلغ مع مرجع مختلف لا يصنف Duplicate تلقائيًا.
- السطر المكرر محفوظ ولا يحذف، ولا يدخل المطابقة.
- إعادة التصنيف تحتاج Permission وReason وAudit، مع تحديث Duplicate Summary.

## Statement Balance Validation

- السياسة الافتراضية: `Opening + Credits - Debits = Closing`.
- يمكن للـprofile اختيار `debit_increases`.
- Running Balance يتحقق لكل صف عند وجوده.
- الفرق خارج Tolerance يفشل الاستيراد ويرجع كل Statement Lines داخل Transaction.
- لا ينشأ Journal لتصحيح الفرق، ولا يتم إخفاؤه.

## Reconciliation Session Workflow

- الحالات: `draft`, `matching`, `ready_for_review`, `under_review`, `approved`, `completed`, `reopened`, `cancelled`.
- Session لحساب واحد وفترة داخل Fiscal Year/Accounting Period.
- يمنع تداخل Completed Sessions.
- Import يجب أن يكون imported، لنفس الحساب والعملة وداخل الفترة.
- Import المستخدم في Completed Session لا يعاد استخدامه.
- Totals Backend-owned وتُعاد من المصدر عند Review وComplete.
- Completed Session immutable، مع retry آمن لعملية Complete.

## Book Transaction Source

- المصدر الوحيد للـbook side هو `journal_entry_lines`.
- Journal Entry يجب أن يكون `posted`.
- Account هو GL Account المرتبط بالحساب البنكي.
- Company وDate Range محكومان.
- Original reversal وقيد العكس يظهران كسطرين ولا يخفي النظام أحدهما.
- Opening/Closing/Running Book Balance محسوبة وليست مخزنة.

## Manual, Partial and Multiple Matching

- One-to-One وOne-to-Many وMany-to-One وMany-to-Many.
- Partial statement وPartial book allocations.
- كل Match داخل Transaction مع `lockForUpdate`.
- يمنع Over-allocation وCross-bank وDirection mismatch.
- مجموع الجانبين يجب أن يكون داخل Tolerance.
- Suggested match لا يغير matched totals قبل القبول.
- Unmatch موثق قبل Completion فقط؛ بعد Completion يحتاج Reopen أولًا.

## Suggested Matching and Rules

- Score من 0 إلى 100 ومبرراته قابلة للعرض.
- الأوزان تشمل External ID، Reference، Exact Amount، Date proximity وIBAN last4.
- Candidate window محدود بالحساب والفترة والاتجاه، والنتائج محدودة.
- لا AI ولا ML ولا Fuzzy scan غير محدود.
- Bank-specific rules تسبق Company rules ثم Priority.
- الشروط محكومة ولا تقبل Raw Regex أو PHP أو SQL.
- Controlled Auto-match يحتاج:
  - Permission مستقلة.
  - طلبًا صريحًا.
  - Rule مفعلة بـ`auto_match`.
  - Confidence أعلى من الحد.
- قواعد المطابقة لا تنشئ Journal.

## Bank Adjustments and Posting

- الأنواع: Bank Fee، Interest Income، Interest Expense، Unidentified Receipt، Unidentified Payment، Rounding وOther.
- الدورة: `draft → pending_approval → approved → posted → reversed`.
- Amount موجب، Bank Account فعال، Offset Account فعال وPosting ومن نفس الشركة.
- Period وSoft Close وModule lock `treasury` تطبق عبر Accounting Period Resolver.
- Posting يمر عبر Accounting Posting/Journal Engine الحالي.
- `source_type = BankAdjustment`، مع Accounting Posting Link دائم وIdempotent.
- Bank Fee/Interest/Unidentified postings تطبق القيود المحددة.
- Rounding/Other يحتاج Statement Line direction.
- لا تعديل بعد Posting، والعكس قيد مقابل exact ويحفظ الأصل.
- لا Automatic Difference Journal.

## Completion and Reopen

- Completion يعيد الحساب ويقفل Session/Bank Account/Matches.
- يتحقق من:
  - imports/currency/status.
  - duplicate classification.
  - matched/ignored/duplicate lines.
  - عدم over-allocation.
  - adjustments posted/cancelled.
  - statement continuity.
  - difference داخل tolerance.
  - period/module status.
  - Review/Approval وSOD.
- يحدث `bank_accounts.last_reconciled_date` فقط عند Completion.
- Reopen يحتاج Completed + Permission + Reason + actor مختلف عن Completer.
- يمنع Reopen مع جلسة Completed لاحقة.
- Matches تبقى محفوظة، وAdjustments لا تعكس تلقائيًا.
- `last_reconciled_date` يعاد حسابه من آخر Session مكتملة.

## Permissions, Policies, SOD, Audit and Events

- أضيفت كل permissions المطلوبة للاستيراد، المطابقة، القواعد، التسويات والتصدير.
- Company Owner وFinance Manager كاملان.
- Accountant يستورد ويطابق وينشئ/يرسل التسويات بدون اعتماد ذاتي.
- General Manager للمراجعة والاعتماد.
- Branch Manager/Cashier عرض محدود بالحسابات المصرح بها.
- Policies تطبق Company/Branch/Bank scope وStatus وSensitive masking.
- Importer لا يعتمد جلسته، Starter لا يراجع نفسه، Reviewer لا يعتمد نفسه.
- Adjustment Creator لا يعتمد، والApprover لا يرحل.
- الأحداث المسماة تصدر عبر `DB::afterCommit`.
- Audit يسجل lifecycle والسبب والملخص فقط، ولا يسجل محتوى الكشف كاملًا.

## Routes, UI, Reports and Export

- أضيفت شاشات RTL داخل Seven Ways Theme:
  - كشوف الحساب والاستيراد.
  - Statement Lines.
  - Import Profiles.
  - جلسات المطابقة Side-by-side.
  - Matching Rules.
  - Bank Adjustments.
  - Reconciliation Reports.
- التقارير:
  - Unmatched Statement Lines.
  - Unmatched Book Transactions.
  - Duplicate Lines.
  - Adjustment Register.
  - Session Summary/Detail.
- CSV Export محمي ومقيد بصلاحية مستقلة.
- إجمالي routes: 453؛ Treasury routes: 49.

## Migration Verification

- أضيفت:
  - `2026_07_26_080000_create_bank_reconciliation_tables.php`
- Before: لا توجد جداول Import/Reconciliation.
- After:
  - `bank_statement_import_profiles`
  - `bank_statement_imports`
  - `bank_statement_lines`
  - `bank_reconciliation_sessions`
  - `bank_reconciliation_session_imports`
  - `bank_reconciliation_matches`
  - `bank_reconciliation_match_items`
  - `bank_matching_rules`
  - `bank_adjustments`
- Migration additive/forward-only؛ لا Drop أو Rename لأي جدول سابق.
- Foreign keys restrictive، وأسماء القيود قصيرة ومتوافقة مع MariaDB.
- أول محاولة اكتشفت اسم FK أطول من حد MariaDB؛ حذفت الجداول الجديدة الجزئية الفارغة فقط، ثم قصرت الأسماء.
- rollback/reapply تم بنجاح على قاعدة PHPUnit `laravel_test_project_testing` فقط.
- Migration 080 مطبقة على قاعدة التطوير، وكل migrations 030–080 Ran.

## Seeder and Factories

- `BankReconciliationSeeder` idempotent.
- يضيف Permissions وRole mappings وDefault CSV profile وTolerance/Direction defaults وDocument Sequences.
- لا ينشئ Imports أو Sessions أو Matches أو Adjustments أو Journals أو balances.
- Factories المضافة:
  - BankStatementImportProfileFactory
  - BankStatementImportFactory
  - BankStatementLineFactory
  - BankReconciliationSessionFactory
  - BankReconciliationMatchFactory
  - BankReconciliationMatchItemFactory
  - BankMatchingRuleFactory
  - BankAdjustmentFactory

## Tests and Verification

| Check | Result |
|---|---|
| Phase 15B targeted | 22 passed |
| Phase 14 Journal Engine | 8 passed |
| Phase 14 Closing | 7 passed |
| Phase 15A Treasury | 7 passed |
| Full Suite | 209 passed |
| `vendor/bin/pint --test` | Passed — 1166 files |
| `composer validate` | Passed |
| `npm.cmd run build` | Passed — 60 modules |
| `php artisan optimize:clear` | Passed |
| `php artisan route:list` | Passed — 453 routes |
| `php artisan view:cache` | Passed |
| `php artisan migrate --force` | Passed |
| Test DB rollback/reapply | Passed |
| `php artisan db:seed --force` twice | Passed |
| `git diff --check` | Passed |

بعد Full Suite تمت إضافة تحقق SOD مبكر يمنع Importer من Approval؛ أُعيد تشغيل Workflow targeted test ونجح.

## Changed Files

### Modified

- `app/Http/Requests/AccountingFormRequest.php`
- `app/Models/BankAccount.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Services/AccountingPostingService.php`
- `app/Services/BankAccountService.php`
- `app/Services/JournalEntryService.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/partials/sidebar.blade.php`
- `routes/web.php`

### Added

- `app/Contracts/BankStatementParserContract.php`
- `app/Events/BankAdjustment*.php`
- `app/Events/BankMatchingRule*.php`
- `app/Events/BankReconciliation*.php`
- `app/Events/BankStatement*.php`
- `app/Http/Controllers/BankAdjustment*.php`
- `app/Http/Controllers/BankMatchingRuleController.php`
- `app/Http/Controllers/BankReconciliation*.php`
- `app/Http/Controllers/BankStatement*.php`
- `app/Http/Requests/BankAdjustment*.php`
- `app/Http/Requests/BankMatchingRuleRequest.php`
- `app/Http/Requests/BankReconciliation*.php`
- `app/Http/Requests/BankStatement*.php`
- `app/Models/BankAdjustment.php`
- `app/Models/BankMatchingRule.php`
- `app/Models/BankReconciliation*.php`
- `app/Models/BankStatement*.php`
- `app/Policies/BankAdjustmentPolicy.php`
- `app/Policies/BankMatchingRulePolicy.php`
- `app/Policies/BankReconciliation*.php`
- `app/Policies/BankStatement*.php`
- `app/Policies/Concerns/TreasuryBankScope.php`
- `app/Services/BankAdjustment*.php`
- `app/Services/BankBookTransactionService.php`
- `app/Services/BankMatching*.php`
- `app/Services/BankReconciliation*.php`
- `app/Services/BankStatement*.php`
- `database/factories/Bank*.php` الخاصة بـPhase 15B
- `database/migrations/2026_07_26_080000_create_bank_reconciliation_tables.php`
- `database/seeders/BankReconciliationSeeder.php`
- `resources/views/treasury/bank-*.blade.php`
- `resources/views/treasury/matching-rules.blade.php`
- `resources/views/treasury/reconciliation*.blade.php`
- `tests/Feature/PhaseFifteenBankReconciliation*.php`
- `tests/Unit/BankStatementFileServiceTest.php`
- `tests/Unit/CsvBankStatementParserTest.php`

## Remaining Warnings and Explicit Exclusions

- Vite build ناجح مع تحذيرات runtime-resolution قديمة لنفس fonts/images الخاصة بالموقع.
- Git يعرض تحذيرات LF/CRLF المعتادة فقط.
- لم يتم تنفيذ Open Banking API أو Direct Bank Login أو PSD2 أو Bank API Sync.
- لم يتم تنفيذ Cheque Lifecycle أو Cash Box Sessions.
- لم يتم تنفيذ Treasury Transfer Posting.
- لم يتم تنفيذ Automatic Difference Journal.
- لم يتم تنفيذ Stored Bank Balance.
