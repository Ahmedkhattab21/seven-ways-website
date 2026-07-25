@props(['size' => 'md'])

<span {{ $attributes->class(['sw-spinner', "sw-spinner--{$size}"]) }} role="status">
    <span class="sw-sr-only">جارٍ التحميل</span>
</span>
