# Backup and Restore Runbook

## Policy

- Database: encrypted/private logical dump daily and before every migration.
- Files: `storage/app/private`, required `storage/app/public`, and production `.env`/APP_KEY
  in a restricted secrets backup. Never back up logs, cache, sessions, vendor, or build source.
- Retention defaults: 7 daily, 4 weekly, 6 monthly; adjust for legal/business policy.
- Store off-server, restrict access, checksum each archive, alert on zero/unexpected size, and
  perform a restore drill at least quarterly.

Use a protected client defaults file so passwords do not appear in commands or process lists:

```bash
mysqldump --defaults-extra-file=/secure/path/mysql-client.cnf --single-transaction --routines --triggers DATABASE > /private/backups/db-YYYYmmdd-HHMMSS.sql
sha256sum /private/backups/db-YYYYmmdd-HHMMSS.sql > /private/backups/db-YYYYmmdd-HHMMSS.sql.sha256
tar -czf /private/backups/storage-YYYYmmdd-HHMMSS.tar.gz -C /path/to/app storage/app/private storage/app/public
```

## Restore drill

1. Enter maintenance mode and take a new backup.
2. Verify checksum and restore the dump to a new temporary database first.
3. Run `migrate:status`, schema/table counts, tenant/branch checks, journal balance checks,
   duplicate document checks, and representative reports.
4. Restore storage to a temporary path and verify counts/checksums and private permissions.
5. Point an isolated application copy to the temporary database/storage.
6. Run `/health/ready`, login, dashboard, posting-read tests, downloads, and asset smoke tests.
7. Obtain approval before switching production. Otherwise discard only the explicitly named
   temporary database and retain incident evidence.
8. Document duration, sizes, checksums, row-count differences, and rollback decision.

Never use the damaged historical database as a production restore source until its independent
recovery and business validation are complete.

