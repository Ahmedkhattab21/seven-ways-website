# Phase 19B — Public Website Regression Closure

## 1. Root Cause

The failing `PublicWebsiteTest` and the working-tree website implementation represented
different playback contracts. The page markup had already moved to explicit user playback,
while the website JavaScript still started the active service video programmatically. The old
test also searched the entire response for the word `muted`, which could report unrelated
content instead of validating the `<video>` element itself.

| File | Current behavior before this patch | Test expectation | Conflict |
| ---- | ---------------------------- | ---------------- | -------- |
| `resources/views/website/services.blade.php` | Service videos expose native controls and `playsinline`; no `autoplay` or `muted` attribute | No forced mute | Markup selected manual playback, but JavaScript still called `play()` for the active slide |
| `resources/views/website/about.blade.php` | About video exposes native controls and `playsinline`; no `autoplay` or `muted` attribute | No forced mute | The broad response assertion did not specifically inspect the video tag |
| `resources/js/website/website.js` | Active service slides were played programmatically | User must initiate playback | Programmatic playback contradicted the controls-based contract and could trigger browser autoplay policy |
| `tests/Feature/PublicWebsiteTest.php` | Page-wide text assertion for `muted` | Video-specific playback contract | The assertion was weaker than inspecting every rendered `<video>` opening tag |

The affected pages are `/our-services` and `/about-us`. The videos are content/service
videos, not an autoplay hero. The files are local public assets configured through
`config/website.php`. No Phase 19 dashboard, analytics, reporting, or export code was changed.

## 2. Business Decision

```text
Video behavior: User-initiated playback
Autoplay: No
Muted: No forced mute; audio starts only through explicit video controls
Plays inline: Yes
Controls: Yes
```

This avoids automatic sound, follows the controls already present in the working-tree markup,
and remains compatible with mobile browser playback policy.

## 3. Files Modified

| File | Change | Reason |
| ---- | ------ | ------ |
| `resources/js/website/website.js` | Removed programmatic playback of the active service slide; active videos are reset and inactive videos are paused | Enforce user-initiated playback and prevent automatic sound |
| `tests/Feature/PublicWebsiteTest.php` | Added exact video-tag attribute validation, a regression test against programmatic playback, and configured video asset checks | Test the agreed behavior without a page-wide false positive |
| `docs/phase-19b-public-website-regression-closure.md` | Added root-cause, verification, risk, and readiness evidence | Close the Phase 20 gate |

The current Blade markup was inspected and already matched the decision, so this patch did
not require another Blade edit. Existing concurrent working-tree changes were preserved.

## 4. Tests

All database-backed tests used MariaDB on `127.0.0.1:3307`; port `3306` was not used.
No migration or seeder was required or run.

| Command | Passed | Failed | Notes |
| ------- | -----: | -----: | ----- |
| `php artisan test --filter=PublicWebsiteTest` | 21 | 0 | Both locales, exact video attributes, no programmatic playback, and local assets |
| `php artisan test --filter=PhaseNineteen` | 19 | 0 | Phase 19 regression |
| `php artisan test --filter=PhaseEighteen` | 12 | 0 | Workflow/audit regression |
| `php artisan test --filter=PhaseSeventeen` | 8 | 0 | Employee finance regression |
| `php artisan test --filter=PhaseFifteen` | 31 | 0 | Treasury regression |
| `php artisan test --filter=EgyptLocalization` | 9 | 0 | Egypt localization regression |
| `php artisan test --filter=TreasuryManualQa` | 6 | 0 | Treasury QA regression |
| `php artisan test` | 284 | 0 | Full suite passed |
| `vendor/bin/pint --test` | 1,373 files | 0 | Formatting passed |
| `composer validate` | 1 | 0 | `composer.json` is valid |
| `npm.cmd run build` | 60 modules | 0 | Vite build passed; existing runtime-resolved CSS asset warnings remain |
| `php artisan view:cache` | 1 | 0 | Blade views cached successfully |
| `php artisan route:list` | 513 routes | 0 | Route discovery passed |
| `php artisan schedule:list` | 6 jobs | 0 | Schedule discovery passed |
| `git diff --check` | 1 | 0 | Passed; Git emitted line-ending conversion notices only |

### HTTP smoke test

The temporary server used the clean local database on port `3307` and was stopped after the
check.

| URL | HTTP | Videos | Invalid video tags |
| --- | ---: | -----: | -----------------: |
| `/` | 200 | 0 | 0 |
| `/our-services?lang=ar` | 200 | 4 | 0 |
| `/our-services?lang=en` | 200 | 4 | 0 |
| `/about-us?lang=ar` | 200 | 1 | 0 |
| `/about-us?lang=en` | 200 | 1 | 0 |

All five configured MP4 URLs returned HTTP 200. Every rendered video had `controls` and
`playsinline`, and none had `autoplay` or `muted`. Arabic and English responses both passed.
Static JavaScript regression coverage and the successful Vite build found no website script
error in the changed path.

## 5. Remaining Risks

| Severity | Evidence | Required action | Blocks Phase 20 |
| -------- | -------- | --------------- | --------------- |
| Medium | PDF output remains protected browser print-to-PDF rather than server-side binary generation | Add a reviewed compatible package only if automated PDFs become mandatory | No |
| Medium | Some detailed specialist reports remain in their module pages | Consolidate only after measured business usage | No |
| Low | XLSX export is capped at 5,000 rows | Add queued/chunked export if production volume requires it | No |
| Low | Vite reports existing runtime-resolved website CSS asset references; public assets and smoke checks pass | Recheck assets during deployment | No |
| Low | Analytics has no cache | Profile production-sized data before adding scoped invalidation-aware caching | No |

The previous High website regression is closed. No High or Critical risk remains unresolved.

## 6. Readiness Decision

- [x] `PublicWebsiteTest` passed.
- [x] Phase 19 tests passed.
- [x] Full test suite passed.
- [x] No unresolved High or Critical risk.
- [x] Video never starts sound automatically.
- [x] Public pages and video assets returned HTTP 200.
- [x] Pint, Composer, Vite, view cache, routes, schedule, and diff check passed.

```text
GO — Ready for Phase 20
```

Phase 20 was not started as part of this patch.
