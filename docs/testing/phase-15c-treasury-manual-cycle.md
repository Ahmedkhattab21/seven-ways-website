# دليل QA اليدوي — Phase 15C Treasury

> هذا الدليل للبيئتين `local` و`testing` فقط. لا تشغّل Seeder على Production، ولا تستخدم `migrate:fresh` أو `db:wipe`.

## 1. حسابات الاختبار

كلمة المرور لكل الحسابات: `Test@123456`.

| الدور | البريد | كلمة المرور | الفرع الافتراضي | الفروع المسموحة | الغرض |
| ----- | ------ | ----------- | --------------- | --------------- | ----- |
| Company Owner — `company_owner` | `qa.owner@sevenways.test` | `Test@123456` | فرع القاهرة QA | كل فروع Seven Ways النشطة | الإدارة، إعدادات الخزينة، الحدود، وفحص كل الفروع |
| Treasury Manager — `qa_treasury_manager` | `qa.treasury.manager@sevenways.test` | `Test@123456` | فرع القاهرة QA | القاهرة QA والجيزة QA | الاعتماد، الترحيل، العكس، والبيانات الحساسة |
| Treasury Accountant — `qa_treasury_accountant` | `qa.treasury.accountant@sevenways.test` | `Test@123456` | فرع القاهرة QA | القاهرة QA والجيزة QA | الإنشاء، الإرسال، المراجعة، الترحيل، والعكس؛ بدون اعتماد لضمان SOD |
| Cairo Cashier — `qa_treasury_cashier` | `qa.cairo.cashier@sevenways.test` | `Test@123456` | فرع القاهرة QA | القاهرة QA فقط | الجلسة والعد والمقبوضات والمدفوعات |
| Giza Cashier — `qa_treasury_cashier` | `qa.giza.cashier@sevenways.test` | `Test@123456` | فرع الجيزة QA | الجيزة QA فقط | اختبار عزل الجيزة |
| Treasury Viewer — `qa_treasury_viewer` | `qa.treasury.viewer@sevenways.test` | `Test@123456` | فرع القاهرة QA | القاهرة QA فقط | عرض فقط، بدون بيانات شيكات حساسة |
| Disabled Cashier — `qa_treasury_cashier` | `qa.disabled.cashier@sevenways.test` | `Test@123456` | فرع القاهرة QA | القاهرة QA فقط | اختبار منع الدخول |

الأدوار النظامية الفعلية الموجودة في المشروع تشمل: `company_owner`, `general_manager`, `branch_manager`, `accountant`, `sales`, `warehouse_keeper`, `technician`, `quality_controller`, و`receptionist`. أنشأ Seeder أدوار QA مستقلة حتى لا يغيّر صلاحيات مستخدمين حقيقيين.

## 2. التجهيز

شغّل من جذر المشروع باستخدام PHP 8.2:

```bash
php artisan db:seed --class=TreasuryManualQaSeeder
php artisan db:seed --class=TreasuryManualQaSeeder
php artisan test --filter=TreasuryManualQa
php artisan serve --host=127.0.0.1 --port=8085
```

الرابط الأساسي:

```text
http://127.0.0.1:8085
```

إذا فشل Seeder لأن الفترة الحالية مغلقة أو `treasury` مقفولة، لا يفتحها Seeder ولا يغيرها. راجع الفترة يدويًا أو استخدم تاريخًا مسموحًا بعد موافقة مسؤول الحسابات.

## 3. البيانات المرجعية

### الفروع والصناديق

| النوع | الكود | الاسم | الجلسة مطلوبة |
| --- | --- | --- | --- |
| Branch | `QA-CAI` | فرع القاهرة QA | — |
| Branch | `QA-GIZ` | فرع الجيزة QA | — |
| Cash Box | `QA-CAI-MAIN` | صندوق رئيسي — القاهرة QA | نعم |
| Cash Box | `QA-CAI-SALES` | صندوق مبيعات — القاهرة QA | لا |
| Cash Box | `QA-GIZ-MAIN` | صندوق رئيسي — الجيزة QA | نعم |

كل صندوق مرتبط بحساب GL مستقل، ولا يوجد عمود stored balance. `Opening Book` و`Closing Book` يأتيان من دفتر الأستاذ.

