@extends('website.layouts.app')

@section('title', __('website.meta.home_title'))
@section('description', __('website.meta.home_description'))
@section('body_class', 'sw-page-home')

@section('content')
    <section class="sw-hero">
        <div class="sw-shell sw-hero__inner">
            <div class="sw-hero__copy sw-reveal">
                <h1>{{ __('website.home.hero_title') }}</h1>
                <p>{{ __('website.home.hero_body') }}</p>
                <div class="sw-hero__cta">
                    <a class="sw-cta" href="{{ route('website.about') }}">
                        <span class="sw-hero__cta-label">{{ __('website.home.about_cta') }}</span>
                        <span class="sw-hero__cta-frame" aria-hidden="true">
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--top-left"></span>
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--top-right"></span>
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--bottom-right"></span>
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--bottom-left"></span>
                        </span>
                        <span class="sw-hero__cta-arrow sw-hero__cta-arrow--right" aria-hidden="true"></span>
                        <span class="sw-hero__cta-arrow sw-hero__cta-arrow--left" aria-hidden="true"></span>
                    </a>
                    <span class="sw-hero__cta-underline" aria-hidden="true"></span>
                </div>
            </div>

            <img
                class="sw-hero__car"
                src="{{ asset(config('website.assets.hero_car')) }}"
                alt="{{ __('website.home.hero_image_alt') }}"
                fetchpriority="high"
            >
        </div>
    </section>

    @include('website.partials.advantages')

    <section
        class="sw-home-services sw-section"
        style="--sw-home-services-background: url('{{ asset(config('website.assets.services_background')) }}')"
    >
        <div class="sw-shell sw-home-services__inner">
            <header class="sw-home-services__heading sw-reveal">
                <div>
                    <img
                        src="{{ asset(config('website.assets.secondary_logo')) }}"
                        alt=""
                        width="702"
                        height="668"
                        aria-hidden="true"
                    >
                    <h2>{{ __('website.home.services_title') }}</h2>
                </div>
            </header>

            <div class="sw-home-services__grid">
                @foreach (config('website.service_media', []) as $index => $service)
                    @php($translation = __('website.home.services.'.$service['id']))
                    <article
                        class="sw-home-service sw-reveal"
                        style="--sw-service-delay: {{ 300 + ($index * 300) }}ms"
                    >
                        <div class="sw-home-service__shape">
                            <h3><span>{{ $translation['title'] }}</span></h3>
                            <div class="sw-home-service__media">
                                <img
                                    src="{{ asset($service['image']) }}"
                                    alt="{{ $translation['title'] }}"
                                    loading="lazy"
                                >
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="sw-home-brands" aria-label="{{ __('website.home.brands_title') }}">
                @foreach (config('website.brand_logos', []) as $index => $brand)
                    <div
                        class="sw-home-brand sw-reveal"
                        style="--sw-brand-delay: {{ $index * 300 }}ms"
                    >
                        <a
                            href="{{ route('website.services') }}#{{ $brand['id'] }}"
                            style="--sw-brand-background: {{ $brand['background'] }}"
                            aria-label="{{ $brand['name'] }}"
                        >
                              @if (filled($brand['image'] ?? null))
                                  <img
                                      src="{{ asset($brand['image']) }}"
                                      alt="{{ $brand['name'] }}"
                                      loading="lazy"
                                  >
                              @else
                                  <strong class="sw-home-brand__name">{{ $brand['name'] }}</strong>
                              @endif
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="sw-home-services__action sw-reveal">
                <div class="sw-hero__cta sw-home-services__cta">
                    <a class="sw-cta" href="{{ route('website.services') }}">
                        <span class="sw-hero__cta-label">{{ __('website.home.show_more') }}</span>
                        <span class="sw-hero__cta-frame" aria-hidden="true">
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--top-left"></span>
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--top-right"></span>
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--bottom-right"></span>
                            <span class="sw-hero__cta-corner sw-hero__cta-corner--bottom-left"></span>
                        </span>
                        <span class="sw-hero__cta-arrow sw-hero__cta-arrow--right" aria-hidden="true"></span>
                        <span class="sw-hero__cta-arrow sw-hero__cta-arrow--left" aria-hidden="true"></span>
                    </a>
                    <span class="sw-hero__cta-underline" aria-hidden="true"></span>
                </div>
            </div>
        </div>
    </section>
@endsection
