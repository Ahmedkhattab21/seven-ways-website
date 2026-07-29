<?php

return [
    'sales' => ['enabled' => true],
    'purchasing' => ['enabled' => true],
    'basic_inventory' => ['enabled' => true],
    'accounting' => ['enabled' => true],
    'treasury' => ['enabled' => true],

    'leads' => [
        'enabled' => false,
        'routes' => ['leads.*', 'lead-attachments.*'],
    ],
    'appointments' => [
        'enabled' => false,
        'routes' => ['appointments.*', 'quotations.appointment', 'appointment-deposits.*'],
    ],
    'work_orders' => [
        'enabled' => false,
        'routes' => ['work-orders.*', 'work-order-*', 'work-order-materials.*'],
    ],
    'technicians' => [
        'enabled' => false,
        'routes' => ['employees.*', 'employee-finance.*'],
    ],
    'quality' => [
        'enabled' => false,
        'routes' => ['vehicle-inspections.*', 'quality-checks.*', 'quality-templates.*'],
    ],
    'rework' => [
        'enabled' => false,
        'routes' => ['rework-orders.*', 'rework-materials.*'],
    ],
    'delivery' => [
        'enabled' => false,
        'routes' => ['deliveries.*'],
    ],
    'warranties' => [
        'enabled' => false,
        'routes' => ['warranties.*', 'warranty-claims.*', 'warranty.verify'],
    ],
    'advanced_roll_inventory' => [
        'enabled' => false,
        'routes' => ['rolls.*', 'roll-*', 'scraps.*'],
        'inventory_sections' => ['rolls', 'scraps', 'reservations'],
    ],
];
