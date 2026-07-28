@props(['code', 'title', 'message', 'reference' => null])

<div class="sw-error-page">
    <div class="sw-error-page__content">
        <x-brand />
        <span class="sw-error-page__code">{{ $code }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        @if($reference)
            <p>Reference: {{ $reference }}</p>
        @endif
        <a class="sw-button sw-button--primary" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
            {{ auth()->check() ? 'العودة إلى لوحة التحكم' : 'العودة إلى تسجيل الدخول' }}
        </a>
    </div>
</div>
