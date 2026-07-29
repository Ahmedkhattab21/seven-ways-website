# INVOICE-WARRANTY-002 — Embedded warranty and WhatsApp sharing

## Result

READY — The sales invoice is the single financial and warranty customer document. No warranty certificate, warranty number, or dependency on the legacy warranty module was added.

## Data model

- Products, services, and service packages now hold configurable warranty defaults.
- Every invoice item freezes an immutable `warranty_snapshot` with applicability, film details, application area, start, duration, calculated end date, terms, notes, and package component warranties.
- Existing `warranty_months` and `default_warranty_months` values are forward-mapped into the new defaults.
- Issued invoice snapshots are not rebuilt from later catalog changes.

## Print

The printable invoice contains company and branch details, customer and optional vehicle details, financial lines and totals, and a warranty section only when a line is covered. Egypt and Saudi Arabia flags are local SVG assets and default to visible through `company.invoice_print_settings`.

Cancelled or void invoices retain the snapshot and display the warranty as not valid.

## WhatsApp

- Sharing requires `sales_invoices.share`, tenant and branch policy scope, and a valid normalized customer phone.
- The generated URL is temporary, signed, read-only, and resolves one invoice through an opaque share UUID.
- `invoice_shares` records generation, opening, expiry, failure, and sent timestamps.
- Opening WhatsApp creates a `generated` log only. It is never recorded as sent without external delivery evidence.

## Permissions

- Branch managers: own-branch print and share.
- Accountants: print, share, and view share logs.
- General managers and owners: all invoice permissions.
- Warranty data is accepted while creating a draft. The current application has no issued-invoice item edit path, so snapshots cannot be changed after issue.

## Safety

- No legacy warranty route, model, certificate, or numbering workflow is used.
- No appointment, work-order, technician, quality, or delivery state is required for a direct invoice.
- No external flag or image request is made.

## Verification

- `InvoiceWarrantyTest`: 6 passed.
- `PhaseTwelveSalesReceivablesTest`: 9 passed.
- UAT migration and idempotent sales-receivables permission seeder: applied successfully.
- Full suite: 449 passed, 1 unrelated existing calendar assertion failed because the generated appointment was outside the current-month filter.
- Blade view cache, Vite production build, and `git diff --check`: passed.
- Pint: invoice warranty changes pass; the full scan still reports the pre-existing style issue in `AccountingPostingService.php`.
- Manual WhatsApp delivery was not marked as sent or simulated; real delivery confirmation remains an external UAT step.
