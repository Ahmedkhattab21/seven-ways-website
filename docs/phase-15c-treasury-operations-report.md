# Phase 15C — Treasury Operations

## النطاق المنفذ

تم تنفيذ عمليات الخزينة اليومية فقط، مع الاعتماد على القيود المحاسبية كمصدر الحقيقة وعدم إضافة أي رصيد مخزن للصناديق أو الحسابات البنكية.

### Treasury Transfers

- أضيفت حالات `processing`, `completed`, `failed`, و`reversed` وحقول المعالجة والفشل والعكس و`idempotency_key`.
- التنفيذ متاح للتحويل المعتمد فقط، داخل `DB::transaction` وبعد قفل التحويل والمصدر والوجهة.
- تدعم خدمة الترحيل الاتجاهات الأربعة: بنك/بنك، بنك/صندوق، صندوق/بنك، وصندوق/صندوق.
- `cash_deposit` و`cash_withdrawal` يستخدمان نفس محرك التحويل، ولا يوجد مستند مكرر.
- الرسوم منفصلة عن أصل التحويل، والقيد متوازن ومرتبط بالمستند.
- إعادة المحاولة لا تنشئ قيدًا ثانيًا، والفشل لا يترك أثرًا محاسبيًا جزئيًا.
- العكس قيد مقابل دقيق، مع احترام الفترة ووحدة `treasury`، وبعد الإكمال يصبح المستند التشغيلي غير قابل للتعديل.
- التحويل بين عملتين مختلفتين أو `exchange_rate != 1` مرفوض صراحة.

### Cash Sessions, Counts, and Over/Short

- أضيفت جلسات الصندوق وحالاتها، مع قيد قاعدة بيانات يمنع وجود أكثر من جلسة نشطة للصندوق نفسه.
- الرصيد الدفتري الافتتاحي والختامي يُحسب من دفتر الأستاذ، وليس من قيمة يرسلها المتصفح.
- إجمالي العد وفروق العد و`line_total` تُحسب في الخادم.
- إغلاق الجلسة يتطلب عدًا ختاميًا معتمدًا، ومعالجة فرق الصندوق عند تطبيق سياسة المنع.
- فرق الزيادة أو العجز يمر بمسار إنشاء واعتماد وترحيل، ويُرحل مرة واحدة بالقيمة الدقيقة.
- الجلسة المغلقة والعد المعتمد غير قابلين للتعديل.
- تم تطبيق فحص أمين الصندوق، الصلاحيات، حدود الاعتماد، الفصل بين المهام، الفرع، والفترة.

### Cash Receipts and Payments

- أضيفت المقبوضات والمدفوعات النقدية العامة بمسار `draft` إلى الاعتماد والترحيل والعكس.
- المقبوض: مدين للصندوق ودائن للحساب المقابل. المدفوع: مدين للحساب المقابل ودائن للصندوق.
- يتم فرض الجلسة وأمين الصندوق عندما يكون `requires_shift_opening` مفعّلًا.
- الحساب المقابل من نفس الشركة وقابل للترحيل، والحسابات الرقابية تحتاج الصلاحية المناسبة.
- لم تُستخدم هذه المستندات بدل `CustomerPayment` أو `SupplierPayment`.
- الترحيل والعكس idempotent وروابط القيود دائمة.

### Cheques

- أضيفت الشيكات الواردة والصادرة، سجل الحالات append-only، وأساس التظهير.
- كل انتقال حالة يتم من خلال `ChequeService` مع تحقق الشركة والفرع والصلاحية وحساب البنك والفترة.
- الشيك الوارد يُرحل أولًا إلى شيكات تحت التحصيل، ثم إلى البنك عند التحصيل.
- الشيك الصادر يُرحل إلى شيكات مستحقة الدفع، ثم إلى البنك عند الخصم.
- الارتداد ينشئ قيدًا مستقلًا مقابلًا ويحافظ على القيود الأصلية.
- التحصيل أو الخصم المكرر ممنوع، ورقم الشيك محمي من التكرار ضمن نطاقه.
- البيانات الحساسة masked بدون `treasury.cheques.view_sensitive`.
- التظهير محدود لشيك وارد موجود بالحيازة، ويُسجل في التاريخ بدون تنفيذ سلسلة قانونية أو قيد غير محسوم.

