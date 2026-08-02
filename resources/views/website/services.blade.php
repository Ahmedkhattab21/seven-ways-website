@extends('website.layouts.app')

@section('title', __('website.meta.services_title'))
@section('description', __('website.meta.services_description'))

@section('content')
    <main class="sw-services-page">
        <section
            class="sw-services-hero"
            style="--sw-services-hero-background: url('{{ asset(config('website.assets.page_title_background')) }}')"
        >
            <div class="sw-services-hero__title">
                <img src="{{ asset(config('branding.mark')) }}" alt="" aria-hidden="true">
                <h1>{{ __('website.services.page_title') }}</h1>
            </div>
        </section>

        <section class="sw-services-showcase" data-sw-services-slider>
            <div class="sw-services-showcase__inner">
                <header class="sw-services-title sw-reveal">
                    <img src="{{ asset(config('website.assets.secondary_logo')) }}" alt="" aria-hidden="true">
                    <h2>{{ __('website.services.showcase_title') }}</h2>
                </header>

                <div class="sw-services-carousel">
                    <button
                        class="sw-services-carousel__control sw-services-carousel__control--previous"
                        type="button"
                        data-sw-services-previous
                        aria-label="{{ __('website.services.previous') }}"
                    >
                        <span aria-hidden="true">‹</span>
                    </button>

                    <div class="sw-services-carousel__viewport">
                        @foreach (config('website.service_media', []) as $service)
                            @php($translation = __('website.home.services.'.$service['id']))
                            <article
                                class="sw-service-slide {{ $loop->first ? 'is-active' : '' }}"
                                data-sw-services-slide
                                aria-hidden="{{ $loop->first ? 'false' : 'true' }}"
                            >
                                <div class="sw-service-slide__copy">
                                    <h3>{{ $translation['title'] }}</h3>
                                    <p>{{ $translation['body'] }}</p>
                                </div>
                                <div class="sw-service-slide__media">
                                    <video
                                        controls
                                        playsinline
                                        preload="{{ $loop->first ? 'metadata' : 'none' }}"
                                        poster="{{ asset($service['image']) }}"
                                        aria-label="{{ $translation['title'] }}"
                                    >
                                        <source src="{{ asset($service['video']) }}" type="video/mp4">
                                    </video>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <button
                        class="sw-services-carousel__control sw-services-carousel__control--next"
                        type="button"
                        data-sw-services-next
                        aria-label="{{ __('website.services.next') }}"
                    >
                        <span aria-hidden="true">›</span>
                    </button>
                </div>
            </div>
        </section>

        <section
            class="sw-products"
            style="--sw-products-background: url('{{ asset(config('website.assets.products_background')) }}')"
        >
            <div class="sw-products__inner">
                <header class="sw-services-title sw-services-title--products sw-reveal">
                    <img src="{{ asset(config('website.assets.secondary_logo')) }}" alt="" aria-hidden="true">
                    <h2>{{ __('website.services.products_title') }}</h2>
                </header>

                <div class="sw-products__list">
                    @foreach (config('website.product_packages', []) as $product)
                        @php($brand = collect(config('website.brand_logos', []))->firstWhere('id', $product['id']))
                        <article id="{{ $product['id'] }}" class="sw-product">
                            <div class="sw-product__masthead">
                                <div class="sw-product__brand sw-product-reveal sw-product-reveal--brand">
                                    <img src="{{ asset($brand['image']) }}" alt="{{ $brand['name'] }}">
                                </div>

                                <div class="sw-product__packages" aria-hidden="true">
                                    @foreach ($product['images'] as $image)
                                        <img
                                            class="sw-product__package sw-product__package--{{ $loop->iteration }}"
                                            src="{{ asset($image) }}"
                                            alt=""
                                            loading="lazy"
                                        >
                                    @endforeach
                                </div>
                            </div>

                            <div class="sw-product__content">
                                @foreach ($product['sections'] as $section)
                                    @php($copy = __('website.services.products.'.$product['id'].'.'.$section))
                                    <section class="sw-product__copy sw-product-reveal">
                                        <h3>{{ $copy['title'] }}</h3>
                                        <p>{{ $copy['body'] }}</p>
                                    </section>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
@endsection
