@props(['title' => 'لا توجد بيانات بعد', 'message' => 'سيظهر المحتوى هنا عند توفره.', 'icon' => 'clipboard'])

<div class="sw-empty">
    <span class="sw-empty__icon"><x-icon :name="$icon" :size="28" /></span>
    <strong>{{ $title }}</strong>
    <p>{{ $message }}</p>
    {{ $slot }}
</div>
