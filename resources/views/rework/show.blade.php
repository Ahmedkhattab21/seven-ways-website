@extends('layouts.app')
@section('title', $rework->rework_number)
@section('breadcrumb', 'إعادة العمل')
@section('page-title', $rework->rework_number)
@section('content')
<div class="sw-card"><p>أمر العمل: {{ $rework->workOrder->work_order_number }} — الحالة: {{ $rework->status }}</p><p>{{ $rework->reason }}</p></div>
<div class="sw-card sw-table-wrap"><h2>الخدمات</h2><table class="sw-table"><thead><tr><th>الخدمة</th><th>الحالة</th><th>الإجراء المطلوب</th><th></th></tr></thead><tbody>
@foreach($rework->services as $line)<tr><td>{{ $line->workOrderService->description }}</td><td>{{ $line->status }}</td><td>{{ $line->required_action }}</td><td>@if($rework->status === 'in_progress' && $line->status !== 'completed')<form method="POST" action="{{ route('rework-orders.action', [$rework, 'service-complete']) }}">@csrf<input type="hidden" name="rework_service_id" value="{{ $line->id }}"><button class="sw-btn">إكمال الخدمة</button></form>@endif</td></tr>@endforeach
</tbody></table></div>
<div class="sw-card">
    @if($rework->status === 'draft')<form method="POST" action="{{ route('rework-orders.action', [$rework, 'approve']) }}">@csrf<button class="sw-btn">اعتماد</button></form>@endif
    @if($rework->status === 'approved')<form method="POST" action="{{ route('rework-orders.action', [$rework, 'start']) }}">@csrf<button class="sw-btn">بدء</button></form>@endif
    @if($rework->status === 'in_progress')<form method="POST" action="{{ route('rework-orders.action', [$rework, 'complete']) }}">@csrf<button class="sw-btn">إكمال وإعادة للجودة</button></form>@endif
</div>
<div class="sw-card sw-table-wrap"><h2>المواد الإضافية</h2><table class="sw-table"><thead><tr><th>المادة</th><th>المتوقع</th><th>الحالة</th><th>التكلفة</th><th></th></tr></thead><tbody>@foreach($rework->materials as $material)<tr><td>{{ $material->product?->name }}</td><td>{{ $material->expected_quantity }}</td><td>{{ $material->status }}</td><td>{{ $material->used_cost }}</td><td>@if($material->status === 'planned')<form method="POST" action="{{ route('rework-orders.materials.reserve',$material) }}">@csrf<button class="sw-btn">حجز</button></form>@elseif($material->status === 'reserved' && $material->material_type === 'quantity')<form method="POST" action="{{ route('work-order-materials.issue',$material) }}">@csrf<input type="number" step="0.000001" name="quantity" value="{{ $material->expected_quantity }}"><button class="sw-btn">صرف</button></form>@endif</td></tr>@endforeach</tbody></table></div>
@if(in_array($rework->status,['approved','in_progress']))
<form class="sw-card" method="POST" action="{{ route('rework-orders.materials.store',$rework) }}">@csrf<h2>إضافة مادة Rework</h2><div class="sw-form-grid"><select name="work_order_service_id">@foreach($rework->services as $line)<option value="{{ $line->work_order_service_id }}">{{ $line->workOrderService->description }}</option>@endforeach</select><select name="product_id">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select><select name="warehouse_id">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select><select name="material_type"><option value="quantity">كمية</option><option value="roll">رول</option><option value="scrap">قصاصة</option></select><input type="number" step="0.000001" min="0.000001" name="expected_quantity" required placeholder="الكمية"></div><button class="sw-btn">إضافة بدون خصم</button></form>
@endif
@can('viewCost', $rework)<div class="sw-card"><p>مواد: {{ $rework->additional_material_cost }} — هالك: {{ $rework->additional_waste_cost }} — عمالة: {{ $rework->additional_labor_cost }} — الإجمالي: {{ $rework->total_rework_cost }}</p></div>@endcan
<form class="sw-card" method="POST" enctype="multipart/form-data" action="{{ route('rework-orders.photos.store', $rework) }}">@csrf<input type="file" name="file" accept="image/*" required><select name="category"><option value="rework_before">قبل</option><option value="rework_during">أثناء</option><option value="rework_after">بعد</option></select><button class="sw-btn">رفع صورة خاصة</button></form>
@endsection
