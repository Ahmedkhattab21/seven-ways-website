@extends('layouts.app')
@section('title', 'نسخة قالب جودة')
@section('breadcrumb', 'الجودة')
@section('page-title', 'إنشاء نسخة قالب جودة')
@section('content')
<form class="sw-card" method="POST" action="{{ route('quality-templates.store') }}">
    @csrf
    <div class="sw-form-grid">
        <label>الكود<input name="code" value="{{ old('code') }}" required></label>
        <label>الاسم<input name="name" value="{{ old('name') }}" required></label>
        <label>الخدمة<select name="service_id"><option value="">عام أو حسب النوع</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></label>
        <label>نوع الخدمة<input name="service_type" value="{{ old('service_type') }}" placeholder="ppf"></label>
        <label><input type="checkbox" name="is_default" value="1" checked> افتراضي</label>
        <label><input type="checkbox" name="is_active" value="1" checked> نشط</label>
    </div>
    <h2>العناصر</h2>
    @foreach(range(0, 7) as $index)
        <div class="sw-form-grid">
            <input name="items[{{ $index }}][code]" placeholder="CODE" @if($index === 0) required @endif>
            <input name="items[{{ $index }}][name]" placeholder="اسم العنصر" @if($index === 0) required @endif>
            <input name="items[{{ $index }}][category]" placeholder="التصنيف" @if($index === 0) required @endif>
            <select name="items[{{ $index }}][check_type]"><option value="pass_fail">Pass/Fail</option><option value="rating">Rating</option><option value="text">Text</option><option value="measurement">Measurement</option><option value="photo">Photo</option></select>
            <label><input type="checkbox" name="items[{{ $index }}][is_required]" value="1" checked> مطلوب</label>
            <label><input type="checkbox" name="items[{{ $index }}][is_critical]" value="1"> حرج</label>
            <label><input type="checkbox" name="items[{{ $index }}][requires_photo_on_failure]" value="1"> صورة عند الفشل</label>
        </div>
    @endforeach
    <button class="sw-btn">حفظ النسخة</button>
</form>
@endsection
