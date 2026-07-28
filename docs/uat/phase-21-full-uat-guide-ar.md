# دليل Phase 21 للاختبار اليدوي الشامل

## 1. الهدف والحدود

الهدف هو اختبار النظام كدورات أعمال مترابطة على قاعدة UAT المعزولة فقط:

```text
Host: 127.0.0.1
Port: 3307
Database: seven_ways_uat
```

الاختبارات الآلية تثبت قواعد قابلة للتكرار، بينما الاختبار اليدوي يثبت تجربة المستخدم الفعلية وحالة الصفحة والمستند والتقارير. لا تُعتبر أي خطوة يدوية ناجحة بدون Actual Result وHTTP status وScreenshot.

ممنوع استخدام port 3306، أو قواعد `seven_ways_clean_local` و`seven_ways_testing`، أو بيانات حقيقية، أو `migrate:fresh`، أو `db:wipe`، أو تعديل ملفات MariaDB، أو نشر Production.

## 2. حسابات الاختبار

كلمة المرور المحلية الموحدة: `Uat@123456`

| الدور | البريد | الفروع | الوظائف الأساسية |
| --- | --- | --- | --- |
| مالك الشركة | `uat.owner@sevenways.test` | الكل | الإدارة والتوقيع النهائي |
| المدير العام | `uat.general.manager@sevenways.test` | الكل | الإدارة والمراجعة |
| مدير القاهرة | `uat.cairo.manager@sevenways.test` | القاهرة | اعتماد الفرع |
| مدير الجيزة | `uat.giza.manager@sevenways.test` | الجيزة | اعتماد الفرع |
| المحاسب | `uat.accountant@sevenways.test` | الكل | إنشاء/مراجعة/ترحيل بدون اعتماد عام مخالف لـSOD |
| مدير الخزينة | `uat.treasury.manager@sevenways.test` | القاهرة والجيزة | اعتماد عمليات الخزينة |
| أمين صندوق القاهرة | `uat.cairo.cashier@sevenways.test` | القاهرة | جلسة/قبض/صرف بدون اعتماد |
| المبيعات | `uat.sales@sevenways.test` | القاهرة | العملاء والعروض والمبيعات |
| أمين المخزن | `uat.warehouse@sevenways.test` | القاهرة | المخزون والاستلام |
| الفني | `uat.technician@sevenways.test` | القاهرة | تنفيذ أمر العمل |
| الجودة | `uat.quality@sevenways.test` | القاهرة | الفحص وإعادة العمل |
| الاستقبال | `uat.reception@sevenways.test` | القاهرة | العميل والمركبة والموعد |
| العرض فقط | `uat.viewer@sevenways.test` | القاهرة | عرض بدون كتابة |
| مستخدم معطل | `uat.disabled@sevenways.test` | القاهرة | يجب رفض الدخول |

## 3. البيانات المرجعية

- الشركة: `Seven Ways UAT Egypt`، مصر، EGP، `Africa/Cairo`.
- الفروع: `UAT-CAI`, `UAT-GIZ`, `UAT-ALX`.
- المخازن: `UAT-CAI-MAIN`, `UAT-CAI-INSTALL`, `UAT-GIZ-MAIN`, `UAT-ALX-MAIN`.
- الصناديق: `UAT-CAI-CASH`, `UAT-GIZ-CASH`, `UAT-ALX-CASH`.
- البنك: `UAT Egypt Test Bank`.
- الحسابات: `UAT-BANK-CAI`, `UAT-BANK-GIZ` بأرقام مقنعة غير حقيقية وبدون IBAN.
- الضرائب: `VAT14-EG`, `VAT0-EG`, `EXEMPT-EG`. الضريبة مرتبطة بالبيانات وليست hardcoded.
- المنتجات: خمسة أكواد تبدأ بـ`UAT-`.
- الخدمات: خمسة أكواد تبدأ بـ`UAT-SVC-`.
- العملاء: ستة عملاء تجريبيين.
- المركبات: خمس مركبات بأرقام `UAT` وهمية.
- الموردون: خمسة موردين تجريبيين.
- الموظفون: عشرة موظفين تجريبيين.
- الرصيد الافتتاحي: صفر. ابدأ المخزون من الاستلام الرسمي؛ لا تعدّل جدول balance.

