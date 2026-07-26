<?php

namespace App\Services;

class FinancialReportValidationService
{
    public function validate(array $trialBalance, array $balanceSheet, array $cashFlow, array $reconciliations = []): array
    {
        $errors = [];
        $warnings = [];
        if (! $trialBalance['balanced']) {
            $errors[] = 'Trial balance is not balanced.';
        }
        if (! $balanceSheet['balanced']) {
            $errors[] = 'Balance sheet equation is not balanced.';
        }
        if (! $cashFlow['reconciled']) {
            $errors[] = 'Cash movement does not reconcile.';
        }
        if ($cashFlow['warning']) {
            $warnings[] = $cashFlow['warning'];
        }
        foreach ($reconciliations as $reconciliation) {
            if (! $reconciliation['balanced']) {
                $warnings[] = ucfirst($reconciliation['type']).' control account has a difference.';
            }
        }

        return ['status' => $errors === [] ? ($warnings === [] ? 'valid' : 'warning') : 'invalid', 'warnings' => $warnings, 'errors' => $errors];
    }
}
