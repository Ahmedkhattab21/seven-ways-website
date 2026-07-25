@props(['eyebrow' => null, 'title', 'light' => false])

<header @class(['sw-section-heading', 'sw-section-heading--light' => $light])>
    @if ($eyebrow)
        <span>{{ $eyebrow }}</span>
    @endif
    <h2>{{ $title }}</h2>
</header>
