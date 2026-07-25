@props(['title' => null, 'description' => null])

<x-card :title="$title" :subtitle="$description" {{ $attributes }}>
    @if(isset($tools))<div class="sw-table-tools">{{ $tools }}</div>@endif
    <div class="sw-table-scroll">
        <table class="sw-table">{{ $slot }}</table>
    </div>
    @if(isset($footer))<div class="sw-table-footer">{{ $footer }}</div>@endif
</x-card>
