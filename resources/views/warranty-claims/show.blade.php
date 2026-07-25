@extends('layouts.app')
@section('title', $claim->claim_number)
@section('breadcrumb', 'مطالبات الضمان')
@section('page-title', $claim->claim_number)
@section('content')
<div class="sw-card"><p>الضمان: {{ $claim->warranty->warranty_number }} — {{ $claim->customer->name }} — {{ $claim->vehicle->plate_number }}</p><p>الحالة: {{ $claim->status }} — القرار: {{ $claim->decision }}</p><p>{{ $claim->complaint }}</p></div>
<form class="sw-card" method="POST" enctype="multipart/form-data" action="{{ route('warranty-claims.photos.store', $claim) }}">@csrf<input type="file" name="file" accept="image/*" required><button class="sw-btn">رفع صورة فحص خاصة</button></form>
@if(in_array($claim->status, ['submitted', 'under_review', 'inspection_scheduled']))
<form class="sw-card" method="POST" action="{{ route('warranty-claims.inspect', $claim) }}">@csrf<h2>الفحص</h2>@foreach($claim->items as $index => $item)<input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"><p>{{ $item->description }} <select name="items[{{ $index }}][inspection_result]"><option value="installation_defect">عيب تركيب</option><option value="material_defect">عيب مادة</option><option value="misuse">سوء استخدام</option><option value="normal_wear">استهلاك طبيعي</option><option value="undetermined">غير محدد</option></select><input name="items[{{ $index }}][notes]" placeholder="ملاحظات"></p>@endforeach<button class="sw-btn">إكمال الفحص</button></form>
@endif
@if($claim->status === 'inspected')
<form class="sw-card" method="POST" action="{{ route('warranty-claims.decide', $claim) }}">@csrf<h2>القرار</h2><select name="decision"><option value="covered">مغطاة</option><option value="partially_covered">مغطاة جزئياً</option><option value="not_covered">غير مغطاة</option><option value="goodwill">حسن نية</option></select>@foreach($claim->items as $index => $item)<input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"><input type="number" min="0" max="100" name="items[{{ $index }}][coverage_percentage]" value="100">@endforeach<input name="reason" placeholder="سبب الرفض إن وجد"><button class="sw-btn">حفظ القرار</button></form>
@endif
@if($claim->status === 'approved')<form class="sw-card" method="POST" action="{{ route('warranty-claims.rework', $claim) }}">@csrf<button class="sw-btn">تحويل إلى Rework</button></form>@endif
@if($claim->status === 'under_review')<form class="sw-card" method="POST" action="{{ route('warranty-claims.resolve', $claim) }}">@csrf<button class="sw-btn">اعتماد الجودة وإغلاق المطالبة</button></form>@endif
<div class="sw-card"><h2>الصور</h2>@foreach($claim->attachments as $attachment)<a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a><br>@endforeach</div>
@endsection
