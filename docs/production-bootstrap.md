# Seven Ways Egypt production bootstrap

1. خذ Backup كاملًا لقاعدة البيانات.
2. تأكد من `APP_ENV=production` و`APP_DEBUG=false`.
3. اضبط المتغيرات الموضحة في `docs/production-bootstrap-env.md`.
4. اضبط `SEVENWAYS_PRODUCTION_BOOTSTRAP=true`.
5. نفّذ `php artisan migrate --force`.
6. راجع Dry Run: `php artisan sevenways:bootstrap-production --dry-run`.
7. راجع الفروع والحسابات والصلاحيات والتسلسلات و`current_number` والربط المحاسبي والمخازن والخزائن.
8. نفّذ Apply: `php artisan sevenways:bootstrap-production --apply --force`.
9. نفّذ التحقق: `php artisan sevenways:verify-production-bootstrap`.
10. نفّذ `php artisan optimize:clear` ثم `php artisan config:cache` و`php artisan route:cache` و`php artisan view:cache`.
11. احفظ التقرير الناتج من `storage/app/private/production-bootstrap-reports/` في مكان آمن.

لا تُشغّل Feature Tests على Production، ولا تنشئ فواتير أو حركات خزينة أو مخزون للاختبار.
