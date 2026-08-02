@extends('layouts.app')
@section('title', $count->document_number)
@section('page-title', 'جرد المخزون '.$count->document_number)
@section('breadcrumb', 'المخزون / الجرد / '.$count->document_number)
@section('content')
<section class="sw-card">
    <header class="sw-card__header">
        <div>
            <h2 class="sw-card__title">{{ $count->document_number }}</h2>
            <p class="sw-card__subtitle">{{ $count->branch->name }} — {{ $count->warehouse->name }}</p>
        </div>
    </header>
    <div class="sw-card__body">
        @if($count->status === 'counting' && $count->counted_at === null)
            @can('count', $count)
                <form method="POST" action="{{ route('inventory.counts.items.update', $count) }}">
                    @csrf @method('PUT')
                    <table class="sw-table">
                        <thead><tr><th>المنتج</th><th>كمية النظام وقت البدء</th><th>الكمية المعدودة</th></tr></thead>
                        <tbody>
                        @foreach($count->items as $item)
                            <tr>
                                <td>{{ $item->product->sku }} — {{ $item->product->name }}</td>
                                <td>{{ $item->system_quantity }}</td>
                                <td><input class="sw-input" type="number" min="0" step="0.000001" name="items[{{ $item->id }}][counted_quantity]" value="{{ old('items.'.$item->id.'.counted_quantity') }}" required></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <x-button type="submit">حفظ وإرسال للمراجعة</x-button>
                </form>
            @endcan
        @else
            <table class="sw-table">
                <thead><tr><th>المنتج</th><th>كمية النظام وقت البدء</th><th>الكمية المعدودة</th></tr></thead>
                <tbody>@foreach($count->items as $item)<tr><td>{{ $item->product->sku }} — {{ $item->product->name }}</td><td>{{ $item->system_quantity }}</td><td>{{ $item->counted_quantity ?? '—' }}</td></tr>@endforeach</tbody>
            </table>
        @endif

        @if($count->status === 'counting' && $count->counted_at)
            @can('post', $count)
                <form method="POST" action="{{ route('inventory.counts.post', $count) }}">@csrf <x-button type="submit">ترحيل الجرد</x-button></form>
            @endcan
        @endif
    </div>
</section>
@endsection
