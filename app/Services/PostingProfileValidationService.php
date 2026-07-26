<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\PostingProfile;

class PostingProfileValidationService
{
    public const SOURCE_TYPES = [
        'sales_invoice', 'sales_credit_note', 'customer_payment', 'customer_refund',
        'supplier_invoice', 'supplier_credit_note', 'supplier_payment', 'purchase_receipt',
        'purchase_return', 'inventory_adjustment', 'stock_transfer', 'work_order_consumption', 'manual',
    ];

    public const ACCOUNT_SOURCES = [
        'fixed_account', 'branch_mapping', 'customer_control', 'supplier_control',
        'product_inventory', 'product_cogs', 'tax_input', 'tax_output', 'payment_method_account',
    ];

    public const AMOUNT_SOURCES = [
        'subtotal', 'net_amount', 'tax_amount', 'discount_amount', 'total', 'cost_amount',
        'paid_amount', 'refund_amount', 'rounding_amount',
    ];

    public function assertActivatable(PostingProfile $profile): void
    {
        $profile->load('lines');
        if (! $profile->lines->contains('entry_side', 'debit') || ! $profile->lines->contains('entry_side', 'credit')) {
            throw new BusinessRuleException('Posting profile requires debit and credit lines.');
        }
        foreach ($profile->lines as $line) {
            if (! in_array($line->account_source, self::ACCOUNT_SOURCES, true)
                || ! in_array($line->amount_source, self::AMOUNT_SOURCES, true)) {
                throw new BusinessRuleException('Posting profile contains an unsupported source.');
            }
            if ($line->account_source === 'fixed_account'
                && (! $line->fixedAccount || ! $line->fixedAccount->is_active || ! $line->fixedAccount->is_posting
                    || $line->fixedAccount->company_id !== $profile->company_id)) {
                throw new BusinessRuleException('Fixed account must be active, posting, and in the same company.');
            }
        }
    }
}
