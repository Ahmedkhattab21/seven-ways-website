<?php

return [
    'separation_of_duties' => env('PURCHASING_SEPARATION_OF_DUTIES', true),
    'purchase_order_approval_required' => env('PURCHASE_ORDER_APPROVAL_REQUIRED', true),
    'price_variance_percentage' => env('PURCHASE_PRICE_VARIANCE_PERCENTAGE', 10),
    'quantity_tolerance_percentage' => env('PURCHASE_QUANTITY_TOLERANCE_PERCENTAGE', 0),
    'over_receipt_allowed' => env('PURCHASE_OVER_RECEIPT_ALLOWED', false),
    'goods_receipt_inspection_required' => env('GOODS_RECEIPT_INSPECTION_REQUIRED', false),
    'supplier_invoice_matching_required' => env('SUPPLIER_INVOICE_MATCHING_REQUIRED', true),
    'free_quantity_cost_policy' => 'distribute_line_cost',
];
