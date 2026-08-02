<?php

return [
    'enabled' => (bool) env('SEVENWAYS_PRODUCTION_BOOTSTRAP', false),
    'company_id' => env('SEVENWAYS_COMPANY_ID'),
    'users' => [
        'nasr_manager' => [
            'name' => 'مسؤول فرع مدينة نصر',
            'email' => env('SEVENWAYS_NASR_MANAGER_EMAIL'),
            'password' => env('SEVENWAYS_NASR_MANAGER_PASSWORD'),
            'role' => 'branch_manager',
            'branch' => 'CAI-MAIN',
        ],
        'alex_manager' => [
            'name' => 'مسؤول فرع الإسكندرية',
            'email' => env('SEVENWAYS_ALEX_MANAGER_EMAIL'),
            'password' => env('SEVENWAYS_ALEX_MANAGER_PASSWORD'),
            'role' => 'branch_manager',
            'branch' => 'ALEX',
        ],
        'accountant' => [
            'name' => 'محاسب Seven Ways',
            'email' => env('SEVENWAYS_ACCOUNTANT_EMAIL'),
            'password' => env('SEVENWAYS_ACCOUNTANT_PASSWORD'),
            'role' => 'accountant',
            'branch' => 'CAI-MAIN',
        ],
        'general_manager' => [
            'name' => 'المدير العام Seven Ways',
            'email' => env('SEVENWAYS_GENERAL_MANAGER_EMAIL'),
            'password' => env('SEVENWAYS_GENERAL_MANAGER_PASSWORD'),
            'role' => 'general_manager',
            'branch' => 'CAI-MAIN',
        ],
    ],
];
