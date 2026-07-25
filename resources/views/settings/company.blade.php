@extends('layouts.app')
@section('title', 'بيانات الشركة')
@section('page-title', 'بيانات الشركة')
@section('breadcrumb', 'الإعدادات / الشركة')
@section('content')
<x-card title="الهوية والبيانات النظامية">
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
            <x-form.input name="currency_code" label="رمز العملة" :value="$company->currency_code" required />
            <x-form.input name="timezone" label="المنطقة الزمنية" :value="$company->timezone" required />
            <x-form.input name="fiscal_year_start_month" type="number" label="بداية السنة المالية" :value="$company->fiscal_year_start_month" min="1" max="12" required />
            <x-form.input name="logo" type="file" label="شعار الشركة" accept="image/png,image/jpeg,image/webp" />
            <x-form.textarea name="address" label="العنوان">{{ $company->address }}</x-form.textarea>
        </div>
        <label class="sw-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $company->is_active))> الشركة نشطة</label>
        <div class="sw-form-actions"><x-button type="submit">حفظ التغييرات</x-button></div>
    </form>
</x-card>
@endsection
