@props(['type' => 'info', 'title' => null])

<div {{ $attributes->class(['sw-alert', "sw-alert--{$type}"])->merge(['role' => 'alert']) }}>
    <x-icon :name="$type === 'danger' ? 'alert' : 'info'" :size="20" />
    <div>
        @if($title)<strong>{{ $title }}</strong>@endif
        <div>{{ $slot }}</div>
    </div>
</div>
