@extends('layouts.app')
@section('title', $check->quality_check_number)
@section('breadcrumb', 'الجودة')
@section('page-title', $check->quality_check_number)
@section('content')
<div class="sw-card">
    <p>أمر العمل: <a href="{{ route('work-orders.show', $check->workOrder) }}">{{ $check->workOrder->work_order_number }}</a></p>
    <p>{{ $check->workOrder->customer->name }} — {{ $check->workOrder->vehicle->plate_number }}</p>
    <p>الجولة: {{ $check->round_number }} — الحالة: {{ $check->status }}</p>
</div>
@if(in_array($check->status, ['draft', 'in_progress']))
<form class="sw-card sw-table-wrap" method="POST" action="{{ route('quality-checks.items', $check) }}">
    @csrf @method('PUT')
    <table class="sw-table">
        <thead><tr><th>العنصر</th><th>النتيجة</th><th>القيمة/التقييم</th><th>ملاحظات</th></tr></thead>
        <tbody>
        @foreach($check->items as $index => $item)
            <tr>
                <td>{{ $item->name }} @if($item->is_critical)<strong>حرج</strong>@endif<input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}"></td>
                <td><select name="items[{{ $index }}][result]">@foreach(['pending','passed','failed','not_applicable'] as $result)<option value="{{ $result }}" @selected($item->result === $result)>{{ $result }}</option>@endforeach</select></td>
                <td><input type="number" min="1" max="5" name="items[{{ $index }}][rating]" value="{{ $item->rating }}"><input type="number" step="0.000001" name="items[{{ $index }}][measurement_value]" value="{{ $item->measurement_value }}"></td>
                <td><input name="items[{{ $index }}][notes]" value="{{ $item->notes }}"><input name="items[{{ $index }}][not_applicable_reason]" value="{{ $item->not_applicable_reason }}" placeholder="سبب N/A"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <button class="sw-btn">حفظ النتائج</button>
</form>
<form class="sw-card" method="POST" enctype="multipart/form-data" action="{{ route('quality-checks.photos.store', $check) }}">
    @csrf
    <input type="file" name="file" accept="image/*" required>
    <select name="category"><option value="quality_overview">صورة عامة</option><option value="quality_failure">صورة فشل</option><option value="quality_pass">صورة اجتياز</option></select>
    <button class="sw-btn">رفع صورة خاصة</button>
</form>
<div class="sw-card">
    <form method="POST" action="{{ route('quality-checks.action', [$check, 'pass']) }}">@csrf<input name="notes" placeholder="ملاحظات الاعتماد"><button class="sw-btn">Pass</button></form>
    <form method="POST" action="{{ route('quality-checks.action', [$check, 'fail']) }}">@csrf<input name="reason" required placeholder="سبب الرفض"><select name="reason_code"><option value="technician_error">خطأ فني</option><option value="material_defect">عيب مادة</option><option value="other">أخرى</option></select><button class="sw-btn">Fail / Rework</button></form>
</div>
@endif
<div class="sw-card"><h2>الصور</h2>@foreach($check->attachments as $attachment)<a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->category }} — {{ $attachment->original_name }}</a><br>@endforeach</div>
@endsection
