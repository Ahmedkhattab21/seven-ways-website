@extends('website.layouts.app')

@section('title', __('website.meta.contact_title'))
@section('description', __('website.meta.contact_description'))

@section('content')
    @php
        $locale = app()->getLocale();
        $branchGroups = collect($branches)->groupBy('country_code');
    @endphp

    <main class="sw-contact-page">
        <section
            class="sw-contact-hero"
            style="--sw-contact-hero-background: url('{{ asset(config('website.assets.page_title_background')) }}')"
        >
            <div class="sw-contact-hero__title">
                <img src="{{ asset(config('branding.mark')) }}" alt="" aria-hidden="true">
                <h1>{{ __('website.contact.page_title') }}</h1>
            </div>
        </section>

        <section
            class="sw-contact-branches"
            style="--sw-contact-branches-background: url('{{ asset(config('website.assets.branches_background')) }}')"
        >
            <div class="sw-contact-branches__inner">
                <header class="sw-contact-title sw-reveal">
                    <img src="{{ asset(config('website.assets.secondary_logo')) }}" alt="" aria-hidden="true">
                    <h2>{{ __('website.contact.branches_title') }}</h2>
                </header>

                <div class="sw-contact-countries">
                    @foreach (['saudi_arabia', 'egypt'] as $countryCode)
                        @continue(!$branchGroups->has($countryCode))

                        @php($countryBranches = $branchGroups->get($countryCode))
                        <section class="sw-contact-country">
                            <h3 class="sw-reveal">{{ $countryBranches->first()['country'][$locale] }}</h3>

                            <div class="sw-contact-country__grid">
                                @foreach ($countryBranches as $branch)
                                    <article
                                        class="sw-contact-branch sw-reveal"
                                        style="--sw-contact-branch-delay: {{ $loop->index * 80 }}ms"
                                    >
                                        <div class="sw-contact-branch__name">
                                            <span>{{ $branch['name'][$locale] }}</span>
                                        </div>

                                        <div class="sw-contact-branch__actions">
                                            <a
                                                href="tel:{{ $branch['phone'] }}"
                                                aria-label="{{ __('website.contact.call') }} {{ $branch['name'][$locale] }}"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24c1.1.36 2.27.54 3.45.54A1.15 1.15 0 0 1 21 16.65V20a1.15 1.15 0 0 1-1.15 1.15A16.85 16.85 0 0 1 3 4.3 1.15 1.15 0 0 1 4.15 3H7.5a1.15 1.15 0 0 1 1.15 1.15c0 1.18.18 2.35.54 3.45a1 1 0 0 1-.24 1Z"/>
                                                </svg>
                                            </a>
                                            <a
                                                href="{{ $branch['whatsapp'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="{{ __('website.contact.whatsapp') }} {{ $branch['name'][$locale] }}"
                                            >
                                                <svg viewBox="0 0 32 32" aria-hidden="true">
                                                    <path d="M16.05 4A11.95 11.95 0 0 0 5.7 21.92L4 28.1l6.34-1.66a11.94 11.94 0 1 0 5.71-22.44Zm0 21.88a9.9 9.9 0 0 1-5.06-1.38l-.36-.21-3.76.98 1-3.66-.23-.38a9.94 9.94 0 1 1 8.41 4.65Zm5.45-7.44c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.95 1.17-.17.2-.35.22-.64.07-2.07-1.03-3.42-1.84-4.08-4.54-.17-.3-.02-.46.13-.61.14-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49 0 1.47 1.07 2.89 1.22 3.09.15.2 2.1 3.21 5.1 4.5.71.31 1.27.5 1.7.63.71.23 1.37.2 1.88.12.58-.09 1.77-.72 2.02-1.42.25-.7.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35Z"/>
                                                </svg>
                                            </a>
                                        </div>

                                        <div class="sw-contact-branch__map">
                                            <iframe
                                                src="{{ $branch['map_embed'] }}"
                                                title="{{ __('website.contact.map_title', ['branch' => $branch['name'][$locale]]) }}"
                                                loading="lazy"
                                                referrerpolicy="no-referrer-when-downgrade"
                                                allowfullscreen
                                            ></iframe>
                                            <a
                                                href="{{ $branch['map_link'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label="{{ __('website.contact.map_title', ['branch' => $branch['name'][$locale]]) }}"
                                            ></a>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
@endsection
