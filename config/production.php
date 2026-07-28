<?php

return [
    'reviewed_migration_operations' => [
        '2026_07_25_141000_remove_soft_deletes_from_stock_transfers' => 'Forward removal of unused soft-delete state; historical data review required on upgrades.',
        '2026_07_25_150000_create_service_catalog_tables' => 'Conditional legacy website-services rename with collision guard.',
        '2026_07_26_070000_create_treasury_foundation_tables' => 'Reviewed constraint replacement required for nullable treasury mappings.',
    ],
];