## 4. أوامر التجهيز

لا تنفذ الأوامر قبل نجاح بوابة الأمان:

```powershell
D:\xxamp\php\php.exe artisan uat:validate-target --env=uat.local
D:\xxamp\php\php.exe artisan optimize:clear --env=uat.local
D:\xxamp\php\php.exe artisan migrate:status --env=uat.local
D:\xxamp\php\php.exe artisan migrate --pretend --env=uat.local
D:\xxamp\php\php.exe artisan migrate --force --env=uat.local
D:\xxamp\php\php.exe artisan db:seed --class=ProductionReferenceSeeder --env=uat.local
D:\xxamp\php\php.exe artisan db:seed --class=SevenWaysUatSeeder --env=uat.local
D:\xxamp\php\php.exe artisan db:seed --class=SevenWaysUatSeeder --env=uat.local
D:\xxamp\php\php.exe artisan key:generate --env=uat.local
D:\xxamp\php\php.exe artisan serve --host=127.0.0.1 --port=8085 --env=uat.local
```

`UatPerformanceSeeder` منفصل ولا يُشغّل إلا بعد حفظ Backup لنتائج UAT الأساسية:

```powershell
D:\xxamp\php\php.exe artisan db:seed --class=UatPerformanceSeeder --env=uat.local
```

## 5. نموذج توثيق كل خطوة

```text
Test ID:
Module:
Account:
Role:
Branch:
URL:
Preconditions:
Action:
Input:
Expected document status:
Expected business result:
Expected journal entry:
Expected notification:
Expected audit event:
Expected report effect:
Actual result:
HTTP status:
Passed / Failed:
Screenshot:
Notes:
```

## 6. الدورات اليدوية

### UAT-AUTH-001 — الدخول والعزل

1. سجّل دخول المالك وتأكد من ظهور الفروع الثلاثة.
2. سجّل دخول مدير القاهرة وتأكد من ظهور القاهرة فقط.
3. غيّر `branch_id` مباشرة إلى الجيزة وتوقع 403/404.
4. سجّل دخول Viewer وحاول POST/PUT/DELETE وتوقع 403.
5. سجّل دخول المستخدم المعطل وتوقع رفض الدخول.
6. جرّب فتح مورد لشركة أخرى بمعرّف مباشر وتوقع 403/404 آمن.

### UAT-CRM-001 — العميل والمركبة

1. من حساب الاستقبال أنشئ عميلًا تجريبيًا جديدًا برقم مصري وهمي.
2. أضف مركبة ولوحة وشاسيه وهميين.
3. تحقق من الفرع والشركة وAudit.
4. افتح العميل من مستخدم فرع غير مسموح وتوقع المنع.

### UAT-QUO-001 — عرض السعر

1. من المبيعات أنشئ عرضًا للعميل والمركبة.
2. أضف خدمة ومنتجًا؛ لا ترسل سعرًا/ضريبة موثوقًا من المتصفح.
3. تحقق أن الخادم حسب subtotal/discount/tax/total.
4. Submit وتأكد من Task واحد وإشعار واحد.
5. حاول اعتماد المنشئ وتوقع المنع.
6. اعتمد بمدير القاهرة وتحقق من Audit والحالة والتقارير.

### UAT-WO-001 — الموعد وأمر العمل

1. حوّل العرض المعتمد بالمسار الرسمي إلى موعد/أمر عمل.
2. عيّن الفني واحجز المواد من `UAT-CAI-MAIN`.
3. حاول مخزن الجيزة وتوقع المنع.
4. Start، consume، complete.
5. تحقق أن الخصم تم مرة واحدة فقط وأن تكلفة الأمر لا تتكرر.

