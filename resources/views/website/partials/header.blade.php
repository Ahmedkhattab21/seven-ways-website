<header class="sw-header" data-sw-header>
    <div class="sw-shell sw-header__inner">
        <a class="sw-brand" href="{{ route('website.home') }}" aria-label="Seven Ways">
            <img class="sw-brand__mark" src="{{ asset(config('website.assets.logo')) }}" alt="">
            <img class="sw-brand__name" src="{{ asset(config('website.assets.brand_name')) }}" alt="Seven Ways">
        </a>

        <nav class="sw-nav" aria-label="{{ __('website.navigation.label') }}">
            <a @class(['is-active' => request()->routeIs('website.home')]) href="{{ route('website.home') }}">
                {{ __('website.navigation.home') }}
            </a>
            <a @class(['is-active' => request()->routeIs('website.about')]) href="{{ route('website.about') }}">
                {{ __('website.navigation.about') }}
            </a>
            <a @class(['is-active' => request()->routeIs('website.services')]) href="{{ route('website.services') }}">
                {{ __('website.navigation.services') }}
            </a>
            <a @class(['is-active' => request()->routeIs('website.contact')]) href="{{ route('website.contact') }}">
                {{ __('website.navigation.contact') }}
            </a>
        </nav>

        <div class="sw-header__actions">
            @auth
                <a class="sw-system-link" href="{{ route('dashboard') }}">{{ __('website.navigation.dashboard') }}</a>
            @else
                <a class="sw-system-link" href="{{ route('login') }}">{{ __('website.navigation.login') }}</a>
            @endauth

            <form
                class="sw-language"
                method="POST"
                action="{{ route('website.language', app()->getLocale() === 'ar' ? 'en' : 'ar') }}"
                data-locale="{{ app()->getLocale() }}"
            >
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                <button type="submit" aria-label="{{ __('website.navigation.language') }}">
                    <span class="sw-language__thumb" aria-hidden="true"></span>
                    <span>{{ strtoupper(app()->getLocale()) }}</span>
                </button>
            </form>

            <button
                class="sw-menu-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="sw-mobile-menu"
                aria-label="{{ __('website.navigation.open_menu') }}"
                data-sw-menu-toggle
            >
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    @include('website.partials.mobile-menu')
</header>
