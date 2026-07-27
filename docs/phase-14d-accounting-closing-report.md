# Phase 14D — Accounting Closing Report

## النتيجة

تم تنفيذ دورة إقفال محاسبي محكومة، بدون Stored Account Balances وبدون تعديل القيود المرحلة. كل الكتابات الحساسة داخل Transactions مع `lockForUpdate`، وروابط القيود تمنع التكرار، والأحداث تُرسل بعد Commit.

## التصميم المنفذ

- `accounting_closing_runs`: سجل موحد لـSoft/Hard Close وLock وYear-End وReopen مع Actors وValidation Snapshot.
- Checklist ثابت ومؤتمت يُنشأ مرة لكل Run، ويُعاد حسابه من خدمات التقارير بدل قبول حالة من Browser.
- Blocking Exceptions تمنع الاعتماد. Waiver يحتاج صلاحية وسببًا وشخصًا غير Starter، ويحدّث الـChecklist وAudit.
- Soft Close: فحص → مراجعة → اعتماد، بدون Journal.
- Hard Close: يبدأ فقط من `soft_closed`، يعيد الفحص، ثم يمنع Posting بعد الاعتماد.
- Period Lock منفصل، وReopen يحفظ التاريخ والسبب.
- Module Locks: `sales`, `purchasing`, `inventory`, `payments`, `manual_journals`, `opening_balances`, `adjustments` مطبقة داخل Resolver/Journal/Adjustment services.
- Adjustments: `draft → pending_approval → approved → posted`، مع `entry_type=adjustment` و`is_adjusting=true`.
- Scheduled Reversals: عكس مطابق، Idempotency Key، Retry آمن، وأمر `accounting:process-scheduled-reversals`.
- Adjustment Period يقبل Adjustments/Manual/Closing فقط ويمنع المصادر التشغيلية.

## Year-End

- Workflow منفصل: Start → Automated Validation → Independent Review → Independent Approval → Different Executor.
- يقفل Revenue وExpense إلى Income Summary، ثم ينقل صافي الربح/الخسارة إلى Retained Earnings.
- Revenue وExpense وIncome Summary تصبح أرصدتها صفرًا بعد الإقفال.
- Opening Carry Forward ينقل Balance Sheet accounts فقط لأول يوم من السنة التالية.
- السنة التالية تُنشأ مرة واحدة بفترات شهرية، وتصبح Open/Current بعد نجاح العملية كاملة.
- Retry يعيد نفس Completed Run ولا يكرر أي Closing Journal.
- Controlled Year Reopen يحتاج Starter وApprover مختلفين، يمنع التنفيذ عند وجود نشاط مرحل عادي بالسنة التالية، يعكس Closing/Opening journals، ويحفظ القيود الأصلية.

## Validation

الفحوصات الفعلية تشمل:

- Trial Balance balance.
- Draft/Pending/Approved journals.
- Unposted eligible sources.
- Missing required cost centers.
- Failed posting links.
- All regular periods closed.
- AR/AP/Inventory/VAT reconciliation حسب Tolerance.
- Retained Earnings وIncome Summary configuration.
- Next-year readiness.

Cash tolerance موجود في الإعدادات، لكن Bank/Cash Reconciliation engine غير منفذ لأنه خارج نطاق Phase 14D.

## الأمان والصلاحيات

- Company scope من `TenantContext`؛ لا يُقبل `company_id` أو Actors أو Status أو Totals من Browser.
- Policies مضافة للRuns، Checklists، Exceptions، Adjustments، Scheduled Reversals، Settings وYear-End.
- فصل مهام Period Closing وAdjustment approval وYear-End execution وException waiver.
- Routes تحت `/accounting/closing` داخل مجموعة المصادقة الحالية، مع Permission checks في Controllers.

## Migration وSeeder

- Migration: `2026_07_26_060000_create_accounting_closing_tables.php`.
- الـMigrations `030000`, `040000`, `050000`, `060000` كلها `Ran`.
- Rollback/Reapply تم على Test DB فقط.
- `AccountingClosingSeeder` Idempotent: Permissions، Role mappings، Settings، Income Summary، Retained Earnings، Sequence.
- الـSeeder لا ينشئ Runs أو Journals أو Adjustments ولا يغير حالة Period/Year.
- 7 Factories مضافة لكل جداول Phase 14D الرئيسية.

