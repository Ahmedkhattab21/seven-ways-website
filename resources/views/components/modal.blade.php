@props(['id', 'title'])

<div id="{{ $id }}" class="sw-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" hidden>
    <button class="sw-modal__backdrop" type="button" data-modal-close="{{ $id }}" aria-label="إغلاق"></button>
    <div class="sw-modal__panel">
        <header>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <button class="sw-icon-button" type="button" data-modal-close="{{ $id }}" aria-label="إغلاق">
                <x-icon name="close" />
            </button>
        </header>
        <div class="sw-modal__body">{{ $slot }}</div>
        @if(isset($footer))<footer>{{ $footer }}</footer>@endif
    </div>
</div>
