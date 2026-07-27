# Clean-local database rebuild runbook

This runbook applies only to the disposable database `seven_ways_clean_local` on
`127.0.0.1:3307`. It must never be used for the historical database, port 3306,
or `seven_ways_testing`.

## Safety gate

1. Resolve and print the host, port, database, application environment, PHP
   version, and MariaDB version without printing credentials.
2. Abort unless the database name is exactly `seven_ways_clean_local`, the host
   is `127.0.0.1`, and the port is `3307`.
3. Confirm `seven_ways_testing` exists and record its migration status.
4. Query each operational domain read-only. Stop if journals, invoices,
   treasury documents, employee-finance documents, approvals, notifications,
   audit events, or other unexpected business data exist.
5. Create a logical SQL dump outside Git. Verify that it is non-empty and
   contains table definitions before continuing.

## Rebuild

Use the isolated MariaDB client and an explicit database name. Do not use
`migrate:fresh`, `db:wipe`, wildcards, filesystem deletion, or any command
against port 3306.

1. Explicitly drop only `seven_ways_clean_local`.
2. Create only `seven_ways_clean_local` using `utf8mb4` and
   `utf8mb4_unicode_ci`.
3. Verify the recreated database is empty and `seven_ways_testing` still
   exists.
4. Run `optimize:clear`, `migrate:status`, `migrate --pretend`,
   `migrate --force`, and final `migrate:status` with explicit clean-local
   connection variables.
5. Run the base seeder, followed by the Employee Finance, Central Workflow, and
   Treasury QA seeders twice. Compare stable reference counts and confirm all
   operational tables remain empty.
6. Run `localization:audit-egypt` in read-only mode.
7. Run only HTTP smoke checks on clean-local. Run PHPUnit exclusively against
   `seven_ways_testing`.

## Partial migration diagnosis

MariaDB DDL commits outside the Laravel migration batch transaction. If a
process stops after creating an early table but before Laravel records the
migration, rerunning the migration can fail with a duplicate-table error.
Never repair this by inserting a migration row manually or by deleting an
individual table without a full evidence review.

For a disposable database, retain a verified backup and rebuild it cleanly. For
any database containing business data, stop and design a forward-only,
data-preserving reconciliation after an approved backup and schema audit.

