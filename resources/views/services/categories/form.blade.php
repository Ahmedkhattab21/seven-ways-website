@extends('layouts.app')
@section('title', $serviceCategory->exists ? 'تعديل تصنيف خدمة' : 'إضافة تصنيف خدمة')
@section('page-title', $serviceCategory->exists ? 'تعديل تصنيف خدمة' : 'إضافة تصنيف خدمة')
@section('breadcrumb', 'الخدمات / التصنيفات')
@section('content')
<x-card title="بيانات التصنيف">
<form class="sw-form" method="POST" action="{{ $serviceCategory->exists ? route('service-categories.update', $serviceCategory) : route('service-categories.store') }}">
@csrf @if($serviceCategory->exists) @method('PUT') @endif
<div class="sw-form-grid">
    <x-form.input name="code" label="الكود" :value="old('code', $serviceCategory->code)" required />
    <x-form.input name="name" label="الاسم" :value="old('name', $serviceCategory->name)" required />
    <x-form.select name="parent_id" label="التصنيف الأب"><option value="">تصنيف رئيسي</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $serviceCategory->parent_id)==$parent->id)>{{ $parent->name }}</option>@endforeach</x-form.select>
    <x-form.select name="icon" label="الأيقونة"><option value="">بدون</option>@foreach(['wrench','shield','car','sparkles','window','tools'] as $icon)<option value="{{ $icon }}" @selected(old('icon', $serviceCategory->icon)===$icon)>{{ $icon }}</option>@endforeach</x-form.select>
    <x-form.input type="number" name="sort_order" label="الترتيب" :value="old('sort_order', $serviceCategory->sort_order ?? 0)" min="0" />
</div>
<x-form.textarea name="description" label="الوصف">{{ old('description', $serviceCategory->description) }}</x-form.textarea>
<input type="hidden" name="is_active" value="0"><x-form.checkbox name="is_active" label="نشط" :checked="old('is_active', $serviceCategory->exists ? $serviceCategory->is_active : true)" />
<div class="sw-form-actions"><x-button type="submit">حفظ</x-button></div>
</form></x-card>
@endsection