### البنك والحسابات البنكية

| الكود | الاسم | الفرع | الرقم الظاهر |
| --- | --- | --- | --- |
| `QA-BANK-CAI` | QA Cairo Bank Account | القاهرة QA | `QA-****-1001` |
| `QA-BANK-GIZ` | QA Giza Bank Account | الجيزة QA | `QA-****-2001` |

البنك المستخدم `QA-BANK` وهمي بالكامل. لا يحتوي Seeder على IBAN أو رقم حساب حقيقي.

### الحسابات المحاسبية

| الكود | الاستخدام |
| --- | --- |
| `QA-CASH-CAI-M` | صندوق القاهرة الرئيسي |
| `QA-CASH-CAI-S` | صندوق مبيعات القاهرة |
| `QA-CASH-GIZ-M` | صندوق الجيزة الرئيسي |
| `QA-BANK-CAI` | بنك القاهرة QA |
| `QA-BANK-GIZ` | بنك الجيزة QA |
| `QA-TR-CLEAR` | Transfer clearing مرجعي؛ محرك Phase 15C يرحّل مباشرة بين الطرفين |
| `QA-BANK-FEES` | رسوم البنك والتحويل |
| `QA-CASH-OS` | حساب موحد لفروق الزيادة والعجز طبقًا لتصميم `over_short_account_id` الحالي |
| `116000` | Cheques under collection |
| `214000` | Cheques payable |
| `117000` | Merchant clearing |
| `651000` | Merchant fees |
| `115000` | Input VAT |
| `QA-OTHER-INCOME` | مقابل عام للمقبوضات |
| `QA-GENERAL-EXP` | مقابل عام للمدفوعات |

### طرق الدفع والـMappings

| الكود | الاسم | Mapping |
| --- | --- | --- |
| `QA-CASH` | Cash QA | صندوق وحساب نقدية الفرع |
| `QA-CARD` | Card QA | Merchant Clearing + Merchant Fees + بنك الفرع |
| `QA-ONLINE` | Online QA | Merchant Clearing + Merchant Fees + بنك الفرع |

### حدود الاعتماد

- `qa_treasury_manager` على مستوى الشركة: `10,000 EGP`.
- نفس الدور في فرع القاهرة: `5,000 EGP`.
- المستخدم `QA Treasury Manager` في القاهرة: `20,000 EGP`.
- أضيفت حدود تشغيلية غير معتمدة للـCashier حتى `5,000 EGP` للإنشاء والإرسال فقط.
- أضيفت حدود تشغيلية للمحاسب للإنشاء والإرسال والترحيل بدون اعتماد، وللمدير للاعتماد.
- User rule يسبق Role rule، وBranch rule يسبق Company rule.

## 4. الحالة الفعلية ودورة الإجراءات

| المستند | الحالات الفعلية | الإجراءات الفعلية |
| --- | --- | --- |
| Transfer | `draft`, `pending_approval`, `approved`, `processing`, `completed`, `failed`, `cancelled`, `reversed` | `submit`, `approve`, `cancel`, `process`, `reverse` |
| Cash Session | `opened`, `counting`, `pending_approval`, `approved`, `closed`, `cancelled` | `start_counting`, `submit`, `approve`, `close`, `cancel`, `reopen` |
| Cash Count | `draft`, `submitted`, `reviewed`, `approved`, `cancelled` | `submit`, `review`, `approve`, `cancel` |
| Over/Short | `draft`, `pending_approval`, `approved`, `posted`, `reversed` | `submit`, `approve`, `post`, `reverse` |
| Cash Receipt/Payment | `draft`, `pending_approval`, `approved`, `posted`, `reversed`, `cancelled` | `submit`, `approve`, `post`, `reverse` |
| Cheque | `draft`, `received`, `issued`, `on_hand`, `deposited`, `under_collection`, `presented`, `cleared`, `bounced`, `returned`, `cancelled`, `replaced` | `submit`, `approve`, `deposit`, `present`, `clear`, `bounce`, `return`, `cancel`, `replace`, `endorse` |
| Merchant Settlement | `draft`, `pending_approval`, `approved`, `posted`, `partially_matched`, `matched`, `reversed`, `cancelled` | `submit`, `approve`, `post`, `reverse` |

