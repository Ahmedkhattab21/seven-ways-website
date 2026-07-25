@extends('layouts.app')
@php($editing = $customer->exists)
@section('title', $editing ? 'تعديل العميل' : 'إضافة عميل')
@section('page-title', $editing ? 'تعديل العميل' : 'إضافة عميل')
@section('breadcrumb', 'إدارة العملاء')
@section('content')
<x-card>
<form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="sw-form">
    @csrf @if($editing) @method('PUT') @endif
    <div class="sw-form-grid">
        <x-form.select name="customer_type" label="نوع العميل" required>@foreach(['individual'=>'فرد','company'=>'شركة','car_showroom'=>'معرض سيارات','rental_company'=>'شركة تأجير','fleet'=>'أسطول'] as $value=>$label)<option value="{{ $value }}" @selected(old('customer_type',$customer->customer_type ?? 'individual')===$value)>{{ $label }}</option>@endforeach</x-form.select>
        <x-form.input name="name" label="الاسم" :value="$customer->name" required />
        <x-form.input name="company_name" label="اسم الشركة" :value="$customer->company_name" />
        <x-form.input name="phone" label="الهاتف" :value="$customer->phone" />
        <x-form.input name="alternative_phone" label="هاتف بديل" :value="$customer->alternative_phone" />
        <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="$customer->email" />
        <x-form.input name="tax_number" label="الرقم الضريبي" :value="$customer->tax_number" />
        <x-form.input name="commercial_registration" label="السجل التجاري" :value="$customer->commercial_registration" />
        <x-form.select name="preferred_language" label="اللغة المفضلة" required><option value="ar" @selected(old('preferred_language',$customer->preferred_language ?? 'ar')==='ar')>العربية</option><option value="en" @selected(old('preferred_language',$customer->preferred_language)==='en')>English</option></x-form.select>
        <x-form.input name="credit_limit" type="number" step="0.0001" min="0" label="الحد الائتماني" :value="$customer->credit_limit ?? 0" required />
        <x-form.input name="payment_term_days" type="number" min="0" label="أيام السداد" :value="$customer->payment_term_days ?? 0" required />
        <x-form.select name="status" label="الحالة" required>@foreach(['active'=>'نشط','inactive'=>'غير نشط','blocked'=>'محظور'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$customer->status ?? 'active')===$value)>{{ $label }}</option>@endforeach</x-form.select>
        <x-form.select name="source_id" label="المصدر"><option value="">—</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((string)old('source_id',$customer->source_id)===(string)$source->id)>{{ $source->name }}</option>@endforeach</x-form.select>
        <x-form.select name="assigned_branch_id" label="الفرع المسؤول" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('assigned_branch_id',$customer->assigned_branch_id)===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</x-form.select>
    </div>
    <label class="sw-check"><input type="checkbox" name="confirm_duplicate" value="1" @checked(old('confirm_duplicate'))> تأكيد المتابعة عند تشابه الهاتف</label>
    <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('customers.index') }}">إلغاء</a></div>
</form>
</x-card>
@endsection
