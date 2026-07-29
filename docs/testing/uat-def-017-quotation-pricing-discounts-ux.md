# UAT-DEF-017 — Quotation automatic pricing and clear discount UX

## Result

READY — Quotation items display authoritative automatic prices, clearly separate item and total discounts, and calculate a server-side preview before saving.

## Pricing behavior

- The catalog/service price is resolved by `QuotationPricingService`, which delegates service prices to `ServicePricingService::resolvePrice()`.
- Manual price is an explicit override, not the default. Its control is rendered only for `quotations.manual_price`; forged requests without that permission return `403`.
- The preview and final save call the same `QuotationPricingService::calculate()` implementation.
- Preview is JSON-only and creates no quotation, sequence number, snapshot, audit record, or database write.
- Preview responses exclude material costs and margins. Minimum price is returned only to users allowed to override it.

## Discounts and tax

1. Base unit price × quantity.
2. Item discount.
3. Sum of item net amounts.
4. Additional quotation discount.
5. Tax on the net amount after both discount layers.
6. Final total.

The item discount applies to one line. The additional quotation discount applies after all item discounts. Negative values, percentages over 100%, and discounts exceeding their base are rejected by backend validation/pricing rules.

## UX and validation

- Service, package, product, and custom rows show only their relevant selector; hidden references are disabled and cleared.
- Custom rows require a description and manual price.
- Rows can be added/removed while preserving at least one row and reindexing request names.
- Server preview shows loading, Arabic error feedback, authoritative line totals, tax, currency, and quotation summary.
- Company, branch, customer/vehicle, active catalog, sellable product, and branch availability rules remain backend-enforced.
- The final save recalculates all figures and does not trust browser totals.

## Automated verification

- `UatDef017QuotationPricingUxTest`: 8 tests passed.
- Covers conditional UI markers, permission enforcement, authoritative service price, no preview writes/sequence consumption, discount order, tax after discounts, invalid discounts, manual override, stale IDs, and old-input retention.
- Existing `UatDef016QuotationCreateUxTest`: 7 tests passed after integrating the stricter manual-price rule.

## Data safety

No UAT quotation was created. No customer, vehicle, stored service price, sequence counter, or historical quotation data was modified by this patch.
