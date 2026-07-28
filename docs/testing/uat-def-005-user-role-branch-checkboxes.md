# UAT-DEF-005 — User role and branch selections

## Result

READY — Branch and role selections are visible, accessible and submitted correctly.

## Root cause and fix

The form hid checkbox inputs but rendered no adjacent visual checkbox element;
the existing CSS expected `.sw-check__box`. The form now renders labelled,
keyboard-accessible checkbox cards with visible focus/checked/hover states,
responsive grid layout, old-input preservation, and default-branch
auto-selection. Validation remains authoritative in the backend.

Roles are deduplicated by technical name, preferring the current company role
over a system role. No roles, permissions, or users were deleted or created
automatically.

## Verification

- `TenantFoundationTest`: 12 passed.
- Pint: passed.
- Vite build: passed (existing unresolved static website asset warnings only).
- Blade cache and `git diff --check`: passed.