الاعتماد والترحيل منفصلان في Transfers وCash وMerchant. اعتماد الشيك ينشئ قيد الاعتراف، ثم `clear` ينشئ قيد البنك. منشئ Transfer/Cash Operation/Cheque/Settlement لا يعتمد مستنده. أمين الجلسة لا يعتمدها عند تفعيل SOD. المنع العام يخص الاعتماد؛ الترحيل يعتمد على الصلاحية والحد والحالة.

## 5. إعداد رصيد دفتري رسمي

Seeder لا ينشئ رصيدًا ولا قيدًا. ابدأ باختبار الرصيد الصفري إن كان مناسبًا. إذا احتجت رصيدًا:

1. ادخل بـ`QA Company Owner`.
2. افتح `/accounting/opening-balances`.
3. أنشئ Opening Balance رسمي بتاريخ فترة QA المفتوحة.
4. أضف سطور حسابات `QA-CASH-*` و`QA-BANK-*`، والطرف المقابل حساب افتتاحي/حقوق ملكية معتمد مثل `310000`.
5. نفذ `submit` ثم `approve` بحساب آخر ثم `mark_ready` ثم `post`.
6. افتح Journal Entry وتأكد أنه متوازن.
7. لا تعدّل `cash_boxes` أو`bank_accounts` مباشرة؛ لا توجد أعمدة رصيد أصلًا.

## 6. Cycle 1 — Authentication and Permissions

| Test ID | الحساب/URL | الإجراء | المتوقع | HTTP |
| --- | --- | --- | --- | --- |
| TR-AUTH-001 | كل حساب نشط `/login` | تسجيل الدخول | Dashboard وSidebar حسب الصلاحيات | 302 ثم 200 |
| TR-AUTH-002 | Disabled Cashier | تسجيل الدخول | رسالة عامة وعدم إنشاء Session Auth | 302 |
| TR-AUTH-003 | Viewer | فتح `/treasury/transfers` | عرض القائمة بدون نموذج/أزرار تغيير | 200 |
| TR-PERM-001 | Viewer | POST مباشر لإنشاء Transfer | ممنوع | 403 |
| TR-PERM-002 | Viewer | فتح Sessions/Cash/Cheques/Settlements/Reports | عرض فقط | 200 |
| TR-PERM-003 | Cairo Cashier | فتح Route لعنصر الجيزة بعد تبديل ID | لا بيانات مسربة | 403 أو 404 |
| TR-PERM-004 | Cairo Cashier | مراجعة Sidebar | لا تظهر إدارة الحدود أو الاعتماد/الترحيل | — |

سجّل العناصر الظاهرة والمخفية وصورة للـ403.

## 7. Cycle 2 — Cash Session

الصفحة: `/treasury/cash-sessions`.

| Test ID | الحساب | الإجراء/البيانات | المتوقع | الحالة/القيد |
| --- | --- | --- | --- | --- |
| TR-CS-001 | Cairo Cashier | افتح `QA-CAI-MAIN` بتاريخ اليوم | Opening Book من GL | `opened`، لا قيد |
| TR-CS-002 | Cairo Cashier | افتح جلسة ثانية للصندوق نفسه | رفض | 422/Business error، جلسة واحدة |
| TR-CS-003 | Cairo Cashier | Opening Count: فئة 100 × 10 | الخادم يحسب 1,000 | Count `draft` |
| TR-CS-004 | Cairo Cashier | `submit` للعد | نجاح | `submitted` |
| TR-CS-005 | Treasury Manager | `review` ثم `approve` | نجاح وفصل مهام | `approved` |
| TR-CS-006 | Cairo Cashier | `start_counting` ثم أنشئ العمليات النقدية | نجاح | Session `counting` |
| TR-CS-007 | Cairo Cashier | Closing Count مطابق للمتوقع | Difference = 0 | لا Over/Short |
| TR-CS-008 | Cairo Cashier | Closing Count بفارق | الخادم يحسب الفرق | لا Close قبل المعالجة |
| TR-CS-009 | Treasury Manager | أنشئ فرقًا ثم `submit/approve/post` | قيد دقيق | Over: Dr Cash/Cr OS؛ Short عكسه |
| TR-CS-010 | Cashier ثم Manager | Session `submit/approve/close` | إغلاق بعد Count معتمد | `closed` |
| TR-CS-011 | أي مستخدم | تعديل Session مغلقة أو Count معتمد | رفض | 403/422 |

