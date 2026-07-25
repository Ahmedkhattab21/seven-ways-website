@extends('layouts.app')
@section('title', 'بيانات الشركة')
@section('page-title', 'بيانات الشركة')
@section('breadcrumb', 'الإعدادات / الشركة')
@section('content')
<x-card title="الهوية والإعدادات التشغيلية">
    <form method="POST" action="{{ route('company.update', $company) }}" enctype="multipart/form-data" class="sw-form">
        @csrf @method('PUT')
        <div class="sw-form-grid">
            <x-form.input name="name" label="اسم الشركة" :value="$company->name" required />
            <x-form.input name="legal_name" label="الاسم القانوني" :value="$company->legal_name" />
            <x-form.input name="commercial_registration" label="السجل التجاري" :value="$company->commercial_registration" />
            <x-form.input name="tax_number" label="الرقم الضريبي" :value="$company->tax_number" />
            <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="$company->email" />
            <x-form.input name="phone" label="الهاتف" :value="$company->phone" />
            <x-form.input name="country_code" label="رمز الدولة" :value="$company->country_code" required />
            <x-form.select name="currency_id" label="العملة الأساسية" required>
                @foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_id', $company->currency_id) == $currency->id)>{{ $currency->code }} — {{ $currency->name_ar }}</option>@endforeach
            </x-form.select>
            <x-form.select name="default_tax_id" label="الضريبة الافتراضية">
                <option value="">بدون</option>
                @foreach($taxes as $tax)<option value="{{ $tax->id }}" @selected(old('default_tax_id', $company->default_tax_id) == $tax->id)>{{ $tax->name }}</option>@endforeach
            </x-form.select>
            <x-form.input name="timezone" label="المنطقة الزمنية" :value="$company->timezone" required />
            <x-form.input name="fiscal_year_start_month" type="number" label="شهر بداية السنة المالية" :value="$company->fiscal_year_start_month" min="1" max="12" required />
            <x-form.select name="date_format" label="صيغة التاريخ" required>@foreach(['Y-m-d','d/m/Y','d-m-Y'] as $format)<option value="{{ $format }}" @selected(old('date_format', $company->date_format) === $format)>{{ $format }}</option>@endforeach</x-form.select>
            <x-form.select name="time_format" label="صيغة الوقت" required>@foreach(['H:i','h:i A'] as $format)<option value="{{ $format }}" @selected(old('time_format', $company->time_format) === $format)>{{ $format }}</option>@endforeach</x-form.select>
            <x-form.input name="money_decimal_places" type="number" label="الخانات العشرية للمبالغ" :value="$company->money_decimal_places" min="0" max="4" required />
            <x-form.select name="default_language" label="اللغة" required><option value="ar" @selected(old('default_language', $company->default_language) === 'ar')>العربية</option><option value="en" @selected(old('default_language', $company->default_language) === 'en')>English</option></x-form.select>
            <x-form.select name="ui_direction" label="اتجاه الواجهة" required><option value="rtl" @selected(old('ui_direction', $company->ui_direction) === 'rtl')>RTL</option><option value="ltr" @selected(old('ui_direction', $company->ui_direction) === 'ltr')>LTR</option></x-form.select>
            <x-form.input name="logo" type="file" label="شعار الشركة" accept="image/png,image/jpeg,image/webp" />
            <x-form.textarea name="address" label="العنوان">{{ $company->address }}</x-form.textarea>
        </div>
        <label class="sw-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $company->is_active))> الشركة نشطة</label>
        <div class="sw-form-actions"><x-button type="submit">حفظ التغييرات</x-button></div>
    </form>
</x-card>
@endsection
