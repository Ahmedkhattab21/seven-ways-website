@props(['compact' => false])

<div {{ $attributes->class(['sw-brand', 'sw-brand--compact' => $compact]) }}>
    @unless($compact)
        <img class="sw-brand__logo" src="{{ asset(config('branding.logo')) }}" alt="Seven Ways">
    @endunless
    <img class="sw-brand__compact-logo" src="{{ asset(config('branding.mark')) }}" alt="" aria-hidden="true">
</div>
