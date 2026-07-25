<?php

return [
    'locales' => ['ar', 'en'],

    'contact' => [
        'recipient' => env('WEBSITE_CONTACT_EMAIL') ?: env('MAIL_FROM_ADDRESS'),
    ],

    'socials' => [
        'instagram' => 'https://www.instagram.com/7sevenways',
        'tiktok' => 'https://www.tiktok.com/@7sevenways?_t=ZS-8y8bGfLUQSW&_r=1',
        'facebook' => 'https://www.facebook.com/share/1AfhixGZwR/?mibextid=wwXIfr',
        'xpel' => 'https://www.xpel.com',
    ],

    'branches' => [
        [
            'id' => 'qadisiyah',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'القادسية', 'en' => 'Al Qadisiyah'],
            'address' => [
                'ar' => 'فرع الرياض - القادسية - معارض السيارات',
                'en' => 'Riyadh Branch - Al Qadisiyah - Car Showrooms',
            ],
            'phone' => '+966534899166',
            'whatsapp' => 'https://wa.me/966534899166',
            'map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3621.109458180303!2d46.826651000000005!3d24.825930099999997!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3e2e55e2569c1033%3A0x58a5ab5b9930ee16!2sSEVEN%20WAYS!5e0!3m2!1sar!2seg!4v1754321572499!5m2!1sar!2seg',
        ],
        [
            'id' => 'jazan',
            'country' => ['ar' => 'السعودية', 'en' => 'Saudi Arabia'],
            'name' => ['ar' => 'جازان', 'en' => 'Jazan'],
            'address' => [
                'ar' => 'فرع جازان - صبيا - طريق الملك فهد',
                'en' => 'Jazan Branch - Sabya - King Fahd Road',
            ],
            'phone' => '+966504118823',
            'whatsapp' => 'https://wa.me/966504118823',
            'map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3812.218296091228!2d42.6290111!3d17.159589!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x15fd53c4b043abfd%3A0x685382d5362607fa!2sSEVEN%20WAYS!5e0!3m2!1sar!2seg!4v1754321640995!5m2!1sar!2seg',
        ],
        [
            'id' => 'nasr-city',
            'country' => ['ar' => 'مصر', 'en' => 'Egypt'],
            'name' => ['ar' => 'مدينة نصر', 'en' => 'Nasr City'],
            'address' => [
                'ar' => 'محطة بنزين وطنية - بجوار مسجد السلام، الوفاء والأمل، مدينة نصر',
                'en' => 'Wataniya Gas Station - Next to Al Salam Mosque, Wafaa and Amal, Nasr City',
            ],
            'phone' => '+201099025564',
            'whatsapp' => 'https://wa.me/201099025564',
            'map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3453.6884222212807!2d31.358420824582655!3d30.045795518506964!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14583d002493452f%3A0xe7f18626b13860ec!2sSeven%20Ways!5e0!3m2!1sar!2seg!4v1754321669152!5m2!1sar!2seg',
        ],
    ],

    'footer_locations' => [
        'saudi_arabia' => [
            'ar' => [
                'فرع الرياض - القادسية - معارض السيارات',
                'فرع جازان - صبيا - طريق الملك فهد',
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
            'ar' => ['محطة بنزين وطنية - بجوار مسجد السلام، الوفاء والأمل، مدينة نصر'],
            'en' => ['Wataniya Gas Station - Next to Al Salam Mosque, Wafaa and Amal, Nasr City'],
        ],
    ],

    'footer_phones' => [
        'saudi_arabia' => ['+966534899166', '+966504118823'],
        'egypt' => ['01099025564', '01095584458'],
    ],

    'assets' => [
        'logo' => 'assets/website/images/logo-DHNnkSwZ.webp',
        'brand_name' => 'assets/website/images/brand-name-DPS60m21.webp',
        'hero_car' => 'assets/website/images/g-class-ar-Cv_phCfN.webp',
        'hero_background' => 'assets/website/images/home-bg-DkJ_mK4W.webp',
        'about_background' => 'assets/website/images/about-us-bg-DpxzeicM.webp',
        'about_video' => 'assets/website/videos/about-us-video-zlm9A9Qo.mp4',
        'page_title_background' => 'assets/website/images/pages-title-bg-DHr8j9_V.webp',
        'services_background' => 'assets/website/images/services-bg-BTO8wyrl.webp',
        'advantages_background' => 'assets/website/images/adv-bg-DhCImAKt.webp',
        'products_background' => 'assets/website/images/products-bg-BKw9Wbq6.webp',
        'branches_background' => 'assets/website/images/branches-bg-b-euROWx.webp',
        'footer_car' => 'assets/website/images/audi-ar-DVxr30Bb.webp',
        'tyre_mark_1' => 'assets/website/images/tyre-mark-1-Bcet6rRb.png',
        'tyre_mark_2' => 'assets/website/images/tyre-mark-2-BISH33e4.png',
    ],

    'brand_logos' => [
        ['name' => 'XPEL', 'image' => 'assets/website/images/logo-BBmOnB6N.webp'],
        ['name' => 'Hexis', 'image' => 'assets/website/images/logo-DguEO2O0.webp'],
        ['name' => 'UPX', 'image' => 'assets/website/images/logo-D3wbkwtS.webp'],
        ['name' => '3M', 'image' => 'assets/website/images/logo-L2ly5kkF.webp'],
        ['name' => 'CarPro', 'image' => 'assets/website/images/logo-CGkfx9mt.webp'],
        ['name' => 'Project 3', 'image' => 'assets/website/images/logo-D0I4roVz.webp'],
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
            'image' => 'assets/website/images/xpel-package-(1)-Cy0PwtMp.webp',
            'sections' => ['ppf', 'thermal'],
        ],
        [
            'id' => 'hexis',
            'brand' => 'Hexis',
            'image' => 'assets/website/images/hexis-package-(1)-Blm3ZWSg.webp',
            'sections' => ['ppf', 'thermal', 'nano'],
        ],
        [
            'id' => 'upx',
            'brand' => 'UPX',
            'image' => 'assets/website/images/uxp-package-(1)-C11Q91bg.webp',
            'sections' => ['ppf', 'thermal', 'nano'],
        ],
        [
            'id' => '3m',
            'brand' => '3M',
            'image' => 'assets/website/images/3m-package-(1)-DTO3noni.webp',
            'sections' => ['ppf', 'thermal', 'nano'],
        ],
        [
            'id' => 'carpro',
            'brand' => 'CarPro',
            'image' => 'assets/website/images/carpro-package-(1)-gn69KU65.webp',
            'sections' => ['nano'],
        ],
        [
            'id' => 'project3',
            'brand' => 'Project 3',
            'image' => 'assets/website/images/project3-package-(1)-BTb8wbbJ.webp',
            'sections' => ['ppf', 'thermal'],
        ],
    ],
];
