# Seven Ways production bootstrap environment

لا تضع أي قيمة حقيقية في Git. تُضبط القيم التالية في Environment الخاصة بالسيرفر فقط:

- `SEVENWAYS_PRODUCTION_BOOTSTRAP`: بوابة أمان؛ اجعلها `true` فقط وقت تنفيذ Apply.
- `SEVENWAYS_COMPANY_ID`: معرّف شركة Seven Ways اختياريًا. عند تركه فارغًا يتم البحث بالاسم.
- `SEVENWAYS_NASR_MANAGER_EMAIL` و`SEVENWAYS_NASR_MANAGER_PASSWORD`: حساب مسؤول مدينة نصر.
- `SEVENWAYS_ALEX_MANAGER_EMAIL` و`SEVENWAYS_ALEX_MANAGER_PASSWORD`: حساب مسؤول الإسكندرية.
- `SEVENWAYS_ACCOUNTANT_EMAIL` و`SEVENWAYS_ACCOUNTANT_PASSWORD`: حساب المحاسب.
- `SEVENWAYS_GENERAL_MANAGER_EMAIL` و`SEVENWAYS_GENERAL_MANAGER_PASSWORD`: حساب المدير العام.

كل كلمات المرور مطلوبة لإنشاء الحسابات الجديدة، لا تُطبع في التقرير، ولا تتغير عند إعادة التشغيل إلا بخيار `--rotate-passwords`.
