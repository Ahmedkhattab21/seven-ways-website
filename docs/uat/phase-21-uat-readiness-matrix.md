# Phase 21 UAT Readiness Matrix

Date: 2026-07-28

This matrix describes implemented product paths discovered from routes, controllers, services, policies, migrations, seeders, and the existing automated suite. Runtime execution against `seven_ways_uat` is blocked while MariaDB on `127.0.0.1:3307` is unavailable.

| Module | Main documents | States | Approval | Posting | Reversal | Reports | Export | UAT ready |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Company / branches | Company, Branch | active/inactive | N/A | N/A | N/A | Dashboard | No | Ready |
| Users / access | User, Role, Branch Access | active/inactive | N/A | N/A | N/A | Audit | No | Ready |
| Customers / vehicles | Customer, Vehicle | active/inactive | N/A | N/A | N/A | Customer views | No | Ready |
| Products / services | Product, Service | active/inactive | N/A | N/A | N/A | Catalog | No | Ready |
| Quotations | Quotation | draft/pending/approved/rejected/sent/accepted | Central/domain | No | Versioning/cancel | Sales | Print | Ready |
| Appointments | Appointment | draft/confirmed/checked-in/completed/cancelled/no-show | Domain | No | Cancel/no-show | Operations | No | Ready |
| Work orders | Work Order | draft/in-progress/awaiting-quality/delivered/cancelled | Domain | Inventory/cost effects | Reopen/cancel | Operations | Print | Ready |
| Quality / rework | Quality Check, Rework | pending/in-progress/passed/rework-required | Domain | No | Reopen/rework | Quality | No | Ready |
| Delivery / warranty | Delivery, Warranty, Claim | workflow states | Domain | Domain posting where applicable | Controlled | Warranty | Print | Ready |
| Sales | Sales Invoice, Credit Note | draft/pending/approved/issued/posted/cancelled | SOD | Accounting engine | Credit/reversal | Sales/AR | CSV/XLSX/print | Ready |
| Receivables | Payment, Allocation, Refund | draft/approved/processed/reversed | SOD | Accounting engine | Allocation/refund reversal | Statement/aging | Export | Ready |
| Purchasing | Requisition, PO, GRN, Supplier Invoice | draft/pending/approved/received/posted | Central/domain | Accounting/inventory | Return/credit/reversal | Purchasing/AP | Export | Ready |
| Inventory | Opening, Adjustment, Count, Transfer | draft/approved/posted/shipped/received | Domain | Inventory/GL | Controlled reversal | Ledger/valuation | Export | Ready |
| Accounting | Journal, Period, Closing | draft/pending/approved/posted/closed | SOD | Journal engine | Journal reversal | TB/GL/IS/BS | Export | Ready |
| Banking | Statement, Match, Reconciliation | imported/matched/reviewed/approved | SOD | Adjustments | Controlled | Bank position | Export | Ready |
| Treasury | Sessions, Receipts, Payments, Transfers | draft/pending/posted/reversed/closed | SOD and limits | Journal engine | Controlled | Treasury reports | Export | Ready |
| Cheques | Incoming/outgoing cheque | draft/approved/deposited/presented/cleared/bounced | SOD | Journal engine | Return/cancel/replace | Timeline/reports | Export | Ready |
| Merchant settlements | Settlement | draft/pending/approved/posted/reversed | SOD | Journal engine | Controlled | Treasury reports | Export | Ready |
| Employee finance | Commission, Expense, Advance | draft/pending/approved/posted/settled | Central/SOD | Journal engine | Controlled | Employee finance | Export | Ready |
| Central approvals | Task, Workflow, Delegation | pending/approved/rejected/overdue/expired | Effective actor | Source-owned | Source-owned | Approval report | Export | Ready |
| Notifications | System Notification | unread/read/dismissed | N/A | N/A | N/A | Inbox | No | Ready |
| Unified audit | Audit Event | immutable event | N/A | N/A | N/A | Search | Masked export | Ready |
| Dashboards | Executive, Branch | read-only | N/A | N/A | N/A | Dashboard | No | Ready |
| Financial reports | TB/GL/IS/BS/Cash Flow | read-only | N/A | Posted GL | N/A | Yes | CSV/XLSX | Ready |
| Operational reports | Sales/stock/treasury/employee | read-only | N/A | Source-specific | N/A | Yes | CSV/XLSX | Ready |
| Public website | Public pages | published | N/A | N/A | N/A | N/A | No | Ready |
| Health / queue / scheduler | Health, Job, schedule | ready/unready/queued/failed | N/A | N/A | retry | Operations | No | Ready |
| Backup / restore | Logical dump | backup/restored/verified | Operations sign-off | N/A | Restore | Integrity | No | Blocked by environment |

## Audit findings

- `InventorySeeder` and `SevenWaysOperationalSeeder` intentionally target the operational `Seven Ways` tenant and cannot provision a separate UAT tenant.
- `SevenWaysUatSeeder` therefore creates only the isolated UAT tenant, roles, users, Egyptian master data, warehouses, services, customers, vehicles, suppliers, employees, cash boxes and test bank accounts.
- `ProductionReferenceSeeder` remains the source of permissions and module reference mappings.
- The UAT seed creates no posted document, journal, stock balance, approval task, notification, delegation, or fake audit event.
- `UatPerformanceSeeder` is separate, explicitly guarded, and must only be run after the base UAT seed.
- Browser evidence, accounting reconciliation, queue worker execution, scheduler execution, performance timings, and backup/restore remain pending because MariaDB port 3307 is stopped.