### Merchant Settlements

- أضيفت التسويات وبنودها، مع دعم التخصيص الجزئي.
- `gross_amount`, `fees_amount`, `tax_amount`, `net_amount`, والإجماليات المخصصة تُحسب وتتحقق في الخادم.
- لا يمكن تخصيص أكثر من المتبقي، ولا يقبل المصدر إلا `CustomerPayment` مرحلًا رسميًا إلى حساب `Merchant Clearing` المرتبط بطريقة الدفع.
- ترحيل دفعات Card/Online يستخدم `Merchant Clearing` بدل البنك المباشر عند وجود الـmapping.
- قيد التسوية: البنك بالصافي، المصروف بالرسوم، ضريبة المدخلات عند وجودها، مقابل إجمالي حساب التحصيل.
- الترحيل والعكس idempotent ولا يكرران `CustomerPayment`.

### Approval, Locks, Security, and Audit

- أضيف `TreasuryApprovalLimitService` و`TreasuryOperationAuthorizationService`.
- أولوية قاعدة المستخدم على الدور، والفرع على الشركة، مع منع القواعد المتداخلة المبهمة.
- الحدود والصلاحيات والوصول للبنك أو الصندوق وأمين الصندوق وSOD تُفرض في الـbackend.
- أضيفت وحدة `treasury` إلى أقفال الوحدات، وتمنع الأقفال والفترات المغلقة الترحيل والعكس.
- فحص الإقفال يرى عمليات الخزينة غير المرحلة.
- الـRequests لا تقبل الشركة أو الحالة أو الممثلين أو التوقيتات أو القيود أو الأرصدة أو الإجماليات المحسوبة.
- الأحداث المطلوبة تُطلق بعد نجاح المعاملة باستخدام `DB::afterCommit`.
- سجل الأحداث لا يعتمد على أرقام حسابات أو شيكات كاملة.

### UI, Routes, and Reports

- أضيفت صفحات التحويلات، جلسات وجرد الصناديق، المقبوضات والمدفوعات، فروق الصناديق، الشيكات، تسويات نقاط البيع، وحدود الاعتماد.
- تعرض صفحات الشيكات timeline للحالات وتخفي البيانات الحساسة حسب الصلاحية.
- تعرض التسويات الإجمالي والرسوم والضريبة والصافي والتخصيص والمتبقي والقيد.
- أضيف أساس تقارير الجلسات المفتوحة، سجل العد والفروق، التحويلات، الشيكات وأعمارها والمرتجعة، التسويات، والعمليات المعلقة.
- `php artisan route:list` نجح وأظهر `482` route.

## Database, Seeder, and Factories

- أضيفت migration: `2026_07_26_090000_create_treasury_operations_tables.php`.
- أنشأت الجداول الاثني عشر المطلوبة وعدلت `treasury_transfers` بطريقة forward-only، بدون `drop` أو `rename` لبيانات سابقة.
- المفاتيح الخارجية restrictive، والفهارس قصيرة ومتوافقة مع MariaDB، وسجل حالات الشيك append-only.
- تم اختبار rollback ثم reapply للـmigration `090` فقط على قاعدة الاختبار، ونجح.
- `TreasuryOperationsSeeder` idempotent ويضيف الصلاحيات وربط الأدوار والتسلسلات وحدودًا افتراضية آمنة والحسابات والـmappings الناقصة.
- الـSeeder لا ينشئ تحويلات أو جلسات أو جردًا أو شيكات أو تسويات أو قيودًا.
- أضيفت factories للجلسات والعد وفروق الصندوق والمقبوضات والمدفوعات والشيكات والتظهير والتسويات وحدود الاعتماد.

## الاختبارات

أضيفت `11` اختبارات Phase 15C موزعة على أربعة ملفات، وتغطي:

