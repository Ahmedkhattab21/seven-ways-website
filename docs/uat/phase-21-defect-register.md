# Phase 21 Defect Register

## Severity definitions

- **Blocker:** a principal cycle cannot complete, data corruption, cross-tenant leak, unbalanced/duplicate posting, or backup/restore failure.
- **Critical:** material financial/security error, incorrect valuation, or data/attachment loss.
- **High:** incorrect principal-cycle result, approval bypass, report mismatch, branch isolation failure, or incorrect allocation.
- **Medium:** secondary function fails and a safe workaround exists.
- **Low/Cosmetic:** wording, alignment, styling, or minor usability.

## Open records

### UAT-ENV-001

| Field | Value |
| --- | --- |
| Severity | Blocker |
| Module | UAT Environment |
| Environment | `127.0.0.1:3307 / seven_ways_uat` |
| Account | N/A |
| Branch | N/A |
| Preconditions | MariaDB 10.4 isolated service must be running |
| Steps | Connect with the MariaDB client to port 3307 |
| Expected | Server responds and target validation can inspect/create `seven_ways_uat` |
| Actual | Connection refused: error 10061 |
| Evidence | Safe TCP connection attempt on 2026-07-28 |
| Root cause | MariaDB process on port 3307 is stopped; data files were not touched |
| Fix | Environment owner starts/repairs the approved isolated service after backup |
| Regression test | Run `php artisan uat:validate-target --env=uat.local` |
| Retest result | Pending |
| Status | Open — environment, not an application-code defect |

## Product defects

No new product defect was confirmed because database-dependent automated E2E, real HTTP smoke, manual browser UAT, performance, queue/scheduler, and backup/restore execution could not start. New findings must be recorded before any fix and cannot be closed without a regression test and retest evidence.
