@extends('layouts.app')
@section('title', $transfer->transfer_number)
@section('page-title', $transfer->transfer_number)
@section('breadcrumb', 'المخزون / التحويلات / التفاصيل')
@section('page-actions')
@can('update',$transfer)<a class="sw-button sw-button--outline" href="{{ route('stock-transfers.edit',$transfer) }}">تعديل المسودة</a>@endcan
@endsection
@section('content')
<x-card title="مسار التحويل">
<div class="sw-stats">@foreach(['requested_at'=>'طلب','approved_at'=>'اعتماد','prepared_at'=>'تجهيز','shipped_at'=>'شحن','received_at'=>'استلام'] as $field=>$label)<div><strong>{{ $label }}</strong><small>{{ $transfer->{$field}?->format('Y-m-d H:i') ?? '—' }}</small></div>@endforeach</div>
<p>من: {{ $transfer->fromBranch->name }} / {{ $transfer->fromWarehouse->name }} — إلى: {{ $transfer->toBranch->name }} / {{ $transfer->toWarehouse->name }}</p>
<p>الحالة: <x-status-badge :status="$transfer->status" /> | Transit: {{ $transfer->transitWarehouse?->name ?? '—' }}</p>
</x-card>
@if($transfer->items->contains(fn($item) => bccomp($item->rejected_quantity, '0', 6) === 1))
<x-alert type="warning">
    توجد كميات مرفوضة غير محسومة ما زالت في مخزن Transit، وهي غير متاحة في المصدر أو الوجهة.
    تحتاج لاحقًا إلى إرجاع رسمي للمصدر أو تسوية معتمدة.
</x-alert>
@endif
<x-table-shell><thead><tr><th>المنتج</th><th>النوع</th><th>الرول/القصاصة</th><th>مطلوب</th><th>معتمد</th><th>مجهز</th><th>مشحون</th><th>مستلم</th><th>تالف</th><th>ناقص</th></tr></thead>
<tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product->name }}</td><td>{{ $item->item_type }}</td><td>{{ $item->roll?->roll_number ?? $item->scrap?->scrap_code ?? '—' }}</td><td>{{ $item->requested_quantity }}</td><td>{{ $item->approved_quantity ?? '—' }}</td><td>{{ $item->prepared_quantity ?? '—' }}</td><td>{{ $item->shipped_quantity ?? '—' }}</td><td>{{ $item->received_quantity }}</td><td>{{ $item->damaged_quantity }}</td><td>{{ $item->shortage_quantity }}</td></tr>@endforeach</tbody></x-table-shell>

@can('update',$transfer) @if($transfer->status==='draft')<form method="POST" action="{{ route('stock-transfers.submit',$transfer) }}">@csrf <x-button type="submit">تقديم للاعتماد</x-button></form>@endif @endcan
@can('approve',$transfer) @if($transfer->status==='pending_approval')<x-card title="قرار الاعتماد"><form method="POST" action="{{ route('stock-transfers.approval',$transfer) }}">@csrf <button class="sw-button sw-button--primary" name="action" value="approve">اعتماد</button><input name="reason" placeholder="سبب الرفض"><button class="sw-button sw-button--outline" name="action" value="reject">رفض</button></form></x-card>@endif @endcan
@can('prepare',$transfer) @if($transfer->status==='approved')<form method="POST" action="{{ route('stock-transfers.prepare',$transfer) }}">@csrf @foreach($transfer->items as $item)<input type="hidden" name="items[{{ $item->id }}]" value="{{ $item->approved_quantity }}">@endforeach<x-button type="submit">تأكيد التجهيز</x-button></form>@endif @endcan
@can('ship',$transfer) @if($transfer->status==='ready_to_ship')<form method="POST" action="{{ route('stock-transfers.ship',$transfer) }}">@csrf <input name="shipping_reference" placeholder="مرجع الشحن"><x-button type="submit">شحن إلى Transit</x-button></form>@endif @endcan
@can('receive',$transfer) @if(in_array($transfer->status,['shipped','partially_received']))<x-card title="استلام الشحنة"><form method="POST" action="{{ route('stock-transfers.receive',$transfer) }}">@csrf @foreach($transfer->items as $item)<p>{{ $item->product->name }}</p><div class="sw-form-grid">@foreach(['received_quantity'=>'مستلم','damaged_quantity'=>'تالف','shortage_quantity'=>'ناقص','rejected_quantity'=>'مرفوض'] as $field=>$label)<x-form.input :name="'items['.$item->id.']['.$field.']'" type="number" step="0.000001" :label="$label" value="0" />@endforeach</div>@endforeach<div class="sw-form-actions"><x-button type="submit">تسجيل الاستلام</x-button></div></form></x-card>@endif @endcan
@can('cancel',$transfer) @if(in_array($transfer->status,['draft','pending_approval','approved','preparing','ready_to_ship']))<form method="POST" action="{{ route('stock-transfers.cancel',$transfer) }}">@csrf <input name="reason" required placeholder="سبب الإلغاء"><x-button type="submit">إلغاء التحويل</x-button></form>@endif @endcan
@can('reverse',$transfer) @if($transfer->status==='received')<form method="POST" action="{{ route('stock-transfers.reverse',$transfer) }}">@csrf <x-button type="submit">إنشاء تحويل عكسي</x-button></form>@endif @endcan

<x-card title="الفروق">
@foreach($transfer->discrepancies as $difference)<p>{{ $difference->discrepancy_type }} — {{ $difference->quantity }} — {{ $difference->status }} @can('resolve',$difference) @if($difference->status==='open')<form method="POST" action="{{ route('stock-transfers.discrepancies.resolve',$difference) }}">@csrf <input name="resolution" required placeholder="الحل"><x-button type="submit">حل</x-button></form>@endif @endcan</p>@endforeach
@can('receive',$transfer)<form method="POST" action="{{ route('stock-transfers.discrepancies.store',$transfer) }}">@csrf <select name="stock_transfer_item_id">@foreach($transfer->items as $item)<option value="{{ $item->id }}">{{ $item->product->name }}</option>@endforeach</select><select name="discrepancy_type">@foreach(['shortage','excess','damage','wrong_item','wrong_roll','other'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select><input name="quantity" type="number" step="0.000001"><input name="description" required placeholder="الوصف"><x-button type="submit">تسجيل فرق</x-button></form>@endcan
</x-card>
@endsection
