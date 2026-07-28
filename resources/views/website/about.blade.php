@extends('website.layouts.app')

@section('title', __('website.meta.about_title'))
@section('description', __('website.meta.about_description'))

@section('content')
    <div class="sw-about-page">
        <section
            class="sw-about-hero"
            style="--sw-about-hero-background: url('{{ asset(config('website.assets.page_title_background')) }}')"
        >
            <div class="sw-about-hero__title">
                <img
                    src="{{ asset(config('website.assets.logo')) }}"
                    alt=""
                    width="702"
                    height="668"
                    aria-hidden="true"
                >
                <h1>{{ __('website.about.page_title') }}</h1>
            </div>
        </section>

        <section
            id="history"
            class="sw-about"
            style="--sw-about-background: url('{{ asset(config('website.assets.about_background')) }}')"
        >
            <div class="sw-about__inner">
                <header class="sw-about__heading sw-reveal">
                    <div>
                        <img
                            src="{{ asset(config('website.assets.secondary_logo')) }}"
                            alt=""
                            width="702"
                            height="668"
                            aria-hidden="true"
                        >
                        <h2>{{ __('website.about.history_title') }}</h2>
                    </div>
                </header>

                <div class="sw-about__grid">
                    <div class="sw-about__copy sw-reveal">
                        <p>{{ __('website.about.history_body') }}</p>
                    </div>

                    <div class="sw-about__media sw-reveal">
                        <video
                            controls
                            playsinline
                            preload="metadata"
                            aria-label="{{ __('website.about.video_label') }}"
                        >
                            <source src="{{ asset(config('website.assets.about_video')) }}" type="video/mp4">
                        </video>
                    </div>
                </div>
            </div>
        </section>

        @include('website.partials.advantages')
    </div>
@endsection