### UAT-QUALITY-001 — الجودة وإعادة العمل

1. ابدأ فحص الجودة.
2. ارفض بندًا وأنشئ Rework مرة واحدة.
3. تحقق أن `rework_required` يمنع الانتقال.
4. أكمل إعادة العمل ثم أعد الفحص واعتمد.
5. تحقق من خصوصية الصور وAudit وعدم تنزيل صورة فرع آخر.

### UAT-SALES-001 / UAT-AR-001 — المبيعات والتحصيل

1. أنشئ فاتورة من أمر العمل المسلّم.
2. Submit ثم اعتماد منفصل ثم Post/Issue.
3. تحقق من Snapshot والقيد المتوازن ورابط الترحيل.
4. سجل دفعة جزئية وخصصها ثم أكمل الدفع.
5. حاول duplicate allocation/posting وتوقع المنع.
6. أنشئ Credit Note جزئيًا ورحله.
7. طابق Customer Statement وAR Aging وSales Report وDashboard مع GL.

### UAT-PUR-001 / UAT-AP-001 — المشتريات

1. أنشئ Purchase Requisition واعتمده مركزيًا.
2. أنشئ Purchase Order واعتمده.
3. استلم جزئيًا ثم كاملًا في مخزن القاهرة.
4. أنشئ Supplier Invoice ونفذ matching الفعلي.
5. Submit/Approve/Post بأدوار منفصلة.
6. ادفع جزئيًا ثم كاملًا وسجل Supplier Credit Note.
7. طابق AP Aging وInventory Valuation وGL.

### UAT-INV-001 — المخزون

1. تحقق من رصيد الاستلام.
2. نفذ تحويلًا داخل القاهرة ثم القاهرة/الجيزة.
3. اتبع Submit/Approve/Ship/Receive.
4. نفذ جردًا وفرقًا وتسوية.
5. حاول Negative Stock وتوقع المنع.
6. طابق Ledger وValuation وInventory GL.
7. تأكد أن Transit لا يظهر في العمليات العادية.

### UAT-ACC-001 — المحاسبة والإقفالات

1. راجع قيود المبيعات والمشتريات والقبض والصرف والمخزون والموظفين.
2. تحقق `Debit = Credit`.
3. اعكس قيدًا مسموحًا وتحقق من بقاء التاريخ.
4. اختبر Period Lock وModule Lock ومنع الترحيل.
5. شغّل Trial Balance وGL وIncome Statement وBalance Sheet.
6. املأ ملف المطابقة المحاسبية بفارق صفر.

### UAT-BANK-001 — البنوك والمطابقة

1. استخدم `UAT-BANK-CAI`.
2. استورد كشفًا وهميًا إذا كان مسار الاستيراد الفعلي متاحًا.
3. نفذ matching ومنع duplicate match.
4. أنشئ جلسة Reconciliation وReview ثم Approve.
5. طابق Bank Position مع GL واختبر عزل الفروع.

### UAT-TR-001 — الخزينة

1. افتح جلسة صندوق القاهرة وسجل Opening Count.
2. نفذ قبضًا وصرفًا وتحويلات Bank/Cash بجميع الاتجاهات المدعومة.
3. اختبر الرسوم وretry وfailure rollback وreverse.
4. سجل Closing Count وOver/Short واعتمده ثم أغلق الجلسة.
5. طابق تقارير الخزينة مع GL.
6. تأكد أن أمين القاهرة لا يصل لموارد الجيزة.

### UAT-CHQ-001 — الشيكات

1. Incoming: Create/Submit/Approve/Deposit/Clear.
2. حاول Clear مرتين وتوقع المنع.
3. اختبر Bounce/Return/Replace/Endorsement حسب الحالات.
4. Outgoing: Create/Approve/Present/Clear ثم حالات الإرجاع/الإلغاء.
5. تحقق من Timeline وإخفاء البيانات الحساسة والقيد البنكي.

