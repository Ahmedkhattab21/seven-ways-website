# UAT-DEF-026B — Cash account selector diagnosis

## Result

READY — Active cash posting accounts appear in cash-box selectors and backend validation rejects ineligible accounts.

## Read-only UAT diagnosis

The audit used read-only queries and did not create or update cash boxes, accounts, journals, balances, or historical documents.

The first captured snapshot for account `88 / CASH-ALEX-111` was:

| Field | Value |
| --- | --- |
| `company_id` | `2` |
| `account_code` | `CASH-ALEX-111` |
| `name_ar` | `خزينة فرع الإسكندرية` |
| `is_active` | `true` |
| `is_header` | `false` |
| `is_posting` | `true` |
| `is_cash_account` | `false` |
| `is_bank_account` | `false` |
| `is_control_account` | `true` |
| `requires_branch` | `false` |
| `currency_id` | `null` |
| `deleted_at` | `null` |

The exact exclusion reason in that snapshot was `is_cash_account = false`. It passed the company, active, posting, and non-deleted conditions, but failed the cash-account condition.

During diagnosis, without this patch modifying UAT data, a later read-only snapshot showed the account properties had changed to:

- `is_cash_account = true`
- `is_control_account = false`
- `requires_branch = true`

The current account therefore passes both query stages:

1. Company + active + posting + non-deleted: included.
2. Cash-account condition: included.

The general account `111000 — النقدية` is also an active posting cash account and remains a valid selectable option. It was not hidden by name or hardcoded ID.

## Code correction

- The controller now sends only same-company, active, posting cash accounts, ordered by account code.
- The Blade template only renders the prepared collection and no longer applies business filtering.
- The selector starts with `اختر حساب الخزينة`.
- Changing the branch clears the selected GL account to prevent carrying a stale selection.
- Backend validation independently enforces the same-company, active, posting, cash, and non-deleted rules.
- Invalid selections return: `يجب اختيار حساب نقدية فعال من نوع حساب حركة.`

## Account usage checkbox chain

The existing account form posts explicit boolean values, `AccountRequest` normalizes them, and `ChartOfAccountsService` persists the validated values. The accounting regression tests confirm that usage-rule cards persist and that cash-account business rules remain enforced.

## Regression coverage

- Eligible cash posting accounts appear.
- Non-cash, inactive, header/non-posting, deleted, and other-company accounts do not appear.
- Results are ordered by account code.
- Blade contains no cash-account business query.
- Branch changes clear the current account selection.
- Backend rejects ineligible accounts with the Arabic validation message.
- Rejected requests do not create a cash box.

## Verification

| Command | Result |
| --- | --- |
| `php artisan optimize:clear --env=uat.local` | Passed |
| `php artisan test --filter=Account` | 40 passed |
| `php artisan test --filter=CashBox` | 3 passed |
| `php artisan test --filter=Treasury` | 29 passed |
| `php artisan test --filter=Accounting` | 26 passed |
| Targeted treasury foundation class | 12 passed |
| `php artisan test` | 458 passed, 1 unrelated existing failure |
| Targeted `vendor/bin/pint --test` | Passed for all changed PHP files |
| Global `vendor/bin/pint --test` | Existing style issue in `app/Services/AccountingPostingService.php` |
| `npm.cmd run build` | Passed; existing unresolved static asset warnings |
| `php artisan view:cache` | Passed |
| `git diff --check` | Passed |

The unrelated full-suite failure is `PhaseNineQuotationAppointmentTest::test_calendar_uses_current_month_when_date_filters_are_missing`, where the expected appointment is outside the calendar's current default period. PHP 8.4 also emits existing dependency deprecation warnings.

## Data safety

No production/UAT business data was changed by this patch. No cash box was created, no GL account was edited, and no journal, balance, or historical document was modified.
