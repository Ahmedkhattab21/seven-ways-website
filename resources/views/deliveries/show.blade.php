@extends('layouts.app')
@section('title', 'تسليم '.$workOrder->work_order_number)
@section('breadcrumb', 'التسليم')
@section('page-title', 'فحص وتسليم السيارة')
@section('content')
<div class="sw-card"><p>{{ $workOrder->customer->name }} — {{ $workOrder->vehicle->plate_number }}</p><p>الحالة: {{ $inspection->status }}</p></div>
@if($inspection->status === 'draft')
<form class="sw-card" method="POST" action="{{ route('deliveries.inspection.update', $workOrder) }}">@csrf @method('PUT')
    <div class="sw-form-grid"><label>عداد التسليم<input type="number" name="odometer" value="{{ $inspection->odometer }}"></label><label>الوقود<input type="number" step="0.01" name="fuel_level" value="{{ $inspection->fuel_level }}"></label><label>اسم المستلم<input name="receiver_name" value="{{ $inspection->receiver_name }}"></label><label>جوال/هوية اختيارية<input name="receiver_contact" value="{{ $inspection->receiver_contact }}"></label></div>
    <input name="items[0][section]" value="exterior" type="hidden"><input name="items[0][item_code]" value="final_condition" type="hidden"><input name="items[0][item_name]" value="حالة السيارة النهائية" type="hidden"><label>الحالة النهائية<select name="items[0][condition]"><option value="good">جيدة</option><option value="noted">توجد ملاحظة</option></select></label><input name="items[0][notes]" placeholder="ملاحظات"><button class="sw-btn">حفظ الفحص</button>
</form>
<form class="sw-card" method="POST" enctype="multipart/form-data" action="{{ route('deliveries.photos.store', $workOrder) }}">@csrf<input type="file" name="file" accept="image/*" required><select name="category"><option value="delivery_overview">صور نهائية</option><option value="delivery_signature">توقيع العميل</option></select><button class="sw-btn">رفع ملف خاص</button></form>
<form class="sw-card" method="POST" action="{{ route('deliveries.inspection.complete', $workOrder) }}">@csrf<input name="receiver_name" required placeholder="اسم المستلم"><button class="sw-btn">إكمال فحص التسليم</button></form>
@endif
<div class="sw-card"><h2>المرفقات</h2>@foreach($inspection->attachments as $attachment)<a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->category }}</a><br>@endforeach</div>
@if(in_array($inspection->status, ['completed', 'customer_acknowledged']))
<form class="sw-card" method="POST" action="{{ route('deliveries.deliver', $workOrder) }}">@csrf<input name="receiver_name" required value="{{ $inspection->approved_by_customer_name }}" placeholder="اسم المستلم"><input name="receiver_contact" value="{{ $inspection->receiver_contact }}" placeholder="جوال/هوية اختيارية"><button class="sw-btn">تسليم السيارة نهائياً</button></form>
@endif
@endsection
