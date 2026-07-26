<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\JournalEntry;

class JournalEntryValidationService
{
    public function assertPostable(JournalEntry $entry, bool $allowControlAccounts = false): void
    {
        $entry->loadMissing('lines.account');
        if ($entry->lines->count() < 2) {
            throw new BusinessRuleException('A journal entry needs at least two lines.');
        }
        $debit = '0.0000';
        $credit = '0.0000';
        foreach ($entry->lines as $line) {
            if (bccomp($line->debit_amount, '0', 4) > 0 === bccomp($line->credit_amount, '0', 4) > 0) {
                throw new BusinessRuleException('Each journal line must contain debit or credit, never both.');
            }
            $this->assertAccount($line->account, $line->toArray(), $entry->company_id, $allowControlAccounts || $entry->is_automatic);
            $debit = bcadd($debit, $line->base_debit_amount, 4);
            $credit = bcadd($credit, $line->base_credit_amount, 4);
        }
        if (bccomp($debit, $credit, 4) !== 0 || bccomp($debit, '0', 4) <= 0) {
            throw new BusinessRuleException('Journal entry debits and credits must be equal and positive.');
        }
    }

    public function assertAccount(Account $account, array $line, int $companyId, bool $allowControlAccounts): void
    {
        if ($account->company_id !== $companyId || ! $account->is_active || ! $account->is_posting || $account->is_header) {
            throw new BusinessRuleException('Journal account is unavailable for posting.');
        }
        if (! $allowControlAccounts && ($account->is_control_account || ! $account->allow_manual_entry)) {
            throw new BusinessRuleException('Manual posting to this control account is not allowed.');
        }
        foreach (['branch', 'cost_center', 'customer', 'supplier', 'employee', 'vehicle'] as $dimension) {
            if ($account->{'requires_'.$dimension} && empty($line[$dimension.'_id'])) {
                throw new BusinessRuleException("Account requires {$dimension} dimension.");
            }
        }
    }
}
