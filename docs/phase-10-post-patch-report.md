# Phase 10 Post-Patch Review

## Shell artifact

- Neither `all())` nor `all())all())` exists now.
- The earlier file was an untracked root-level redirect artifact named `all())`.
- Its content was PsySH/Tinker warning output (`Unexpected end of input`) from a malformed PowerShell-quoted `artisan tinker --execute` command. PowerShell interpreted part of the broken expression as output redirection and used the trailing token as the filename.
- It was not imported, included, referenced by a command, or tracked by Git, and was deleted.
- Recursive filename/content checks found no remaining malformed filename or equivalent warning-output artifact. Legitimate PHP calls ending in `->all()` and `count()` were left untouched.

## Artifact review

`git status --short`, `git diff --name-status`, and `git ls-files` were inspected.

- No `.env`, database dump, log, `public/build`, temporary command output, malformed filename, or test-result file is present in Git changes.
- `.env`, `public/build`, and runtime logs are ignored by existing rules.
- The only tracked storage log path is `storage/logs/.gitignore`.
- Vite output exists only as ignored build output after the requested build.

## Inventory and cost review

### Product issue

- `WorkOrderMaterialIssueService` locks the material row.
- Issue is allowed only from `reserved`, consumes the reservation once, and then changes the line to `issued`.
- A repeated call is rejected before another `work_order_issue` movement can be created.

### Roll consumption

- `WorkOrderRollConsumptionService` still delegates physical consumption to the existing `RollConsumptionService`.
- It does not create a second roll or stock movement.
- The work-order material row is locked and leaves `reserved` after the first consumption, blocking replay.

### Scrap consumption

- `WorkOrderScrapConsumptionService` delegates to the existing `RollScrapService`.
- Both the work-order line and scrap become `consumed`; replay is rejected and no second `roll_scrap_consumed` movement is created.

### Waste cost

The review found and fixed a real double-counting risk:

- Before: `WorkOrderCostService` added `work_order_materials.waste_cost` and `work_order_waste_records.total_cost`.
- After: `work_order_waste_records` is the authoritative aggregate source for actual waste cost. Material/roll `waste_cost` remains a line-level snapshot and is not summed a second time.
- Product and roll consumption automatically create one linked waste record when waste is positive.
- Identical manual waste records are rejected.
- Order-level waste records are now included even when no service line is assigned.

## Forward migration

`2026_07_25_171000_link_work_order_waste_to_materials.php` adds:

- Nullable `work_order_material_id`.
- Restrictive foreign key to `work_order_materials`.
- Unique index preventing a material consumption from creating duplicate authoritative waste records.

Existing waste rows are preserved with `NULL`; no backfill or destructive operation is needed. The migration was applied to development and testing. Its rollback and re-apply were both verified on the testing database.

## State review

- `awaiting_quality` is reached only when no service remains outside `completed` or `cancelled`.
- Cancelled services do not block quality handoff.
- `rework_required` blocks quality handoff.
- New service/material rows are rejected after `awaiting_quality` or later terminal states.
- Reopen is transactional, changes the service to `rework_required`, returns the order to `in_progress`, clears quality/finish timestamps, and allows the service to start again.
- Reopen is rejected for delivery, delivered, cancelled, or closed orders.
- Cancelling a settled issued-material order preserves its material and stock-movement history.

## Attachment review

- Inspection photos use the private `local` disk under `private/attachments/{company}`.
- Stored filename is generated as UUID plus detected extension.
- Stored path and filename are never accepted from the request.
- Original names are reduced to `basename`.
- Authorization uses the morph target and branch, not the category string.
- Cross-branch download is denied.
- Inspection attachment deletion requires `vehicle_inspections.manage_photos`, not view-only access.
- `customer_signature_path` and other unknown request fields are excluded by validated input, so Base64 signatures are not stored in the database.

## Added coverage

Phase 10 coverage increased from 8 to 14 tests, adding:

- Repeated quantity issue does not deduct stock twice.
- Roll movement and waste cost are recorded once.
- Scrap cannot be consumed twice.
- All non-cancelled services must complete.
- Cancelled service does not block quality; `rework_required` does.
- Quality handoff locks new services/materials and Reopen restores execution.
- Cross-branch inspection-photo download returns `403`.
- Cancellation preserves issued-material history.

## Verification

- `php artisan test`: passed — **107 tests**.
- `vendor/bin/pint --test`: passed — **442 files**.
- `composer validate`: passed.
- `npm.cmd run build`: passed — Vite 4.5.14, 58 modules.
- `php artisan route:list`: passed — **191 routes**.
- `php artisan view:cache`: passed.
- `git diff --check`: passed.
- `git status --short`: only Phase 10 and this limited post-patch remain; no artifacts.

PHP 8.4 vendor deprecation warnings remain from Laravel 9 dependencies. No Laravel or dependency upgrade was performed.

## Files changed by this limited patch

- `app/Http/Controllers/WorkOrderMaterialController.php`
- `app/Models/WorkOrderMaterial.php`
- `app/Models/WorkOrderService.php`
- `app/Models/WorkOrderWasteRecord.php`
- `app/Policies/AttachmentPolicy.php`
- `app/Services/WorkOrderCostService.php`
- `app/Services/WorkOrderMaterialIssueService.php`
- `app/Services/WorkOrderRollConsumptionService.php`
- `app/Services/WorkOrderScrapConsumptionService.php`
- `app/Services/WorkOrderServiceActionService.php`
- `app/Services/WorkOrderWasteService.php`
- `database/migrations/2026_07_25_171000_link_work_order_waste_to_materials.php`
- `tests/Feature/PhaseTenWorkOrderExecutionTest.php`
- `docs/phase-10-post-patch-report.md`
