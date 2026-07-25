@extends('layouts.app')
@php($editing = $branch->exists)
@section('title', $editing ? 'تعديل الفرع' : 'إضافة فرع')
@section('page-title', $editing ? 'تعديل الفرع' : 'إضافة فرع')
@section('breadcrumb', 'الإعدادات / الفروع')
@section('content')
<x-card>
    <form method="POST" action="{{ $editing ? route('branches.update', $branch) : route('branches.store') }}" class="sw-form">
        @csrf @if($editing) @method('PUT') @endif
        <div class="sw-form-grid">
            <x-form.input name="code" label="كود الفرع" :value="$branch->code" required />
            <x-form.input name="name" label="اسم الفرع" :value="$branch->name" required />
            <x-form.input name="commercial_name" label="الاسم التجاري" :value="$branch->commercial_name" />
            <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="$branch->email" />
            <x-form.input name="phone" label="الهاتف" :value="$branch->phone" />
            <x-form.input name="tax_number" label="الرقم الضريبي" :value="$branch->tax_number" />
            <x-form.input name="latitude" type="number" step="0.0000001" label="خط العرض" :value="$branch->latitude" />
            <x-form.input name="longitude" type="number" step="0.0000001" label="خط الطول" :value="$branch->longitude" />
            <x-form.textarea name="address" label="العنوان">{{ $branch->address }}</x-form.textarea>
        </div>
        <label class="sw-check"><input type="checkbox" name="is_main" value="1" @checked(old('is_main', $branch->is_main))> فرع رئيسي</label>
        <label class="sw-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $branch->is_active : true))> نشط</label>
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('branches.index') }}">إلغاء</a></div>
    </form>
</x-card>
@endsection
