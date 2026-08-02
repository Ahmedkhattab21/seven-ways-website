<?php

namespace App\Support;

final class DocumentSequenceCatalog
{
    /** @return array<string, array{short_code: string, reset_period: string}> */
    public static function production(): array
    {
        return [
            'customer' => ['short_code' => 'CUS', 'reset_period' => 'never'],
            'quotation' => ['short_code' => 'QT', 'reset_period' => 'yearly'],
            'sales_invoice' => ['short_code' => 'INV', 'reset_period' => 'yearly'],
            'sales_credit_note' => ['short_code' => 'SCN', 'reset_period' => 'yearly'],
            'customer_payment' => ['short_code' => 'CPR', 'reset_period' => 'yearly'],
            'customer_refund' => ['short_code' => 'CRF', 'reset_period' => 'yearly'],
            'supplier' => ['short_code' => 'SUP', 'reset_period' => 'never'],
            'purchase_requisition' => ['short_code' => 'PR', 'reset_period' => 'yearly'],
            'purchase_order' => ['short_code' => 'PO', 'reset_period' => 'yearly'],
            'goods_receipt' => ['short_code' => 'GRN', 'reset_period' => 'yearly'],
            'supplier_invoice' => ['short_code' => 'PINV', 'reset_period' => 'yearly'],
            'supplier_payment' => ['short_code' => 'SP', 'reset_period' => 'yearly'],
            'purchase_return' => ['short_code' => 'PRTN', 'reset_period' => 'yearly'],
            'supplier_credit_note' => ['short_code' => 'SUPCN', 'reset_period' => 'yearly'],
            'stock_opening' => ['short_code' => 'SO', 'reset_period' => 'yearly'],
            'stock_movement' => ['short_code' => 'SM', 'reset_period' => 'yearly'],
            'inventory_count' => ['short_code' => 'IC', 'reset_period' => 'yearly'],
            'stock_adjustment' => ['short_code' => 'SA', 'reset_period' => 'yearly'],
            'stock_transfer' => ['short_code' => 'ST', 'reset_period' => 'yearly'],
            'cash_box_session' => ['short_code' => 'CS', 'reset_period' => 'yearly'],
            'cash_receipt' => ['short_code' => 'CR', 'reset_period' => 'yearly'],
            'cash_payment' => ['short_code' => 'CPAY', 'reset_period' => 'yearly'],
            'treasury_transfer' => ['short_code' => 'TT', 'reset_period' => 'yearly'],
            'receipt_voucher' => ['short_code' => 'RV', 'reset_period' => 'yearly'],
            'payment_voucher' => ['short_code' => 'PV', 'reset_period' => 'yearly'],
            'cheque_received' => ['short_code' => 'CHQR', 'reset_period' => 'yearly'],
            'cheque_issued' => ['short_code' => 'CHQI', 'reset_period' => 'yearly'],
            'merchant_settlement' => ['short_code' => 'MS', 'reset_period' => 'yearly'],
            'bank_adjustment' => ['short_code' => 'BADJ', 'reset_period' => 'yearly'],
            'bank_reconciliation' => ['short_code' => 'BREC', 'reset_period' => 'yearly'],
            'journal_entry' => ['short_code' => 'JE', 'reset_period' => 'yearly'],
            'opening_balance' => ['short_code' => 'OB', 'reset_period' => 'yearly'],
            'accounting_closing_run' => ['short_code' => 'ACR', 'reset_period' => 'yearly'],
        ];
    }

    /** @return list<string> */
    public static function codeConfiguredTypes(): array
    {
        return array_keys(config('document_sequences.types', []));
    }
}
