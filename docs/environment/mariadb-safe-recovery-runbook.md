# MariaDB Safe Recovery Runbook

الحالة المرصودة في 2026-07-27:

- MariaDB 10.4.32 غير مستمعة على port 3306، ولا توجد عملية `mysqld` أو خدمة Windows باسم MySQL/MariaDB.
- `my.ini` يشير إلى `D:/xxamp/mysql/data` وport 3306 بصورة متسقة.
- سجل الأخطاء يثبت `Aria recovery failed` و`InnoDB: Missing MLOG_CHECKPOINT`.
- مجلد البيانات يحتوي 1187 ملفًا بحجم إجمالي 221,230,018 bytes وقت الفحص.
- المساحة الحرة على D: نحو 129 GB.
- الحالة ليست تعارض Port أو Process عالقة؛ تتطلب DBA/manual recovery بعد Backup.

## 1. الإيقاف والتحقق

1. أغلق XAMPP وأي Terminal أو IDE يشغل MariaDB.
2. تحقق:

   ```powershell
   Get-CimInstance Win32_Process |
     Where-Object { $_.Name -match '^(mysqld|mariadbd|mysql)\.exe$' }
   netstat -ano | Select-String ':3306'
   ```

3. لا تبدأ أي Recovery قبل أن تكون النتيجتان فارغتين.

## 2. Backup كامل بالنسخ فقط

استخدم مسارًا جديدًا Timestamped على قرص به مساحة كافية. لا تستخدم Move، ولا تنسخ فوق Backup سابق.

```powershell
$source = 'D:\xxamp\mysql\data'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$destination = "E:\seven-ways-backups\mysql-data-$stamp"
New-Item -ItemType Directory -Path $destination
Copy-Item -LiteralPath $source -Destination $destination -Recurse
```

استبدل `E:` بمسار Backup فعلي منفصل ومناسب. لا تستخدم مجلد `data` نفسه.

## 3. التحقق من النسخة

```powershell
$sourceFiles = Get-ChildItem -LiteralPath $source -Recurse -File
$backupData = Join-Path $destination 'data'
$backupFiles = Get-ChildItem -LiteralPath $backupData -Recurse -File

$sourceFiles.Count
$backupFiles.Count
($sourceFiles | Measure-Object Length -Sum).Sum
($backupFiles | Measure-Object Length -Sum).Sum
```

يجب تطابق عدد الملفات والحجم الإجمالي. يفضل أيضًا إنشاء Checksums للملفات الحساسة على النسخة والمصدر قبل أي تدخل.

## 4. حفظ إعدادات التشخيص

انسخ إلى مجلد Backup منفصل:

- `D:\xxamp\mysql\bin\my.ini`
- `D:\xxamp\mysql\data\mysql_error.log`
- نسخة من أسماء مفاتيح DB في `.env` بعد إزالة كلمات المرور والقيم السرية، مثل host/port/database فقط.

لا تضع `.env` الأصلية داخل Git أو التقرير.

## 5. التصعيد

بعد توثيق Backup موثوق، سلّم النسخة إلى DBA لفحص Aria وInnoDB على نسخة عمل منفصلة. ممنوع على مجلد البيانات الأصلي:

- حذف `aria_log*` أو `ibdata1` أو `ib_logfile*`.
- تشغيل `aria_chk --recover` أو أي repair آلي.
- Force Recovery يكتب على الملفات.
- نسخ System Tables أو Data Directory آخر فوقه.

النتيجة الحالية: **Database recovery requires DBA/manual intervention.**

## 6. بعد الاستعادة

1. أنشئ قاعدة Testing منفصلة، والاسم يجب أن يحتوي `test` أو `testing`.
2. لا تستخدم قاعدة Local في PHPUnit.
3. شغّل `migrate:status` ثم `localization:audit-egypt` read-only.
4. راجع النتائج وخذ Backup منطقي قبل `migrate --pretend` ثم أي Migration فعلية.
5. لا تستخدم `--apply-safe-defaults` قبل مراجعة SAR/VAT15 والتاريخ المرحل.
