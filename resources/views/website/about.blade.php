@extends('website.layouts.app')

@section('title', __('website.meta.about_title'))
@section('description', __('website.meta.about_description'))

@section('content')
    <x-website.page-hero :title="__('website.about.page_title')" />

    <section class="sw-about sw-section">
        <div class="sw-shell sw-about__grid">
            <div class="sw-about__media sw-reveal">
                <video
                    autoplay
                    controls
                    muted
                    loop
                    playsinline
                    preload="metadata"
                    poster="{{ asset(config('website.assets.about_background')) }}"
                    aria-label="{{ __('website.about.video_label') }}"
                >
                    <source src="{{ asset(config('website.assets.about_video')) }}" type="video/mp4">
                </video>
            </div>
            <div class="sw-about__copy sw-reveal">
                <x-website.section-heading :title="__('website.about.history_title')" />
                <p>{{ __('website.about.history_body') }}</p>
                <div class="sw-about__year">
                    <strong>2018</strong>
                    <span>{{ __('website.about.founded') }}</span>
                </div>
            </div>
        </div>
    </section>

    @include('website.partials.advantages')
@endsection
