<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'لوحة التحكم') — Seven Ways ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sw-body">
    <div class="sw-app" data-app-shell>
        @include('partials.sidebar')
        <button class="sw-drawer-backdrop" type="button" data-sidebar-close aria-label="إغلاق القائمة"></button>

        <div class="sw-app__workspace">
            @include('partials.topbar')
            <main class="sw-main" id="main-content">
                @include('partials.feedback')
                <div class="sw-page-heading">
                    <div>
                        <nav class="sw-breadcrumb" aria-label="مسار التنقل">
                            <a href="{{ route('dashboard') }}">الرئيسية</a>
                            @hasSection('breadcrumb')
                                <x-icon name="chevron" :size="14" />
                                <span>@yield('breadcrumb')</span>
                            @endif
                        </nav>
                        <h1>@yield('page-title', 'لوحة التحكم')</h1>
                        @hasSection('page-description')
                            <p>@yield('page-description')</p>
                        @endif
                    </div>
                    @hasSection('page-actions')
                        <div class="sw-page-heading__actions">@yield('page-actions')</div>
                    @endif
                </div>
                @yield('content')
            </main>
            <footer class="sw-footer">
                <span>Seven Ways ERP</span>
                <span>واجهة الإدارة والتشغيل</span>
            </footer>
        </div>
    </div>
    @stack('modals')
</body>
</html>
