@props(['compact' => false])

<div {{ $attributes->class(['sw-brand', 'sw-brand--compact' => $compact]) }}>
    <span class="sw-brand__mark" aria-hidden="true">
        <span>7</span><span>W</span>
    </span>
    @unless($compact)
        <span class="sw-brand__copy">
            <strong>SEVEN WAYS</strong>
            <small>نظام الإدارة والتشغيل</small>
        </span>
    @endunless
</div>