## 8. Cycle 3 — Cash Receipt

الصفحة: `/treasury/cash-receipts`.

1. افتح Session القاهرة الرئيسية أولًا.
2. أنشئ `1,000 EGP` على `QA-CAI-MAIN`، Session المفتوحة، والحساب `QA-OTHER-INCOME`.
3. Cashier: Save ثم `submit`.
4. جرّب الاعتماد بنفس المنشئ: يجب الرفض.
5. Treasury Manager: `approve`.
6. Treasury Accountant أو Manager: `post`.
7. افتح القيد:

```text
Debit:  QA-CASH-CAI-M    1,000
Credit: QA-OTHER-INCOME  1,000
```

| Test ID | الاختبار | المتوقع |
| --- | --- | --- |
| TR-CR-001 | إنشاء بدون Session للصندوق الرئيسي | 404/رفض |
| TR-CR-002 | Draft → Submit → Approve → Post | `posted` وقيد واحد |
| TR-CR-003 | إعادة POST على `post` | لا قيد ثانٍ |
| TR-CR-004 | `reverse` بسبب موثق | `reversed` وقيد مقابل |
| TR-CR-005 | تعديل بعد الترحيل/العكس | رفض |
| TR-CR-006 | إرسال `company_id/status/document_number/journal_entry_id` | 422 ولا حفظ |

## 9. Cycle 4 — Cash Payment

أنشئ `300 EGP` على `QA-CAI-MAIN` والحساب `QA-GENERAL-EXP`.

```text
Debit:  QA-GENERAL-EXP  300
Credit: QA-CASH-CAI-M   300
```

| Test ID | الإجراء | المتوقع |
| --- | --- | --- |
| TR-CP-001 | بدون Session مطلوبة | رفض |
| TR-CP-002 | Draft/Submit/Approve/Post | قيد واحد متوازن |
| TR-CP-003 | Retry | لا Double posting |
| TR-CP-004 | Reverse | قيد مقابل دقيق |
| TR-CP-005 | مبلغ يتجاوز حد أمين الصندوق أو الرصيد/السياسة | رفض |

## 10. Cycle 5 — Treasury Transfers

الصفحة: `/treasury/transfers`. أنشئ بالمحاسب، اعتمد وعالج بالمدير.

| Test ID | الاتجاه | المبلغ/الرسوم | القيد المتوقع |
| --- | --- | --- | --- |
| TR-TR-001 | Bank Cairo → Bank Giza | 2,000 / 25 | Dr بنك الجيزة 2,000؛ Dr رسوم 25؛ Cr بنك القاهرة 2,025 |
| TR-TR-002 | Bank Cairo → Cash Cairo | 1,000 / 0 | Dr صندوق القاهرة؛ Cr بنك القاهرة |
| TR-TR-003 | Cash Cairo → Bank Cairo | 500 / 0 | Dr بنك القاهرة؛ Cr صندوق القاهرة |
| TR-TR-004 | Cash Cairo → Cash Sales Cairo | 200 / 0 | Dr صندوق المبيعات؛ Cr الصندوق الرئيسي |

لكل Transfer نفّذ: `create → submit → approve → process → completed → retry → reverse`. تحقق من قيد واحد، Posting Link واحد، ثم قيد عكس واحد. اختبر أيضًا:

- المصدر = الوجهة: رفض.
- مصدر/وجهة الجيزة بحساب Cairo-only: 403.
- عملة مختلفة أو `exchange_rate != 1`: رفض.
- تعديل `completed`: رفض.
- الرسوم منفصلة عن أصل التحويل.

## 11. Cycle 6 — Cash Deposit and Withdrawal

| Test ID | النوع | الأطراف | المتوقع |
| --- | --- | --- | --- |
| TR-CD-001 | `cash_deposit` | Cash Box → Bank | نفس Transfer Engine ومستند واحد |
| TR-CW-001 | `cash_withdrawal` | Bank → Cash Box | نفس Transfer Engine ومستند واحد |
| TR-CD-002 | Retry | نفس المستند | لا قيد إضافي |

