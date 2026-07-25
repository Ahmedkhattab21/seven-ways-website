<footer class="sw-footer">
    <div class="sw-footer__tracks" aria-hidden="true">
        @for ($index = 0; $index < 10; $index++)
            <img src="{{ asset(config('website.assets.tyre_mark_2')) }}" alt="">
        @endfor
    </div>

    <div class="sw-shell sw-footer__grid">
        <div class="sw-footer__about">
            <a class="sw-brand sw-brand--footer" href="{{ route('website.home') }}" aria-label="Seven Ways">
                <img class="sw-brand__mark" src="{{ asset(config('website.assets.logo')) }}" alt="">
                <img class="sw-brand__name" src="{{ asset(config('website.assets.brand_name')) }}" alt="Seven Ways">
            </a>
            <p>{{ __('website.footer.goal') }}</p>

            <div class="sw-socials">
                <a href="{{ config('website.socials.instagram') }}" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5A4.25 4.25 0 0 0 7.75 20.5h8.5A4.25 4.25 0 0 0 20.5 16.25v-8.5A4.25 4.25 0 0 0 16.25 3.5h-8.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zm5.25-.88a1.13 1.13 0 1 1 0 2.25 1.13 1.13 0 0 1 0-2.25z"/></svg>
                </a>
                <a href="{{ config('website.socials.tiktok') }}" target="_blank" rel="noopener noreferrer" aria-label="TikTok">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.7 3.2c.4 2.3 1.8 3.7 4.1 3.9v3.1a8 8 0 0 1-4.1-1.2v6.2a6.1 6.1 0 1 1-5.3-6v3.2a3 3 0 1 0 2.2 2.9V3.2h3.1z"/></svg>
                </a>
                <a href="{{ config('website.socials.facebook') }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88v-6.99H7.9V12h2.54V9.41c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.25c-1.23 0-1.61.76-1.61 1.55V12h2.74l-.44 2.89h-2.3v6.99A10 10 0 0 0 22 12z"/></svg>
                </a>
            </div>
        </div>

        <div class="sw-footer__branches">
            <h2>{{ __('website.footer.branches') }}</h2>
            <div class="sw-footer__branch-columns">
                <section>
                    <h3>{{ __('website.footer.saudi_arabia') }}</h3>
                    <ul>
                        @foreach (config('website.footer_locations.saudi_arabia.'.app()->getLocale(), []) as $location)
                            <li>{{ $location }}</li>
                        @endforeach
                    </ul>
                    @foreach (config('website.footer_phones.saudi_arabia', []) as $phone)
                        <a class="sw-footer__phone" href="tel:{{ $phone }}">{{ $phone }}</a>
                    @endforeach
                </section>
                <section>
                    <h3>{{ __('website.footer.egypt') }}</h3>
                    <ul>
                        @foreach (config('website.footer_locations.egypt.'.app()->getLocale(), []) as $location)
                            <li>{{ $location }}</li>
                        @endforeach
                    </ul>
                    @foreach (config('website.footer_phones.egypt', []) as $phone)
                        <a class="sw-footer__phone" href="tel:{{ $phone }}">{{ $phone }}</a>
                    @endforeach
                </section>
            </div>
        </div>
    </div>

    <div class="sw-footer__bottom">
        <div class="sw-shell">
            <span>© {{ now()->year }} Seven Ways</span>
            <a href="{{ route('website.sitemap') }}">{{ __('website.footer.sitemap') }}</a>
        </div>
    </div>

    <img class="sw-footer__car" src="{{ asset(config('website.assets.footer_car')) }}" alt="">
</footer>
