# Phase 21 Accounting Reconciliation Pack

Status: Pending execution on `seven_ways_uat`.

For every row, attach the source document, journal detail, report filter, dashboard filter, and evidence. Posted rows must satisfy `Debit = Credit` and `Difference = 0`.

| Cycle | Source Document | Journal Number | Debit | Credit | Report Total | Dashboard Total | Difference |
| --- | --- | --- | ---: | ---: | ---: | ---: | ---: |
| Sales invoice | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Customer payment | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Credit note | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Purchase invoice | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Supplier payment | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Supplier credit note | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Inventory receipt | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Inventory issue | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Inventory adjustment | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Treasury receipt | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Treasury payment | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Treasury transfer | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Cheque clearing | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Merchant settlement | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Commission accrual | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Commission settlement | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Expense claim | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Employee advance | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |
| Employee advance settlement | Pending | Pending | 0.00 | 0.00 | 0.00 | 0.00 | 0.00 |

## Mandatory queries/checks

- `production:check-integrity` reports zero unbalanced posted journals.
- Every posted source document has one official posting link.
- Reversal references the original journal and does not delete history.
- Trial Balance, General Ledger, Income Statement and Balance Sheet use the same period and branch filters.
- Dashboard totals are compared only with posted source/GL totals for the same scope.
- Inventory valuation is reconciled with the inventory control account.
- AR/AP aging is reconciled with customer/supplier control accounts by currency.