## 12. Cycle 7 — Incoming Cheque

الصفحة: `/treasury/cheques/received`. أنشئ شيك `3,000 EGP`:

- Bank: `QA-BANK`.
- Bank Account: `QA-BANK-CAI`.
- Clearing: `116000`.
- Offset: حساب عميل رسمي أو `QA-OTHER-INCOME` لاختبار دورة عامة.

| Test ID | الإجراء | الحالة | القيد/النتيجة |
| --- | --- | --- | --- |
| TR-RCH-001 | Create/Submit | `received` | لا قيد قبل الاعتماد |
| TR-RCH-002 | Approve بحساب آخر | `on_hand` | Dr 116000 / Cr Offset |
| TR-RCH-003 | Deposit | `deposited` | لا قيد بنك بعد |
| TR-RCH-004 | Clear | `cleared` | Dr Bank / Cr 116000 |
| TR-RCH-005 | Clear مرة ثانية | بلا تغيير | رفض، لا قيد ثانٍ |
| TR-RCH-006 | Bounce بسبب موثق | `bounced` | عكس مستقل لقيد Clearance |
| TR-RCH-007 | Return | `returned` | القيود الأصلية محفوظة |
| TR-RCH-008 | Replace | الأصلي `replaced` والجديد `draft` | رقم جديد فريد |
| TR-RCH-009 | Endorse وهو `on_hand` | Endorsement pending | اعتماد منفصل، بدون قيد |
| TR-RCH-010 | Viewer مقابل Manager | رقم masked/ظاهر | حسب `view_sensitive` |
| TR-RCH-011 | رقم مكرر في نفس النطاق | — | رفض |

## 13. Cycle 8 — Outgoing Cheque

الصفحة: `/treasury/cheques/issued`. أنشئ `1,500 EGP`:

- Clearing: `214000`.
- Offset: `QA-GENERAL-EXP` أو حساب مورد رسمي.

```text
عند الاعتماد:
Debit:  Offset
Credit: 214000 Cheques Payable

عند Clear بعد Present:
Debit:  214000
Credit: QA-BANK-CAI
```

اختبر `submit`, `approve`, `present`, `clear`, منع الخصم مرتين، `bounce/return/cancel/replace` حسب الحالة، الـTimeline، وإخفاء الأرقام.

## 14. Cycle 9 — Merchant Settlement

1. أنشئ CustomerPayment رسميًا بطريقة `QA-CARD` أو `QA-ONLINE`.
2. اعتمده/رحّله من دورة Customer Payments حتى يكون Debit على `117000 Merchant Clearing`.
3. افتح `/treasury/merchant-settlements`.
4. أنشئ تخصيصًا:

```text
Gross: 1,000 EGP
Fees: 20 EGP
VAT: حسب الضريبة المصرية الفعلية المرتبطة والمفعلة
Net: يحسبه الخادم
```

| Test ID | الإجراء | المتوقع |
| --- | --- | --- |
| TR-MS-001 | Partial allocation | نجاح والمتبقي متاح |
| TR-MS-002 | تسوية ثانية للباقي | نجاح |
| TR-MS-003 | Over-allocation | رفض |
| TR-MS-004 | Payment غير مرحل | رفض |
| TR-MS-005 | Payment لا يستخدم Merchant Clearing | رفض |
| TR-MS-006 | تزوير gross/net/allocated totals | 422 أو تجاهل القيم المحمية |
| TR-MS-007 | Submit/Approve/Post | قيد واحد متوازن |
| TR-MS-008 | Retry/Reverse | لا تكرار وقيد عكس دقيق |

```text
Debit:  QA Bank Account      Net
Debit:  651000               Fees
Debit:  115000               VAT if applicable
Credit: 117000               Gross
```

لا يجب إنشاء CustomerPayment جديد.

## 15. Cycle 10 — Approval Limits