### UAT-MER-001 — Merchant Settlement

1. سجل Customer Payment بالبطاقة.
2. تحقق من Merchant Clearing.
3. أنشئ Settlement بالرسوم والضريبة الفعلية المرتبطة إن وجدت.
4. اختبر partial allocation وover-allocation.
5. Approve/Post ثم retry وreverse.
6. طابق gross/fees/VAT/net مع GL ولا تكرر Customer Payment.

### UAT-EMP-001 — مالية الموظفين

1. تحقق من Commission Accrual لفاتورة مرحلة واستبعاد VAT من الأساس.
2. Submit واعتماد المدير وترحيل المحاسب.
3. اختبر partial/full settlement وnegative adjustment من Credit Note.
4. نفذ Expense Claim بأرقام الخادم ثم SOD/Post/Pay.
5. نفذ Advance/Disburse/Partial settlement/Expense/Cash return.
6. لا تغلق السلفة قبل التسوية الكاملة؛ طابق التقارير وGL.

### UAT-APR-001 — الاعتمادات والتفويض

1. تحقق أن Task ينشأ مرة واحدة وأن retry لا يكرره.
2. امنع self-approval والحالة الخاطئة.
3. اختبر approve/reject مع السبب.
4. اختبر تفويضًا فعالًا ومستقبليًا ومنتهيًا ودائريًا وعبر شركة/فرع.
5. تحقق من effective actor والحد الأقل وAudit والإشعار.

### UAT-RPT-001 — التقارير واللوحات

استخدم نفس الشركة والفروع والفترة والعملة وطابق Source Documents ثم Posted Journals ثم:

- Executive Dashboard وBranch Dashboard.
- Sales وAR/AP Aging.
- Stock Valuation.
- Treasury وEmployee Finance وApprovals.
- Trial Balance وIncome Statement وBalance Sheet.

كل فرق يجب أن يكون صفرًا أو موثقًا بسبب دقيق.

### UAT-EXP-001 — التصدير والطباعة

1. اختبر CSV وXLSX وPrint/PDF view.
2. تحقق من العربية وRTL والفلاتر والشركة والفرع.
3. أدخل قيمة تبدأ بـ`=`, `+`, `-`, `@` وتحقق من منع formula injection.
4. اختبر صلاحية التصدير الحساس وحد 5,000 صف.
5. تأكد من عدم إنشاء ملف عام دائم.

### UAT-SEC-001 — الأمان

اختبر Cross-company, Cross-branch, IDOR, Mass Assignment, CSRF, throttling, disabled users, private attachments, executable/invalid/oversized uploads, traversal, masking, ownership, delegation escalation, export bypass, health/error leakage, headers وCorrelation ID.

### UAT-OPS-001 — التشغيل

1. شغّل `/health` و`/health/ready`.
2. شغّل validation وmigration scanner وintegrity وasset verification.
3. شغّل Queue worker محدودًا واختبر success/retry/failed/retry بدون أثر مكرر.
4. شغّل أوامر Scheduler مرتين وتحقق من idempotency.
5. نفذ Backup إلى ملف غير متتبع ثم Restore في `seven_ways_uat_restore`.
6. قارن كل الجداول والصفوف والهجرات والبيانات المحاسبية ثم health/login/dashboard/reports.
7. لا تحذف Restore DB إلا بعد التحقق الصريح من الاسم وأنها Disposable.

## 7. حالة التنفيذ الحالية

```text
Automated static/security: Automated Passed
Database-dependent E2E: Blocked by Environment
HTTP Smoke: Not Executed
Manual Browser: Pending
Accounting Reconciliation: Pending
Performance: Blocked by Environment
Queue/Scheduler: Blocked by Environment
Backup/Restore: Blocked by Environment
```