- اتجاهات التحويل الأربعة، الرسوم، التوازن، retry، failure rollback، العكس، lock، وعدم الترحيل المكرر.
- جلسة نشطة واحدة، حساب الفئات في الخادم، منع الإغلاق بفارق غير معالج، over/short، وعدم قابلية السجل المغلق للتعديل.
- ترحيل وعكس المقبوضات والمدفوعات.
- دورة الشيك الوارد والصادر، التاريخ، الإخفاء، التكرار، التحصيل مرة واحدة، الارتداد، والاستبدال.
- إجماليات التسوية في الخادم، التخصيص الجزئي والزائد، مصدر Merchant Clearing، الرسوم، وعدم الترحيل المكرر.
- أولوية حدود المستخدم والفرع، منع التداخل، mass assignment، وعدم وجود stored balances.
- idempotency للـSeeder وعدم إنشائه معاملات تشغيلية أو قيودًا.

النتائج:

- `php artisan test`: **220 passed**.
- `php artisan test --filter=PhaseFifteen`: **31 passed**.
- `vendor/bin/pint --test`: **PASS — 1273 files**.
- `composer validate`: **valid**.
- `npm.cmd run build`: **نجح**، Vite 4.5.14، 60 modules.
- `php artisan view:cache`: **نجح**.
- `php artisan route:list`: **نجح — 482 routes**.
- `git diff --check`: **نجح**؛ توجد تحذيرات تحويل LF إلى CRLF فقط.
- `php artisan migrate --force`: **نجح** وmigration `090` حالتها `Ran`.
- `php artisan db:seed --force`: **نجح**.
- `php artisan optimize:clear`: **نجح**.

## الملفات المعدلة في Phase 15C

- `database/migrations/2026_07_26_090000_create_treasury_operations_tables.php`
- `database/seeders/TreasuryOperationsSeeder.php`
- `database/seeders/DatabaseSeeder.php`
- Models: `CashBoxSession`, `CashBoxCount`, `CashBoxCountLine`, `CashOverShortAdjustment`, `CashReceipt`, `CashPayment`, `Cheque`, `ChequeStatusHistory`, `ChequeEndorsement`, `MerchantSettlement`, `MerchantSettlementLine`, `TreasuryApprovalLimit`, `TreasuryTransfer`, و`PaymentMethodAccountMapping`.
- Services: خدمات التحويل والترحيل، الجلسات والعد والفروق، المقبوضات والمدفوعات، الشيكات، التسويات، الاعتماد، التفويض، القيود، resolver، وإقفال المحاسبة.
- Controllers: `CashBoxSessionController`, `CashOperationController`, `ChequeController`, `MerchantSettlementController`, `TreasuryApprovalLimitController`, `TreasuryReportController`, و`TreasuryTransferController`.
- Requests وPolicies وEvents الخاصة بعمليات Phase 15C.
- `app/Providers/AuthServiceProvider.php`
- `resources/views/treasury/`
- `resources/views/partials/sidebar.blade.php`
- `routes/web.php`
- Factories الخاصة بـPhase 15C.
- `tests/Concerns/BuildsTreasuryOperationsContext.php`
- `tests/Feature/PhaseFifteenTreasuryTransferProcessingTest.php`
- `tests/Feature/PhaseFifteenCashSessionOperationsTest.php`
- `tests/Feature/PhaseFifteenChequeMerchantSettlementTest.php`
- `tests/Feature/PhaseFifteenTreasuryLimitsSecurityTest.php`

## التحذيرات والحدود

- Vite ما زال يعرض تحذيرات قديمة لمسارات fonts/images تحت `/assets/website/...` تُحل وقت التشغيل؛ الـbuild نجح ولم يغير Phase 15C إعدادات الواجهة.
- Git يعرض تحذيرات line endings من `LF` إلى `CRLF` لبعض الملفات القائمة؛ `git diff --check` ناجح.
- توجد تغييرات غير ملتزمة من مراحل 14D و15A و15B في working tree وتم الحفاظ عليها.
- لم يتم تنفيذ Open Banking أو Credit Card Acquirer API أو ZATCA أو Cross-currency Treasury أو Stored Cash/Bank Balances.