## الاختبارات

- `PhaseFourteenAccountingClosingTest`: 7 passed.
- يغطي Schema، Soft/Hard Close، SOD، Module Lock، independent waiver، Adjustment posting، exact scheduled reversal، Year-End closing، carry-forward، retry، وcontrolled reopen.
- Full Suite النهائي: `180 passed` في `217.96s`.

## نتائج الأوامر

- `php artisan migrate --force`: نجح؛ Migration 060 طُبقت.
- `php artisan migrate:status`: 030/040/050/060 = Ran.
- `php artisan db:seed --force`: نجح.
- `php artisan optimize:clear`: نجح.
- `php artisan test`: 180 passed.
- `vendor/bin/pint --test`: PASS، 1011 files.
- `composer validate`: `composer.json is valid`.
- `npm.cmd run build`: نجح، 60 modules transformed.
- `php artisan route:list`: نجح، 404 routes.
- `php artisan view:cache`: نجح.
- `php artisan accounting:process-scheduled-reversals`: نجح، 0 records.
- `git diff --check`: Exit 0.

## الملفات المعدلة

- Core integration: `app/Providers/AuthServiceProvider.php`, `app/Services/AccountingPeriodResolver.php`, `app/Services/JournalEntryService.php`, `database/seeders/DatabaseSeeder.php`, `resources/views/partials/sidebar.blade.php`, `routes/web.php`.
- Migration/Seeder: `database/migrations/2026_07_26_060000_create_accounting_closing_tables.php`, `database/seeders/AccountingClosingSeeder.php`.
- Models: `AccountingClosingRun`, `AccountingClosingChecklist`, `AccountingClosingChecklistItem`, `AccountingClosingException`, `AccountingAdjustment`, `ScheduledJournalReversal`, `YearEndClosingSetting`.
- Services: `AccountingClosingValidationService`, `AccountingClosingChecklistService`, `AccountingPeriodClosingService`, `AccountingModuleLockService`, `AccountingClosingExceptionService`, `AccountingAdjustmentService`, `ScheduledJournalReversalService`, `YearEndClosingService`, `RevenueExpenseClosingService`, `NetProfitTransferService`, `OpeningCarryForwardService`, `NextFiscalYearService`, `ClosingJournalService`, `AccountingReopenService`.
- Controllers: ملفات Phase 14D تحت `app/Http/Controllers/*Closing*`, `*Adjustment*`, `ScheduledJournalReversalController.php`, `AccountingModuleLockController.php`, `YearEndClosingController.php`.
- Requests: ملفات Phase 14D تحت `app/Http/Requests/Accounting*` و`ScheduledJournalReversalRequest.php` و`YearEndClosingSettingsRequest.php`.
- Policies: ملفات Phase 14D تحت `app/Policies/*Closing*`, `AccountingAdjustmentPolicy.php`, `ScheduledJournalReversalPolicy.php`, `YearEndClosingPolicy.php`.
- Events: Events الخاصة بالإقفال والتسويات والعكس وYear-End تحت `app/Events`.
- Command: `app/Console/Commands/ProcessScheduledAccountingReversals.php`.
- Factories: 7 Factories الخاصة بنماذج Phase 14D تحت `database/factories`.
- UI: `resources/views/accounting/closing/index.blade.php`, `adjustments.blade.php`, `year-end.blade.php`, `reports.blade.php`.
- Tests: `tests/Feature/PhaseFourteenAccountingClosingTest.php`.
- Report: `docs/phase-14d-accounting-closing-report.md`.

## Warnings والنطاق المؤجل

- Vite نجح مع Warnings قديمة لمسارات Fonts/Images ثابتة تُحل Runtime.
- `git diff --check` أظهر فقط تحذيرات LF→CRLF لبعض الملفات، بدون Whitespace errors.
- لم يتم تنفيذ Bank Reconciliation، ZATCA، Budgets، Fixed Assets، Payroll Accounting، FX Revaluation أو Consolidation.
