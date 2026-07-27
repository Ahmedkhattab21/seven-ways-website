# تقرير تجهيز QA المحلي — Phase 15C Treasury

تاريخ آخر تحديث: 2026-07-27

## النتيجة

تم تجهيز Seeder محلي آمن، واختبارات للصلاحيات وعزل الفروع، ودليل يدوي كامل لدورة الخزينة. بيانات QA مصرية وتستخدم `EGP` وفرعي القاهرة والجيزة. لا ينشئ Seeder أرصدة مخزنة أو مستندات تشغيلية أو قيودًا، ولا يعمل إلا في `local` و`testing`.

لم تُنشأ البيانات فعليًا لأن MariaDB المحلية متوقفة. لم تُنفذ أي محاولة إصلاح قد تمس ملفات البيانات.

## الحسابات

كلمة المرور الموحدة: `Test@123456`.

| الاسم | البريد | الدور | الفرع الافتراضي | الوصول |
|---|---|---|---|---|
| QA Company Owner | `qa.owner@sevenways.test` | `company_owner` | `QA-CAI` | كل الفروع النشطة |
| QA Treasury Manager | `qa.treasury.manager@sevenways.test` | `qa_treasury_manager` | `QA-CAI` | القاهرة والجيزة |
| QA Treasury Accountant | `qa.treasury.accountant@sevenways.test` | `qa_treasury_accountant` | `QA-CAI` | القاهرة والجيزة |
| QA Cairo Cashier | `qa.cairo.cashier@sevenways.test` | `qa_treasury_cashier` | `QA-CAI` | القاهرة فقط |
| QA Giza Cashier | `qa.giza.cashier@sevenways.test` | `qa_treasury_cashier` | `QA-GIZ` | الجيزة فقط |
| QA Treasury Viewer | `qa.treasury.viewer@sevenways.test` | `qa_treasury_viewer` | `QA-CAI` | عرض فقط |
| QA Disabled Cashier | `qa.disabled.cashier@sevenways.test` | `qa_treasury_cashier` | `QA-CAI` | حساب معطل |

قاعدة الصلاحيات المعتمدة:

- المدير: اعتماد وترحيل وعكس طبقًا للحالة والحدود.
- المحاسب: إنشاء وإرسال ومراجعة وترحيل وعكس، بدون `approve` لضمان Separation of Duties.
- أمين الصندوق: الجلسات والعد وإنشاء وإرسال العمليات المسموح بها.
- المراقب: صلاحيات العرض والتقارير فقط.

## البيانات المرجعية

- الفروع: `QA-CAI`, `QA-GIZ`.
- الصناديق: `QA-CAI-MAIN`, `QA-CAI-SALES`, `QA-GIZ-MAIN`.
- البنك: `QA-BANK` باسم `QA Egypt Bank`.
- الحسابات البنكية: `QA-BANK-CAI`, `QA-BANK-GIZ` بأرقام وهمية مقنعة وبدون IBAN.
- حسابات النقدية: `QA-CASH-CAI-M`, `QA-CASH-CAI-S`, `QA-CASH-GIZ-M`.
- حسابات مساعدة: `QA-TR-CLEAR`, `QA-BANK-FEES`, `QA-CASH-OS`, `QA-OTHER-INCOME`, `QA-GENERAL-EXP`.
- الحسابات النظامية المستخدمة دون تغيير: `115000`, `116000`, `117000`, `214000`, `651000`.
- طرق الدفع: `QA-CASH`, `QA-CARD`, `QA-ONLINE`.
- العملة: `EGP` النشطة فقط؛ لا يوجد fallback إلى عملة أخرى.
- حدود التحويل: مستوى الشركة `10,000 EGP`، دور فرع القاهرة `5,000 EGP`، مستخدم المدير `20,000 EGP`.
- الضريبة على رسوم Merchant Settlement: حسب Tax وMapping المصري الفعلي، وقد تكون صفرًا.

## الأمان وIdempotency

- كل المفاتيح الطبيعية ثابتة، والكتابة داخل transaction.
- لا يُعدل Seeder مستخدمين أو فروعًا تشغيلية حقيقية.
- لا ينشئ Sessions أو Transfers أو Cash Operations أو Cheques أو Settlements أو Journals.
- لا توجد أعمدة stored balance للصندوق أو الحساب البنكي.
- عزل القاهرة والجيزة ينتج 403 آمنًا في الاتجاهين.
- العملة مطلوبة بالبحث عن `EGP` النشطة، ويُرفض التشغيل إذا كانت غير موجودة أو ليست العملة الأساسية للشركة.

## الاختبارات

`tests/Feature/TreasuryManualQaTest.php` يغطي:

1. الدخول ورفض الحساب المعطل والشركة المعطلة والمستخدم بلا فرع.
2. Viewer للعرض فقط.
3. نطاق أمين صندوق القاهرة واشتراط الجلسة ومنع التلاعب بالحقول.
4. عزل القاهرة والجيزة في الاتجاهين.
5. أولوية User/Role وBranch/Company limits.
6. Idempotency وعدم إنشاء عمليات أو قيود أو أرصدة.
7. منع المحاسب من الاعتماد والسماح للمدير.

الاختبارات لم تصل إلى assertions في البيئة الحالية بسبب `SQLSTATE[HY000] [2002] connection refused`.

## الأوامر السابقة

| الأمر | النتيجة |
|---|---|
| Seeder مرتين | فشل اتصال قبل أي كتابة |
| `php artisan test --filter=TreasuryManualQa` | تعطل بيئي بسبب MariaDB |
| `vendor/bin/pint --test` | PASS |
| `composer validate` | PASS |
| `npm.cmd run build` | PASS مع تحذيرات assets قديمة |
| `php artisan view:cache` | PASS |
| `php artisan route:list` | PASS |
| `git diff --check` | PASS |

## بدء الدورة بعد إصلاح MariaDB

1. أخذ Backup وفحص MariaDB بواسطة مسؤول قاعدة البيانات.
2. تشغيل `TreasuryManualQaSeeder` مرتين.
3. تشغيل اختبارات Treasury QA ثم الاختبارات الكاملة.
4. تشغيل الخادم على `127.0.0.1:8085`.
5. تنفيذ Cycles 1–12 من `phase-15c-treasury-manual-cycle.md` وتسجيل Actual Result وHTTP Status وScreenshot.

لم تُعدل أي مستندات مالية تاريخية.
