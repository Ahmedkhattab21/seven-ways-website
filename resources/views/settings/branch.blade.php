@extends('layouts.app')
@section('title', 'إعدادات الفرع')
@section('page-title', 'إعدادات فرع '.$branch->name)
@section('breadcrumb', 'الإعدادات / الفرع الحالي')
@section('content')
<x-card title="التشغيل والترقيم">
    <form method="POST" action="{{ route('branch-settings.update') }}" class="sw-form">
        @csrf @method('PUT')
        <div class="sw-form-grid">
            <x-form.select name="default_tax_id" label="الضريبة الافتراضية"><option value="">بدون</option>@foreach($taxes as $tax)<option value="{{ $tax->id }}" @selected(old('default_tax_id', $settings->default_tax_id) == $tax->id)>{{ $tax->name }}</option>@endforeach</x-form.select>
            <x-form.select name="default_payment_method_id" label="طريقة الدفع الافتراضية"><option value="">بدون</option>@foreach($paymentMethods as $method)<option value="{{ $method->id }}" @selected(old('default_payment_method_id', $settings->default_payment_method_id) == $method->id)>{{ $method->name }}</option>@endforeach</x-form.select>
            @foreach(['invoice_prefix'=>'الفاتورة','quotation_prefix'=>'عرض السعر','appointment_prefix'=>'الحجز','work_order_prefix'=>'أمر العمل','purchase_order_prefix'=>'أمر الشراء','stock_transfer_prefix'=>'تحويل المخزون','warranty_prefix'=>'الضمان'] as $name => $label)<x-form.input :name="$name" :label="'بادئة '.$label" :value="$settings->{$name}" />@endforeach
            <x-form.input name="maximum_discount_percentage" type="number" step="0.01" label="أقصى خصم %" :value="$settings->maximum_discount_percentage ?? 0" min="0" max="100" required />
            <x-form.input name="working_day_start" type="time" label="بداية العمل" :value="$settings->working_day_start" />
            <x-form.input name="working_day_end" type="time" label="نهاية العمل" :value="$settings->working_day_end" />
            <x-form.select name="weekend_days[]" label="أيام الإجازة" multiple>
                @foreach(['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'] as $day => $label)<option value="{{ $day }}" @selected(in_array($day, old('weekend_days', $settings->weekend_days ?? [])))>{{ $label }}</option>@endforeach
            </x-form.select>
        </div>
        <label class="sw-check"><input type="checkbox" name="requires_discount_approval" value="1" @checked(old('requires_discount_approval', $settings->requires_discount_approval))> الخصم يحتاج موافقة</label>
        <label class="sw-check"><input type="checkbox" name="requires_invoice_cancel_approval" value="1" @checked(old('requires_invoice_cancel_approval', $settings->requires_invoice_cancel_approval))> إلغاء الفاتورة يحتاج موافقة</label>
        <label class="sw-check"><input type="checkbox" name="allow_negative_stock" value="1" @checked(old('allow_negative_stock', $settings->allow_negative_stock))> السماح بالمخزون السالب</label>
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button></div>
    </form>
</x-card>
@endsection
