# Phase 21 Release Candidate Checklist

| Item | Current value / status |
| --- | --- |
| Current branch | `main` |
| Commit hash | `900eb67` at audit start |
| Working tree | Dirty with Phase 21 changes; no commit requested |
| Suggested tag | `v1.0.0-rc1` after sign-off only |
| PHP | 8.2.12 |
| MariaDB | Expected 10.4.32; runtime unavailable |
| Migration count/status | Blocked by environment |
| Route count | Last verified Phase 20: 515 |
| Full automated count | Last verified Phase 20: 301 passed |
| Phase 21 automated | 4 static/security passed; 10 DB-dependent blocked |
| Manual UAT | Pending |
| Open defects | 1 environment blocker |
| Backup verification | Pending for UAT |
| Restore verification | Pending for UAT |
| Queue verification | Pending for UAT |
| Scheduler verification | Pending for UAT |
| Hosting actions | Pending |
| Rollback readiness | Runbooks exist; UAT drill pending |
| Training readiness | Pending business owner |

## Release gates

- [ ] Phase 21 database-dependent automated tests pass.
- [ ] Full suite passes on port 3307.
- [ ] Manual browser evidence and business-owner sign-off exist.
- [ ] No Blocker/Critical/High defect remains open.
- [ ] Accounting reconciliation differences are zero.
- [ ] Dashboard/report/GL totals match.
- [ ] Cross-company and cross-branch tests pass.
- [ ] Queue worker and scheduler are verified.
- [ ] UAT backup/restore comparison passes.
- [ ] Real hosting TLS/proxy/worker/cron/storage/monitoring checklist is approved.
- [ ] Rollback, production backup, and training are approved.

No commit, tag, push, production credential, deployment, or go-live was created.
