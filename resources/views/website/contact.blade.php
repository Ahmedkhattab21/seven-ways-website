@extends('website.layouts.app')

@section('title', __('website.meta.contact_title'))
@section('description', __('website.meta.contact_description'))

@section('content')
    <x-website.page-hero :title="__('website.contact.page_title')" />

    <section class="sw-contact-branches sw-section">
        <div class="sw-shell">
            <x-website.section-heading :title="__('website.contact.branches_title')" />

            <div class="sw-contact-branches__grid">
                @foreach ($branches as $branch)
                    <article class="sw-branch-card sw-reveal">
                        <div class="sw-branch-card__heading">
                            <span>{{ $branch['country'][app()->getLocale()] }}</span>
                            <h2>{{ $branch['name'][app()->getLocale()] }}</h2>
                            <p>{{ $branch['address'][app()->getLocale()] }}</p>
                        </div>
                        <div class="sw-branch-card__actions">
                            <a href="tel:{{ $branch['phone'] }}">{{ __('website.contact.call') }} {{ $branch['phone'] }}</a>
                            <a href="{{ $branch['whatsapp'] }}" target="_blank" rel="noopener noreferrer">{{ __('website.contact.whatsapp') }}</a>
                        </div>
                        <iframe
                            src="{{ $branch['map_embed'] }}"
                            title="{{ __('website.contact.map_title', ['branch' => $branch['name'][app()->getLocale()]]) }}"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                        ></iframe>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="sw-contact-form-section sw-section" id="contact-form">
        <div class="sw-shell sw-contact-form-section__grid">
            <div>
                <x-website.section-heading :title="__('website.form.title')" light />
                <p>{{ __('website.form.intro') }}</p>
            </div>

            <form class="sw-contact-form" method="POST" action="{{ route('website.contact.submit') }}">
                @csrf

                @if (session('contact_success'))
                    <div class="sw-form-alert sw-form-alert--success" role="status">{{ session('contact_success') }}</div>
                @endif
                @if (session('contact_error'))
                    <div class="sw-form-alert sw-form-alert--error" role="alert">{{ session('contact_error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="sw-form-alert sw-form-alert--error" role="alert">{{ __('website.form.validation.summary') }}</div>
                @endif

                <div class="sw-form-grid">
                    <label>
                        <span>{{ __('website.form.fields.name') }}</span>
                        <input name="name" value="{{ old('name') }}" autocomplete="name" required>
                        @error('name') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>{{ __('website.form.fields.phone') }}</span>
                        <input name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" required>
                        @error('phone') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>{{ __('website.form.fields.email') }}</span>
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email">
                        @error('email') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>{{ __('website.form.fields.branch') }}</span>
                        <select name="branch">
                            <option value="">{{ __('website.form.choose_branch') }}</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch['id'] }}" @selected(old('branch') === $branch['id'])>
                                    {{ $branch['name'][app()->getLocale()] }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <label>
                    <span>{{ __('website.form.fields.subject') }}</span>
                    <input name="subject" value="{{ old('subject') }}" required>
                    @error('subject') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>{{ __('website.form.fields.message') }}</span>
                    <textarea name="message" rows="6" required>{{ old('message') }}</textarea>
                    @error('message') <small>{{ $message }}</small> @enderror
                </label>

                <div class="sw-honeypot" aria-hidden="true">
                    <label>Website<input name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <button class="sw-cta" type="submit">{{ __('website.form.submit') }}</button>
            </form>
        </div>
    </section>
@endsection
