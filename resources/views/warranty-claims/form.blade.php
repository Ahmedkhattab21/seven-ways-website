@extends('layouts.app')
@section('title', 'مطالبة ضمان')
@section('breadcrumb', 'الضمان')
@section('page-title', 'مطالبة ضمان جديدة')
@section('content')
<form class="sw-card" method="POST" action="{{ route('warranty-claims.store') }}">@csrf
<label>الضمان<select name="warranty_id" required>@foreach($warranties as $warranty)<option value="{{ $warranty->id }}">{{ $warranty->warranty_number }} — {{ $warranty->vehicle_id }}</option>@endforeach</select></label>
<label>الشكوى<textarea name="complaint" required>{{ old('complaint') }}</textarea></label>
<h2>العناصر</h2>
@foreach(range(0, 2) as $index)<div class="sw-form-grid"><select name="items[{{ $index }}][warranty_item_id]" @if($index === 0) required @endif><option value="">اختر العنصر</option>@foreach($warranties as $warranty)@foreach($warranty->items as $item)<option value="{{ $item->id }}">{{ $warranty->warranty_number }} — {{ $item->service?->name }}</option>@endforeach @endforeach</select><select name="items[{{ $index }}][issue_type]"><option value="peeling">تقشير</option><option value="bubbles">فقاعات</option><option value="discoloration">تغير لون</option><option value="installation_defect">عيب تركيب</option><option value="material_defect">عيب مادة</option><option value="other">أخرى</option></select><input name="items[{{ $index }}][description]" placeholder="وصف المشكلة" @if($index === 0) required @endif></div>@endforeach
<button class="sw-btn">إرسال المطالبة</button></form>
@endsection
