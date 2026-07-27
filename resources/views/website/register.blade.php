@extends('website.layouts.app')

@section('title', __('website.meta.register_title'))
@section('description', __('website.meta.register_description'))

@section('content')
    <main class="sw-registration-page">
        <div class="sw-registration-shell">
            @if (session('registration_success'))
                <section class="sw-registration-card sw-registration-card--status" role="status">
                    <div class="sw-registration-card__accent"></div>
                    <img src="{{ asset(config('website.assets.logo')) }}" alt="Seven Ways">
                    <h1>{{ __('website.registration.success_title') }}</h1>
                    <p>{{ session('registration_success') }}</p>
                    <a href="{{ route('website.home') }}">{{ __('website.registration.back_home') }}</a>
                </section>
            @else
                <form method="POST" action="{{ route('website.register.submit') }}" class="sw-registration-form">
                    @csrf

                    <section class="sw-registration-card sw-registration-card--intro">
                        <div class="sw-registration-card__accent"></div>
                        <img src="{{ asset(config('website.assets.logo')) }}" alt="Seven Ways">
                        <h1>{{ __('website.registration.title') }}</h1>
                        <p>{{ __('website.registration.intro') }}</p>
                        <div class="sw-registration-required-note">* {{ __('website.registration.required_note') }}</div>
                    </section>

                    @if (session('registration_error'))
                        <div class="sw-registration-alert" role="alert">{{ session('registration_error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="sw-registration-alert" role="alert">{{ __('website.registration.validation.summary') }}</div>
                    @endif

                    <section class="sw-registration-card">
                        <label for="full_name">{{ __('website.registration.fields.full_name') }} <b>*</b></label>
                        <input id="full_name" name="full_name" value="{{ old('full_name') }}" autocomplete="name" placeholder="{{ __('website.registration.placeholders.short_answer') }}" required>
                        @error('full_name') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="phone">{{ __('website.registration.fields.phone') }} <b>*</b></label>
                        <input id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" placeholder="{{ __('website.registration.placeholders.short_answer') }}" required>
                        @error('phone') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="email">{{ __('website.registration.fields.email') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="{{ __('website.registration.placeholders.short_answer') }}">
                        @error('email') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <fieldset>
                            <legend>{{ __('website.registration.fields.country') }} <b>*</b></legend>
                            @foreach (['saudi_arabia', 'egypt'] as $country)
                                <label class="sw-registration-option">
                                    <input type="radio" name="country" value="{{ $country }}" @checked(old('country') === $country) required>
                                    <span></span>
                                    {{ __('website.registration.countries.'.$country) }}
                                </label>
                            @endforeach
                        </fieldset>
                        @error('country') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="city">{{ __('website.registration.fields.city') }} <b>*</b></label>
                        <input id="city" name="city" value="{{ old('city') }}" placeholder="{{ __('website.registration.placeholders.short_answer') }}" required>
                        @error('city') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="vehicle_type">{{ __('website.registration.fields.vehicle_type') }} <b>*</b></label>
                        <input id="vehicle_type" name="vehicle_type" value="{{ old('vehicle_type') }}" placeholder="{{ __('website.registration.placeholders.short_answer') }}" required>
                        @error('vehicle_type') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="vehicle_model">{{ __('website.registration.fields.vehicle_model') }} <b>*</b></label>
                        <input id="vehicle_model" name="vehicle_model" value="{{ old('vehicle_model') }}" placeholder="{{ __('website.registration.placeholders.short_answer') }}" required>
                        @error('vehicle_model') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="vehicle_year">{{ __('website.registration.fields.vehicle_year') }}</label>
                        <input id="vehicle_year" type="number" name="vehicle_year" min="1980" max="{{ now()->year + 1 }}" value="{{ old('vehicle_year') }}" placeholder="{{ __('website.registration.placeholders.short_answer') }}">
                        @error('vehicle_year') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <fieldset>
                            <legend>{{ __('website.registration.fields.service') }} <b>*</b></legend>
                            @foreach (['ppf', 'thermal', 'nano', 'polishing', 'other'] as $service)
                                <label class="sw-registration-option">
                                    <input type="radio" name="service" value="{{ $service }}" @checked(old('service') === $service) required>
                                    <span></span>
                                    {{ __('website.registration.services.'.$service) }}
                                </label>
                            @endforeach
                        </fieldset>
                        @error('service') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="preferred_branch">{{ __('website.registration.fields.preferred_branch') }}</label>
                        <select id="preferred_branch" name="preferred_branch">
                            <option value="">{{ __('website.registration.placeholders.choose') }}</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch['id'] }}" @selected(old('preferred_branch') === $branch['id'])>
                                    {{ $branch['name'][app()->getLocale()] }}
                                </option>
                            @endforeach
                        </select>
                        @error('preferred_branch') <small>{{ $message }}</small> @enderror
                    </section>

                    <section class="sw-registration-card">
                        <label for="notes">{{ __('website.registration.fields.notes') }}</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="{{ __('website.registration.placeholders.long_answer') }}">{{ old('notes') }}</textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </section>

                    <div class="sw-honeypot" aria-hidden="true">
                        <label>Website<input name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="sw-registration-actions">
                        <button type="submit">{{ __('website.registration.submit') }}</button>
                        <button type="reset">{{ __('website.registration.clear') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </main>
@endsection
