# UAT-DEF-008 — Zero cash count

## Result

READY — Empty cash boxes can record an auditable zero cash count without fake denomination lines.

## Fix

Cash count requests now support `zero_count`. Zero counts cannot contain
denomination lines and normal counts cannot omit them. `CashBoxCountService`
computes totals server-side, stores `0.0000`, creates no fake lines, and adds a
clear audit note. The existing session lifecycle and approval rules are
unchanged. The session UI provides a visible zero-count checkbox and disables
denomination fields when selected.

The current UAT session was not deleted or modified, and no balance, journal,
or financial document was changed.

## Verification

- Cash session regression: 4 passed.
- Pint, Blade cache, and `git diff --check`: passed.
- No destructive database command was used.
