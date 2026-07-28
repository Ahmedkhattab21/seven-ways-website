# Production Rollback Runbook

Rollback is incident-controlled and starts with maintenance mode and a fresh backup.

## Code-only rollback

1. Confirm the previous release is schema-compatible.
2. Switch `current` to the prior approved release, or check out its exact commit.
3. Preserve shared `.env` and storage.
4. Run `optimize:clear`, rebuild caches, restart queues, smoke-test, then `up`.

## Database rule

Never run `migrate:rollback` automatically in production. Prefer a backward-compatible
forward fix. If a migration caused verified data corruption, restore an approved backup only
under the backup/restore runbook and retain the incident database for investigation.

## Failure handling

- If code rollback cannot read the new schema, deploy a compatibility patch.
- If uploads changed, restore only the affected timestamped storage snapshot.
- Record commit IDs, migration status, backup IDs, timestamps, actor, symptoms, and checks.
- Keep maintenance mode active until `/health/ready`, login, critical posting, and asset
  smoke tests pass.

