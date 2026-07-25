@extends('layouts.app')
@section('title',data_get($document,$numberField)) @section('page-title',$title)
@section('content')
<div class="sw-card"><div class="sw-detail-grid"><div><small>الرقم</small><strong>{{ data_get($document,$numberField) }}</strong></div><div><small>الحالة</small><strong>{{ $document->status }}</strong></div><div><small>المورد</small><strong>{{ data_get($document,'supplier.name','—') }}</strong></div><div><small>الإجمالي</small><strong>{{ data_get($document,'total',data_get($document,'estimated_total','—')) }}</strong></div></div></div>
@if($document->relationLoaded('items'))<div class="sw-card"><table class="sw-table"><thead><tr><th>الصنف</th><th>الكمية</th><th>السعر/التكلفة</th><th>الإجمالي</th></tr></thead><tbody>@foreach($document->items as $item)<tr><td>{{ data_get($item,'product.name',data_get($item,'description','—')) }}</td><td>{{ data_get($item,'ordered_quantity',data_get($item,'requested_quantity',data_get($item,'received_quantity',data_get($item,'quantity')))) }}</td><td>{{ data_get($item,'unit_price',data_get($item,'unit_cost')) }}</td><td>{{ data_get($item,'total',data_get($item,'total_cost',data_get($item,'estimated_total'))) }}</td></tr>@endforeach</tbody></table></div>@endif
<div class="sw-page-actions">@foreach($actions as $action=>$label)<form method="POST" action="{{ route($actionRoute,[$document,$action]) }}">@csrf @if($action==='reject')<input name="reason" required placeholder="سبب الرفض">@endif<button class="sw-btn">{{ $label }}</button></form>@endforeach</div>
@yield('details')
@endsection
