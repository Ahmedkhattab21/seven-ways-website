<?php

return [
    'locales' => ['ar', 'en'],

    'defaults' => [
        'country_code' => 'EG',
        'currency_code' => 'EGP',
        'timezone' => 'Africa/Cairo',
        'locale' => 'ar_EG',
    ],

    'contact' => [
        'recipient' => env('WEBSITE_CONTACT_EMAIL') ?: env('MAIL_FROM_ADDRESS'),
    ],

    'socials' => [
        'instagram' => 'https://www.instagram.com/7sevenways',
        'tiktok' => 'https://www.tiktok.com/@7sevenways?_t=ZS-8y8bGfLUQSW&_r=1',
        'facebook' => 'https://www.facebook.com/share/1AfhixGZwR/?mibextid=wwXIfr',
        'xpel' => 'https://www.xpel.com',
    ],

    'footer_socials' => [
        'saudi_arabia' => [
            'instagram' => 'https://www.instagram.com/7sevenways',
            'tiktok' => 'https://www.tiktok.com/@7sevenways?_t=ZS-8y8bGfLUQSW&_r=1',
            'facebook' => 'https://www.facebook.com/share/1AfhixGZwR/?mibextid=wwXIfr',
        ],
        'egypt' => [
            'instagram' => 'https://www.instagram.com/sevenwayseg?igsh=MW92M2RzYTQ1ZnY0Mw==',
            'tiktok' => 'https://www.tiktok.com/@sevenwayeseg?_t=ZS-8y8a6wRrVUA&_r=1',
            'facebook' => 'https://www.facebook.com/profile.php?id=61577794306595&mibextid=ZbWKwL',
        ],
    ],

    'branches' => [
        [
            'id' => 'riyadh',
            'country_code' => 'saudi_arabia',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'فرع الرياض', 'en' => 'Riyadh Branch'],
            'address' => [
                'ar' => 'القادسية - معارض السيارات',
                'en' => 'Al Qadisiyah - Car Showrooms',
            ],
            'phone' => '+966534899166',
            'whatsapp' => 'https://wa.me/966534899166',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=Seven+Ways+Riyadh',
            'map_embed' => 'https://www.google.com/maps?q=Seven+Ways+Riyadh&output=embed',
        ],
        [
            'id' => 'jazan',
            'country_code' => 'saudi_arabia',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'فرع جيزان', 'en' => 'Jazan Branch'],
            'address' => [
                'ar' => 'صبيا - طريق الملك فهد',
                'en' => 'Sabya - King Fahd Road',
            ],
            'phone' => '+966504118823',
            'whatsapp' => 'https://wa.me/966504118823',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=Seven+Ways+Sabya',
            'map_embed' => 'https://www.google.com/maps?q=Seven+Ways+Sabya&output=embed',
        ],
        [
            'id' => 'khamis-mushait',
            'country_code' => 'saudi_arabia',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'فرع خميس مشيط', 'en' => 'Khamis Mushait Branch'],
            'address' => [
                'ar' => 'معارض السيارات',
                'en' => 'Car Showrooms',
            ],
            'phone' => '+966534899166',
            'whatsapp' => 'https://wa.me/966534899166',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=Seven+Ways+Khamis+Mushait',
            'map_embed' => 'https://www.google.com/maps?q=Seven+Ways+Khamis+Mushait&output=embed',
        ],
        [
            'id' => 'dammam',
            'country_code' => 'saudi_arabia',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'فرع الدمام', 'en' => 'Dammam Branch'],
            'address' => [
                'ar' => 'الدمام، المملكة العربية السعودية',
                'en' => 'Dammam, Saudi Arabia',
            ],
            'phone' => '+966504118823',
            'whatsapp' => 'https://wa.me/966504118823',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=Seven+Ways+Dammam',
            'map_embed' => 'https://www.google.com/maps?q=Seven+Ways+Dammam&output=embed',
        ],
        [
            'id' => 'jeddah',
            'country_code' => 'saudi_arabia',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'فرع جدة', 'en' => 'Jeddah Branch'],
            'address' => [
                'ar' => 'جدة، المملكة العربية السعودية',
                'en' => 'Jeddah, Saudi Arabia',
            ],
            'phone' => '+966534899166',
            'whatsapp' => 'https://wa.me/966534899166',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=Seven+Ways+Jeddah',
            'map_embed' => 'https://www.google.com/maps?q=Seven+Ways+Jeddah&output=embed',
        ],
        [
            'id' => 'nasr-city',
            'country_code' => 'egypt',
            'country' => ['ar' => 'مصر', 'en' => 'Egypt'],
            'name' => ['ar' => 'مدينة نصر', 'en' => 'Nasr City'],
            'address' => [
                'ar' => 'محطة بنزين وطنية - بجوار مسجد السلام، الوفاء والأمل، مدينة نصر',
                'en' => 'Wataniya Gas Station - Next to Al Salam Mosque, Wafaa and Amal, Nasr City',
            ],
            'phone' => '+201099025564',
            'whatsapp' => 'https://wa.me/201099025564',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=Seven+Ways+Nasr+City',
            'map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3453.6884222212807!2d31.358420824582655!3d30.045795518506964!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14583d002493452f%3A0xe7f18626b13860ec!2sSeven%20Ways!5e0!3m2!1sar!2seg!4v1754321669152!5m2!1sar!2seg',
        ],
        [
            'id' => 'alexandria',
            'country_code' => 'egypt',
            'country' => ['ar' => 'مصر', 'en' => 'Egypt'],
            'name' => ['ar' => 'فرع الإسكندرية', 'en' => 'Alexandria Branch'],
            'address' => [
                'ar' => 'الإسكندرية، مصر',
                'en' => 'Alexandria, Egypt',
            ],
            'phone' => '+201095584458',
            'whatsapp' => 'https://wa.me/201095584458',
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=31.26125,29.98375',
            'map_embed' => 'https://www.google.com/maps?q=31.26125,29.98375&z=16&output=embed',
        ],
    ],

    'footer_locations' => [
        'saudi_arabia' => [
            'ar' => [
                'فرع الرياض - القادسية - معارض السيارات',
                'فرع جيزان - صبيا - طريق الملك فهد',
                'فرع خميس مشيط - معارض السيارات',
                'فرع الدمام',
                'فرع جدة',
            ],
            'en' => [
                'Riyadh Branch - Al Qadisiyah - Car Showrooms',
                'Jazan Branch - Sabya - King Fahd Road',
                'Khamis Mushait Branch - Car Showrooms',
                'Dammam Branch',
                'Jeddah Branch',
            ],
        ],
        'egypt' => [
            'ar' => [
                'محطة بنزين وطنية - بجوار مسجد السلام، الوفاء والأمل، مدينة نصر',
                'فرع الإسكندرية - الإسكندرية، مصر',
            ],
            'en' => [
                'Wataniya Gas Station - Next to Al Salam Mosque, Wafaa and Amal, Nasr City',
                'Alexandria Branch - Alexandria, Egypt',
            ],
        ],
    ],

    'footer_phones' => [
        'saudi_arabia' => ['966534899166', '966504118823'],
        'egypt' => ['01099025564', '01095584458'],
    ],

    'assets' => [
        'logo' => 'assets/brand/seven-ways-logo.webp',
        'mark' => 'assets/brand/seven-ways-mark.webp',
        'hero_car' => 'assets/website/images/g-class-ar-Cv_phCfN.webp',
        'hero_background' => 'assets/website/images/home-bg-DkJ_mK4W.webp',
        'about_background' => 'assets/website/images/about-us-bg-DpxzeicM.webp',
        'about_video' => 'assets/website/videos/about-us-video-zlm9A9Qo.mp4',
        'page_title_background' => 'assets/website/images/pages-title-bg-DHr8j9_V.webp',
        'services_background' => 'assets/website/images/services-bg-BTO8wyrl.webp',
        'advantages_background' => 'assets/website/images/adv-bg-DhCImAKt.webp',
        'advantages_car' => 'assets/website/images/white-car-2kDVYj1h.webp',
        'secondary_logo' => 'assets/brand/seven-ways-mark.webp',
        'xpel_logo' => 'assets/website/images/logo-BBmOnB6N.webp',
        'products_background' => 'assets/website/images/products-bg-BKw9Wbq6.webp',
        'branches_background' => 'assets/website/images/branches-bg-b-euROWx.webp',
        'footer_car' => 'assets/website/images/audi-ar-DVxr30Bb.webp',
        'tyre_mark_1' => 'assets/website/images/tyre-mark-1-Bcet6rRb.png',
        'tyre_mark_2' => 'assets/website/images/tyre-mark-2-BISH33e4.png',
    ],

    'brand_logos' => [
        ['id' => 'xpel', 'name' => 'XPEL', 'image' => 'assets/website/images/logo-BBmOnB6N.webp', 'background' => '#ffb81c'],
        ['id' => 'hexis', 'name' => 'Hexis', 'image' => 'assets/website/images/logo-DguEO2O0.webp', 'background' => '#ffffff'],
        ['id' => 'upx', 'name' => 'UPX', 'image' => 'assets/website/images/logo-D3wbkwtS.webp', 'background' => '#000000'],
        ['id' => '3m', 'name' => '3M', 'image' => 'assets/website/images/logo-L2ly5kkF.webp', 'background' => '#d7b6a3'],
        ['id' => 'carpro', 'name' => 'CarPro', 'image' => 'assets/website/images/logo-CGkfx9mt.webp', 'background' => '#a1a1a1'],
        ['id' => 'project3', 'name' => 'Project 3', 'image' => 'assets/website/images/logo-D0I4roVz.webp', 'background' => '#b1b1b1'],
        ['id' => 'osren', 'name' => 'OSREN', 'image' => 'assets/website/images/osren-logo.webp', 'background' => '#111111'],
    ],

    'service_media' => [
        [
            'id' => 'ppf',
            'image' => 'assets/website/images/protection-Bojyp1bE.webp',
            'video' => 'assets/website/videos/paint-protection-films-BFI-I27J.mp4',
        ],
        [
            'id' => 'thermal',
            'image' => 'assets/website/images/thermal-insulation-D5pv4bAo.webp',
            'video' => 'assets/website/videos/thermal-insulation-v3aq9m9A.mp4',
        ],
        [
            'id' => 'nano',
            'image' => 'assets/website/images/nano-ceramic-ClspLSNk.webp',
            'video' => 'assets/website/videos/nano-ceramic-c1cY0Cy0.mp4',
        ],
        [
            'id' => 'polishing',
            'image' => 'assets/website/images/polishing-kT06SIma.webp',
            'video' => 'assets/website/videos/car-polishing-CcwjhZ_9.mp4',
        ],
    ],

    'product_packages' => [
        [
            'id' => 'xpel',
            'brand' => 'XPEL',
            'images' => [
                'assets/website/images/xpel-package-(1)-Cy0PwtMp.webp',
                'assets/website/images/xpel-package-(3)-sWI_VhrM.webp',
            ],
            'sections' => ['ppf', 'thermal'],
        ],
        [
            'id' => 'hexis',
            'brand' => 'Hexis',
            'images' => [
                'assets/website/images/hexis-package-(1)-Blm3ZWSg.webp',
                'assets/website/images/hexis-package-(2)-DnveVTkO.webp',
            ],
            'sections' => ['ppf', 'thermal', 'nano'],
        ],
        [
            'id' => 'upx',
            'brand' => 'UPX',
            'images' => [
                'assets/website/images/uxp-package-(1)-C11Q91bg.webp',
                'assets/website/images/uxp-package-(2)-DCCBpFP1.webp',
            ],
            'sections' => ['ppf', 'thermal', 'nano'],
        ],
        [
            'id' => '3m',
            'brand' => '3M',
            'images' => [
                'assets/website/images/3m-package-(1)-DTO3noni.webp',
                'assets/website/images/3m-package-(2)-C52kz2Uf.webp',
            ],
            'sections' => ['ppf', 'thermal', 'nano'],
        ],
        [
            'id' => 'carpro',
            'brand' => 'CarPro',
            'images' => [
                'assets/website/images/carpro-package-(1)-gn69KU65.webp',
                'assets/website/images/carpro-package-(2)-D5ajrG_X.webp',
            ],
            'sections' => ['nano'],
        ],
        [
            'id' => 'project3',
            'brand' => 'Project 3',
            'images' => [
                'assets/website/images/project3-package-(1)-BTb8wbbJ.webp',
            ],
            'sections' => ['ppf', 'thermal'],
        ],
        [
            'id' => 'osren',
            'brand' => 'OSREN',
            'images' => [
                'assets/website/images/osren-nao-glaze-28.webp',
            ],
            'sections' => ['polishing'],
        ],
    ],
];
