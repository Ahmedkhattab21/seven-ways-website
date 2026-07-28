# Initial Production Onboarding

Run migrations, then `ProductionReferenceSeeder`. It creates reference records only and does
not create a company, branch, user, document, journal, or balance.

Complete onboarding through approved application workflows:

1. Create the Egyptian legal company; country `EG`, currency `EGP`, timezone `Africa/Cairo`.
2. Review configurable taxes, including the 14% reference, without assigning it universally.
3. Create real branches, fiscal year/periods, chart of accounts, posting mappings, banks,
   cash boxes, warehouses, and document sequences.
4. Create the first named administrator through a secure one-time process, then roles and
   least-privilege users. Require password change and MFA at the identity layer if available.
5. Configure products, services, customers, and suppliers.
6. Import reviewed opening balances through the approved opening workflow with independent
   review; never seed them.
7. Perform controlled sample transactions, reconcile journals/reports, then reverse or retain
   them according to the signed UAT script.

Do not run `SevenWaysTenantSeeder`, `SevenWaysOperationalSeeder`, or
`TreasuryManualQaSeeder`; do not create `qa.*` accounts or fixed passwords.

