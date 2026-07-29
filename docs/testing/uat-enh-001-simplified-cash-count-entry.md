# UAT-ENH-001 — Simplified cash count entry

## Result

READY — Cashiers confirm total physical cash as matching, different, or empty without entering denominations or quantities.

## New flow

The count form now offers `match_book`, `manual_total`, and `empty`. The server calculates `counted_total`, `book_total`, and `difference`; only manual mode accepts a positive total (minimum 0.01). Denomination lines are prohibited by the new request and are not created for new counts. Existing count lines and legacy records remain readable and auditable.

Opening counts use the session's immutable `opening_book_balance` snapshot. Interim, surprise, and closing counts use the current cash-box book balance. The existing count lifecycle and session lifecycle are unchanged; opening approval still moves an opened session to counting.

## Data safety

No existing counts, count lines, sessions, balances, journal entries, or posted documents were deleted or edited. A nullable `count_input_mode` migration is forward-only; legacy databases without the column remain compatible during rollout.

## Verification

`UatEnh001SimplifiedCashCountTest` covers all three modes, server-side totals, no new lines, and rejection of zero manual totals. Existing cash-session regression tests remain green. Pint, view cache, Vite build, and `git diff --check` were run.
