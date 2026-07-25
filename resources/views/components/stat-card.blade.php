@props(['label', 'value', 'hint', 'icon'])

<article class="sw-stat">
    <div class="sw-stat__icon"><x-icon :name="$icon" :size="22" /></div>
    <div class="sw-stat__content">
        <p>{{ $label }}</p>
        <strong>{{ $value }}</strong>
        <span>{{ $hint }}</span>
    </div>
</article>
