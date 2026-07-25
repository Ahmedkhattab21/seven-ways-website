<?php

return [
    'decimal_scale' => 6,
    'cost_scale' => 4,
    'roll_tolerance' => '0.000001',
    'reservation_reference_types' => [
        'stock_opening', 'stock_adjustment', 'inventory_count', 'manual_inventory', 'stock_transfer', 'work_order', 'rework_order',
        'sales_invoice', 'sales_invoice_item',
    ],
];
