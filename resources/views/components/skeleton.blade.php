@props(['lines' => 3])

<div {{ $attributes->class(['sw-skeleton']) }} aria-label="جارٍ تحميل المحتوى">
    @for($i = 0; $i < $lines; $i++)
        <span @class(['sw-skeleton__line', 'sw-skeleton__line--short' => $i === $lines - 1])></span>
    @endfor
</div>
