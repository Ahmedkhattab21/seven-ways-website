# Database conventions

## Naming and keys

- Use plural `snake_case` table names and singular `snake_case` columns.
- Use bigint auto-increment `id` for joins and an indexed unique `uuid` for public IDs.
- Name foreign keys `<singular>_id` with explicit constraints and indexes.
- Add composite indexes in the order used by tenant filters and common queries.
- Define unique constraints at the database layer, including tenant columns when
  uniqueness is tenant-local.

Foreign-key delete behavior must be intentional. Prefer `restrict` for posted or audited
records and `nullOnDelete` only for optional historical attribution. Do not cascade-delete
financial, stock, or audit history.

## Numeric values

- Money and unit prices: `decimal(19,4)`.
- Quantities, lengths, roll areas, and conversions: `decimal(19,6)` where needed.
- Never use `float` or `double` for money.
- Store currency explicitly when a document can differ from company base currency.

## Dates and time zones

Store timestamps in UTC and convert at system boundaries using the company/branch
timezone. Use `date` for calendar-only values and `timestamp`/`datetime` for instants.

## Ownership and audit

Tenant-owned rows use `company_id` and optionally `branch_id`; both require foreign keys
and indexes. Public UUID, author tracking, tenancy, and soft deletion are separate,
opt-in concerns. Do not apply tenant scopes to global configuration tables.

## Deletion and posting

Use soft deletion only when recovery is a real requirement. Posted stock or accounting
documents are immutable: corrections use reversal or adjustment entries inside a
transaction, with original and reversing records retained.

Every deployed schema change uses a new forward migration. Never use `migrate:fresh`,
`db:wipe`, or destructive backfills on an existing environment.
