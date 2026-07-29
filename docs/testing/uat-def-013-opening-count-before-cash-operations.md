# UAT-DEF-013 — Opening count gate

## Result

READY — Cash operations require an approved opening count, and opening counts always use the session's immutable opening balance snapshot.

## Findings and correction

Cash operations previously accepted `opened` sessions and the cash count service used the live cash-box balance for every count. The backend now requires an active `counting` session with an approved `opening` count for boxes configured with `requires_shift_opening`. The guard is checked on create and again before posting; transfers check it on draft creation and processing.

Opening counts use `cash_box_sessions.opening_book_balance`. Interim, surprise, and closing counts continue to use the current book balance.

Approving an opening count moves an opened session to `counting`. No existing receipt, payment, transfer, journal entry, or current balance was changed. The reported UAT balance remains 800; the existing session is not repaired automatically.

## Tests

`UatDef013OpeningCountGateTest` verifies create blocking, the immutable snapshot, approval transition, and operation creation after approval. Existing cash-session and transfer processing tests remain green.

## UAT recovery

The existing session must be handled through the normal UI by recording the historical opening count, submitting it, and completing review/approval. If that is not financially appropriate, close it through a documented administrative process without altering posted history.
