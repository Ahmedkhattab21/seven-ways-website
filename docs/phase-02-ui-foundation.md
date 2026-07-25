# Phase 02 — Seven Ways UI Foundation

## Frontend before implementation

The application used Blade with a single stock Laravel welcome view. That view contained
a large inline CSS block and the Nunito font. `resources/css/app.css` was empty and
`resources/js/app.js` only loaded Axios/bootstrap helpers. There was no CSS framework,
Vue, React, Livewire, UI library, login screen, web authentication flow, dashboard,
layout, sidebar, topbar, RTL support, or project logo asset.

## Implemented technology and decisions

- Laravel Blade components and layouts.
- Vanilla CSS compiled by the existing Vite pipeline; no new frontend dependency.
- Small framework-free JavaScript behaviors for the sidebar, mobile drawer, dropdown,
  password visibility, loading state, and modal foundation.
- Existing Laravel `web` session guard for login/logout; Sanctum and API routes unchanged.
- Arabic-first markup with RTL by default and direction derived from the application
  locale so future English/LTR can reuse the layouts.
- Cairo from Google Fonts, with system Arabic font fallbacks.
- Text-based Seven Ways `7W` brand mark because no approved logo asset existed.

## Design tokens

All visual values are centralized in `resources/css/app.css`. Core colors:

- Primary `#E10600`, hover `#B90500`.
- Background `#090909`, sidebar `#101010`.
- Surface `#181818`, elevated surface `#202020`, border `#2C2C2C`.
- Main text `#FFFFFF`, secondary `#B3B3B3`, muted `#7A7A7A`.
- Success `#22C55E`, warning `#F59E0B`, danger `#EF4444`, info `#3B82F6`.

The same token layer defines spacing, radii, shadows, font sizes/weights, transitions,
sidebar widths, topbar height, container width, and z-index levels. Page templates do
not repeat color hex values or contain inline style blocks.

## UI delivered

- Responsive split login screen with validation, throttled authentication, remember-me,
  show/hide password, submit loading state, and safe invalid-credential feedback.
- Authenticated administration layout with collapsible desktop sidebar and mobile drawer.
- Sticky topbar with page identity, disabled branch selector, disabled notifications,
  authenticated user initials/menu, and secure POST logout.
- Disabled future-module navigation labels; none link to fake routes.
- Presentation-only dashboard statistics, chart, activity, alerts, and quick actions.
  Values use zero/dash/empty states and never query commercial data.
- Reusable buttons, inputs, select, textarea, checkbox, radio, switch, cards, statistics,
  status badges, alerts, empty state, spinner, skeleton, modal, and table shell.
- Branded 403, 404, 419, 500, and 503 views with no technical details.

## Routes

| Method | Route | Middleware | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `web` | Redirect to login or dashboard |
| GET | `/login` | `web`, `guest` | Login screen |
| POST | `/login` | `web`, `guest` | Session authentication |
| GET | `/dashboard` | `web`, `auth` | UI-only dashboard |
| POST | `/logout` | `web`, `auth` | End session |

No commercial API or route was added.

## Verification

Visual QA used Chrome at `1440×1000` and `390×844`:

- Login and dashboard rendered RTL with no global horizontal overflow.
- Login converted from split layout to a single mobile form.
- Dashboard statistics converted from four columns to one.
- Mobile sidebar started off-canvas, opened as a `280px` drawer, displayed a backdrop,
  and exposed a working close control.
- Future module items remained visibly disabled.

Laptop `1280×800` and tablet `768×1024` were also checked. Neither produced global
horizontal overflow; statistics rendered as four columns on laptop and two on tablet,
and tablet navigation correctly remained off-canvas until opened.

Feature coverage includes guest login visibility, dashboard authentication, authenticated
layout access, logout, and safe server-error output.

Command results:

- `php artisan test`: 15 tests passed.
- `composer validate --no-interaction`: valid.
- `npm run build`: 58 modules transformed successfully.
- `php artisan route:list`: 12 routes; login/dashboard/logout middleware verified.
- Targeted Pint for all phase PHP files: passed.
- Full `vendor/bin/pint --test`: four pre-existing style failures remain in
  `app/Console/Kernel.php`, `UserController.php`, `WelcomeController.php`, and
  `RedirectIfAuthenticated.php`.
- PHP 8.4 still emits third-party deprecation warnings; PHP 8.2 remains recommended.

## Deferred

- Approved production logo files.
- Password reset, registration, user management, final roles/permissions.
- Company/branch persistence and branch-selection logic.
- Real notifications, charts, activities, quick actions, and commercial module links.
- Full English translations; the layout direction is prepared for LTR.

No company, branch, customer, vehicle, product, inventory, sales, work-order, invoice,
accounting, supplier, ZATCA, or other commercial module was implemented.
