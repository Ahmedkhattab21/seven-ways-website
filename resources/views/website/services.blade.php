@extends('website.layouts.app')

@section('title', __('website.meta.services_title'))
@section('description', __('website.meta.services_description'))

@section('content')
    <x-website.page-hero :title="__('website.services.page_title')" />

    <section class="sw-services-showcase sw-section" data-sw-slider>
        <div class="sw-shell">
            <x-website.section-heading :title="__('website.services.our_services')" />

            <div class="sw-slider">
                <button class="sw-slider__control" type="button" data-sw-slider-previous aria-label="{{ __('website.services.previous') }}">‹</button>
                <div class="sw-slider__track" data-sw-slider-track>
                    @foreach (config('website.service_media', []) as $service)
                        @php($translation = __('website.home.services.'.$service['id']))
                        <article class="sw-video-card">
                            <video
                                controls
                                muted
                                loop
                                playsinline
                                preload="metadata"
                                poster="{{ asset($service['image']) }}"
                                aria-label="{{ $translation['title'] }}"
                            >
                                <source src="{{ asset($service['video']) }}" type="video/mp4">
                            </video>
                            <div>
                                <h2>{{ $translation['title'] }}</h2>
                                <p>{{ $translation['body'] }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <button class="sw-slider__control" type="button" data-sw-slider-next aria-label="{{ __('website.services.next') }}">›</button>
            </div>
        </div>
    </section>

    <section class="sw-products sw-section">
        <div class="sw-shell">
            <x-website.section-heading :title="__('website.services.products_title')" light />

            <div class="sw-products__list">
                @foreach (config('website.product_packages', []) as $product)
                    <article class="sw-product sw-reveal">
                        <div class="sw-product__media">
                            <img src="{{ asset($product['image']) }}" alt="{{ $product['brand'] }}">
                            <strong>{{ $product['brand'] }}</strong>
                        </div>
                        <div class="sw-product__content">
                            @foreach ($product['sections'] as $section)
                                @php($copy = __('website.services.products.'.$product['id'].'.'.$section))
                                <section>
                                    <h2>{{ $copy['title'] }}</h2>
                                    <p>{{ $copy['body'] }}</p>
                                </section>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
