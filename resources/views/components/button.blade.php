@props(['variant' => 'primary', 'loading' => false])

<button {{ $attributes->class(['sw-button', "sw-button--{$variant}"])->merge(['type' => 'button']) }}>
    @if($loading)
        <x-spinner size="sm" />
    @endif
    {{ $slot }}
</button>
