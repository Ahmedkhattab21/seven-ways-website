@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->class(['sw-card']) }}>
    @if($title || isset($action))
        <header class="sw-card__header">
            <div>
                @if($title)<h2 class="sw-card__title">{{ $title }}</h2>@endif
                @if($subtitle)<p class="sw-card__subtitle">{{ $subtitle }}</p>@endif
            </div>
            {{ $action ?? '' }}
        </header>
    @endif
    <div class="sw-card__body">{{ $slot }}</div>
</section>
