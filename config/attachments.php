<?php

return [
    'max_kb' => (int) env('ATTACHMENT_MAX_KB', 10240),
    'mimetypes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ],
    'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
];
