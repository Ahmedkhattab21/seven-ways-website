<footer class="sw-footer">
    <div class="sw-footer__tracks" aria-hidden="true">
        @for ($index = 0; $index < 10; $index++)
            <img src="{{ asset(config('website.assets.tyre_mark_2')) }}" alt="">
        @endfor
    </div>

    <div class="sw-shell sw-footer__grid">
        <div class="sw-footer__about">
            <a class="sw-footer__emblem" href="{{ route('website.home') }}" aria-label="Seven Ways">
                <img src="{{ asset(config('website.assets.logo')) }}" alt="">
            </a>

            <p>{{ __('website.footer.goal') }}</p>

            <div class="sw-footer__country-switch" role="group" aria-label="{{ __('website.footer.social_country') }}">
                <label>
                    <input type="radio" name="footer_country" value="egypt" data-sw-footer-country checked>
                    <span>{{ __('website.footer.egypt') }}</span>
                </label>
                <label>
                    <input type="radio" name="footer_country" value="saudi_arabia" data-sw-footer-country>
                    <span>{{ __('website.footer.saudi_arabia') }}</span>
                </label>
            </div>

            <div class="sw-socials">
                @foreach (['instagram', 'tiktok', 'facebook'] as $network)
                    <a
                        class="sw-socials__{{ $network }}"
                        href="{{ config("website.footer_socials.egypt.$network") }}"
                        data-sw-footer-social="{{ $network }}"
                        data-egypt-url="{{ config("website.footer_socials.egypt.$network") }}"
                        data-saudi-arabia-url="{{ config("website.footer_socials.saudi_arabia.$network") }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="{{ ucfirst($network) }}"
                    >
                        @if ($network === 'instagram')
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.75 2h8.5A5.75 5.75 0 0 1 22 7.75v8.5A5.75 5.75 0 0 1 16.25 22h-8.5A5.75 5.75 0 0 1 2 16.25v-8.5A5.75 5.75 0 0 1 7.75 2zm0 1.5A4.25 4.25 0 0 0 3.5 7.75v8.5A4.25 4.25 0 0 0 7.75 20.5h8.5A4.25 4.25 0 0 0 20.5 16.25v-8.5A4.25 4.25 0 0 0 16.25 3.5h-8.5zM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zm5.25-.88a1.13 1.13 0 1 1 0 2.25 1.13 1.13 0 0 1 0-2.25z"/></svg>
                        @elseif ($network === 'tiktok')
                            <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M38.08 15.85c-2-.06-3.83-1.02-5.2-2.31a8.88 8.88 0 0 1-2.32-5.54h-5.59v20.83c0 3.51-2.09 5.59-4.69 5.59a4.7 4.7 0 1 1 1.52-9.13V19.6a10.28 10.28 0 1 0 8.76 10.12V18.83a13.05 13.05 0 0 0 7.52 2.26v-5.24Z"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88v-6.99H7.9V12h2.54V9.41c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.25c-1.23 0-1.61.76-1.61 1.55V12h2.74l-.44 2.89h-2.3v6.99A10 10 0 0 0 22 12Z"/></svg>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="sw-footer__branches">
            <h2>{{ __('website.footer.branches') }}</h2>
            <div class="sw-footer__branch-columns">
                @foreach (['saudi_arabia', 'egypt'] as $country)
                    <section>
                        <h3>{{ __("website.footer.$country") }}:</h3>
                        <ul>
                            @foreach (config("website.footer_locations.$country.".app()->getLocale(), []) as $location)
                                <li>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-4.1 7-11.25a7 7 0 1 0-14 0C5 17.9 12 22 12 22Z"/><circle cx="12" cy="10.75" r="2.75"/></svg>
                                    <span>{{ $location }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <ul class="sw-footer__phones">
                            @foreach (config("website.footer_phones.$country", []) as $phone)
                                <li>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5.2C3 14 10 21 18.8 21H21v-4.1l-5-1.25-1.9 2.15a12.3 12.3 0 0 1-7.9-7.9L8.35 8 7.1 3H3v2.2Z"/></svg>
                                    <a href="tel:{{ $phone }}">{{ $phone }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    <img class="sw-footer__car" src="{{ asset(config('website.assets.footer_car')) }}" alt="">

</footer>
