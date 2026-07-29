# UAT-DEF-017 — Multi-item quotation builder

## Result

READY — Quotations support multiple dynamically managed items with server-side automatic pricing, clear item discounts, a clear quotation-level discount, and a trusted pricing preview.

## Changes

- The form starts with one item and can add or remove item cards without refreshing.
- Removing a populated item asks for confirmation; indices and field names are rebuilt after removal.
- Service, package, product, and custom fields are shown and submitted only for their selected type.
- `QuotationRequest` rejects conflicting references, browser totals, and unauthorized manual pricing.
- Automatic prices and preview totals come from `QuotationPricingService`; the preview performs no writes and consumes no document sequence.
- Each item shows catalog price, used price, source, discount, net, tax, duration, warnings, and total.
- Item discounts apply to one item. The quotation discount applies after all item discounts.
- Cost and margin appear only with `quotations.view_cost`.
- Saving recalculates on the server and stores the final snapshot as a draft. The UI disables duplicate submission.

## Data safety

No UAT quotation, customer, vehicle, catalog price, journal, inventory movement, or historical document was created or changed while implementing this patch.

## Verification

Focused quotation, pricing, and service-pricing tests cover server-owned totals, permissions, conflicting references, discount limits, preview read-only behavior, old multi-item input, and multi-item calculations.
