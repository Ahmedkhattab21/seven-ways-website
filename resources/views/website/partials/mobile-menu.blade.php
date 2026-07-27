<div class="sw-mobile-menu" id="sw-mobile-menu" data-sw-mobile-menu hidden>
    <nav aria-label="{{ __('website.navigation.mobile_label') }}">
        <a href="{{ route('website.home') }}">{{ __('website.navigation.home') }}</a>
        <a href="{{ route('website.about') }}">{{ __('website.navigation.about') }}</a>
        <a href="{{ route('website.services') }}">{{ __('website.navigation.services') }}</a>
        <a href="{{ route('website.contact') }}">{{ __('website.navigation.contact') }}</a>
        @auth
            <a href="{{ route('dashboard') }}">{{ __('website.navigation.dashboard') }}</a>
        @else
            <a href="{{ route('website.register') }}">{{ __('website.navigation.register') }}</a>
        @endauth
    </nav>
</div>
