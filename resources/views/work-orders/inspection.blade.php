@extends('layouts.app')
@section('title','فحص الاستلام')
@section('breadcrumb','فحص الاستلام')
@section('page-title','فحص استلام السيارة')
@section('content')
<div class="sw-card"><p>الحالة: {{ $inspection->status }} — السيارة: {{ $inspection->workOrder->vehicle->plate_number ?: $inspection->workOrder->vehicle->vin }}</p></div>
@can('update',$inspection)<form class="sw-card" method="POST" action="{{ route('vehicle-inspections.update',$inspection) }}">@csrf @method('PUT')
<input type="number" name="odometer" value="{{ $inspection->odometer }}" placeholder="العداد"><input type="number" step="0.01" min="0" max="100" name="fuel_level" value="{{ $inspection->fuel_level }}" placeholder="الوقود %"><textarea name="general_notes" placeholder="ملاحظات عامة">{{ $inspection->general_notes }}</textarea>
<h2>عنصر الفحص</h2><input name="items[0][section]" value="exterior" required><input name="items[0][item_code]" value="body" required><input name="items[0][item_name]" value="هيكل السيارة" required><select name="items[0][condition]"><option value="good">جيد</option><option value="scratched">خدوش</option><option value="damaged">تلف</option></select><label><input type="checkbox" name="items[0][is_existing_damage]" value="1"> ضرر سابق</label><button class="sw-btn">حفظ الفحص</button></form>
<form class="sw-card" method="POST" enctype="multipart/form-data" action="{{ route('vehicle-inspections.photos.store',$inspection) }}">@csrf<input type="file" name="file" accept="image/*" required><select name="category"><option value="inspection_overview">صورة عامة</option><option value="inspection_damage">ضرر</option><option value="inspection_odometer">العداد</option><option value="inspection_interior">الداخلية</option></select><button class="sw-btn">رفع صورة خاصة</button></form>
<form method="POST" action="{{ route('vehicle-inspections.complete',$inspection) }}">@csrf<input name="customer_name" placeholder="اسم إقرار العميل"><button class="sw-btn sw-btn--primary">إكمال الفحص</button></form>@endcan
<div class="sw-card"><h2>الصور</h2>@foreach($inspection->attachments as $attachment)<p><a href="{{ route('attachments.download',$attachment) }}">{{ $attachment->original_name }}</a> — {{ $attachment->category }}</p>@endforeach</div>
@endsection
