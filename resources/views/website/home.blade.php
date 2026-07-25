@extends('website.layouts.app')

@section('title', __('website.meta.home_title'))
@section('description', __('website.meta.home_description'))

@section('content')
    <section class="sw-hero">
        <div class="sw-shell sw-hero__inner">
            <div class="sw-hero__copy sw-reveal">
                <h1>{{ __('website.home.hero_title') }}</h1>
                <p>{{ __('website.home.hero_body') }}</p>
                <a class="sw-cta" href="{{ route('website.about') }}">{{ __('website.home.about_cta') }}</a>
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

    <section class="sw-brands" aria-label="{{ __('website.home.brands_title') }}">
        <div class="sw-brands__track">
            @foreach (config('website.brand_logos', []) as $brand)
                <img src="{{ asset($brand['image']) }}" alt="{{ $brand['name'] }}">
            @endforeach
            @foreach (config('website.brand_logos', []) as $brand)
                <img src="{{ asset($brand['image']) }}" alt="" aria-hidden="true">
            @endforeach
        </div>
    </section>

    <section class="sw-home-services sw-section">
        <div class="sw-shell">
            <x-website.section-heading :title="__('website.home.services_title')" />

            <div class="sw-home-services__grid">
                @foreach (config('website.service_media', []) as $service)
                    @php($translation = __('website.home.services.'.$service['id']))
                    <article class="sw-service-card sw-reveal">
                        <img src="{{ asset($service['image']) }}" alt="{{ $translation['title'] }}">
                        <div>
                            <h3>{{ $translation['title'] }}</h3>
                            <p>{{ $translation['body'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="sw-section__action">
                <a class="sw-cta" href="{{ route('website.services') }}">{{ __('website.home.show_more') }}</a>
            </div>
        </div>
    </section>
@endsection
