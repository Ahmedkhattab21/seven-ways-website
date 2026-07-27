@php
    $locale = app()->getLocale();
    $direction = $locale === 'ar' ? 'rtl' : 'ltr';
    $description = trim($__env->yieldContent('description')) ?: __('website.meta.default_description');
    $shareImage = trim($__env->yieldContent('share_image')) ?: asset(config('website.assets.hero_car'));
    $currentPath = request()->getPathInfo();
    $localizedUrl = url($currentPath).'?lang='.$locale;
    $alternateUrls = [
        'ar' => url($currentPath).'?lang=ar',
        'en' => url($currentPath).'?lang=en',
    ];
    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'AutomotiveBusiness',
        'name' => 'Seven Ways',
        'url' => route('website.home'),
        'logo' => asset(config('website.assets.logo')),
        'description' => __('website.meta.default_description'),
        'sameAs' => array_values(array_diff_key(config('website.socials', []), ['xpel' => true])),
        'telephone' => config('website.branches.0.phone'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111111">
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $localizedUrl }}">
    <link rel="alternate" hreflang="ar" href="{{ $alternateUrls['ar'] }}">
    <link rel="alternate" hreflang="en" href="{{ $alternateUrls['en'] }}">
    <link rel="alternate" hreflang="x-default" href="{{ $alternateUrls['ar'] }}">
    <link rel="icon" type="image/webp" href="{{ asset(config('website.assets.logo')) }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Seven Ways">
    <meta property="og:title" content="@yield('title', __('website.meta.default_title'))">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $localizedUrl }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_EG' : 'en_US' }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'ar' ? 'en_US' : 'ar_EG' }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', __('website.meta.default_title'))">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $shareImage }}">

    <title>@yield('title', __('website.meta.default_title'))</title>

    @vite(['resources/css/website/website.css', 'resources/js/website/website.js'])
    <script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
</head>
<body class="sw-website @yield('body_class')">
    <a class="sw-skip-link" href="#sw-main">{{ __('website.navigation.skip') }}</a>

    @include('website.partials.header')

    <main id="sw-main">
        @yield('content')
    </main>

    @include('website.partials.footer')
    @include('website.partials.floating-contact-buttons')
</body>
</html>