| Test ID | الحساب | الفرع | المبلغ | القاعدة المتوقعة | النتيجة |
| --- | --- | --- | --- | --- | --- |
| TR-LIM-001 | QA Treasury Manager | Cairo | 4,000 | User 20,000 يسبق Role | Allow |
| TR-LIM-002 | QA Treasury Manager | Cairo | 7,000 | User 20,000 | Allow |
| TR-LIM-003 | QA Treasury Manager | Cairo | 15,000 | User 20,000 | Allow |
| TR-LIM-004 | مستخدم Probe بنفس Role بلا User rule | Cairo | 7,000 | Branch Role 5,000 | Deny |
| TR-LIM-005 | نفس Probe | Giza | 7,000 | Company Role 10,000 | Allow |
| TR-LIM-006 | نفس Probe | Giza | 15,000 | Company Role 10,000 | Deny |
| TR-LIM-007 | Owner | Approval Limits | قاعدة متداخلة لنفس subject/scope/date | رفض |

## 16. Cycle 11 — Accounting Locks

1. سجّل حالة الفترة و`locked_modules` قبل التغيير.
2. نفّذ هذا الـCycle فقط إذا كان كود الفترة يبدأ بـ`QA-`. إذا استخدم Seeder فترة محلية قائمة، انتقل إلى قاعدة testing معزولة ولا تغيّرها.
3. بحساب Owner اقفل `treasury` على فترة QA فقط.
4. جرّب Post وReverse: يجب الرفض.
5. أزل Module Lock وأغلق فترة QA بالطريقة الرسمية.
6. جرّب Post وReverse: يجب الرفض.
7. افتح Closing Validation وتأكد أن Draft/Pending Treasury Operations تظهر.
8. أعد فترة QA وModule Lock إلى القيم المسجلة قبل الاختبار. لا تغيّر فترة تشغيل حقيقية.

| Test ID | الاختبار | المتوقع |
| --- | --- | --- |
| TR-LOCK-001 | Treasury module locked | منع Post |
| TR-LOCK-002 | Treasury module locked | منع Reverse |
| TR-LOCK-003 | Period closed | منع Post/Reverse |
| TR-LOCK-004 | Closing validation | العمليات غير المرحلة ظاهرة |

## 17. Cycle 12 — Reports

الصفحة: `/treasury/operation-reports`.

راجع:

- Open Cash Sessions.
- Cash Count / Over-Short Register.
- Treasury Transfer Register.
- Cheque Register / Aging / Bounced.
- Merchant Settlement Register.
- Pending Treasury Operations.

اختبر بحساب Owner، Viewer، Cairo Cashier، وGiza Cashier. يجب أن تُحترم الشركة والفروع والصلاحيات، وألا تظهر بيانات فرع آخر. طابق الإجماليات مع المستندات والقيود.

## 18. نموذج توثيق النتيجة

انسخ هذا القالب لكل Test ID:

```text
Test ID:
Module:
Account:
Role:
Branch:
URL:
Action:
Input:
Expected Status:
Expected Result:
Expected Journal Entry:
Actual Result:
HTTP Status:
Passed / Failed:
Screenshot:
Notes:
```

## 19. Checklist النهائية

- [ ] Seeder اشتغل مرتين بدون تكرار.
- [ ] الحسابات السبعة وحالات الدخول صحيحة.
- [ ] Viewer عرض فقط.
- [ ] Cairo/Giza isolation يعيد 403 أو 404.
- [ ] لا Stored Cash/Bank Balance.
- [ ] Session واحدة نشطة لكل صندوق.
- [ ] Counts محسوبة في الخادم.
- [ ] Over/Short معتمد ومرحل قبل الإغلاق.
- [ ] Receipt/Payment لا يتجاوزان الدورة الحالية.
- [ ] الاتجاهات الأربعة للتحويل نجحت.
- [ ] Retry لا يكرر القيود.
- [ ] Reversal ينشئ قيدًا واحدًا مقابلًا.
- [ ] Cheque Timeline وMasking صحيحان.
- [ ] Merchant Settlement لا يكرر CustomerPayment.
- [ ] User/Role وBranch/Company limit precedence صحيح.
- [ ] Period/Module locks تمنع Post وReverse.
- [ ] التقارير محكومة بالفرع والصلاحية.
- [ ] كل Screenshot وActual Result وHTTP Status موثق.
