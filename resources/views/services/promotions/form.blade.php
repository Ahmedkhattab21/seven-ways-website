@extends('layouts.app')
@section('title', $promotion->exists ? 'تعديل عرض ترويجي' : 'إضافة عرض ترويجي')
@section('page-title', $promotion->exists ? 'تعديل عرض ترويجي' : 'إضافة عرض ترويجي')
@section('breadcrumb', 'الخدمات / العروض الترويجية')
@section('content')
<x-card title="بيانات العرض الترويجي">
    <form class="sw-form" method="POST" action="{{ $promotion->exists ? route('promotions.update', $promotion) : route('promotions.store') }}">
        @csrf
        @if($promotion->exists) @method('PUT') @endif
        <div class="sw-form-grid">
            <x-form.input name="code" label="الكود (تلقائي عند تركه فارغًا)" :value="old('code', $promotion->code)" />
            <x-form.input name="name" label="الاسم" :value="old('name', $promotion->name)" required />
            <x-form.select name="promotion_type" label="نوع العرض">
                @foreach(['service' => 'خدمة', 'package' => 'باقة', 'product' => 'منتج', 'general' => 'عام'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('promotion_type', $promotion->promotion_type) === $value)>{{ $label }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="discount_type" label="نوع الخصم">
                @foreach(['fixed' => 'قيمة ثابتة', 'percentage' => 'نسبة مئوية', 'fixed_price' => 'سعر نهائي ثابت'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('discount_type', $promotion->discount_type) === $value)>{{ $label }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="number" step="0.0001" name="discount_value" label="قيمة الخصم أو السعر النهائي" :value="old('discount_value', $promotion->discount_value)" min="0.0001" required />
            <x-form.input type="datetime-local" name="start_at" label="البداية" :value="old('start_at', $promotion->start_at?->format('Y-m-d\\TH:i'))" required />
            <x-form.input type="datetime-local" name="end_at" label="النهاية" :value="old('end_at', $promotion->end_at?->format('Y-m-d\\TH:i'))" required />
        </div>
        <x-form.textarea name="description" label="الوصف">{{ old('description', $promotion->description) }}</x-form.textarea>

        <h3>الفروع</h3>
        @foreach($branches as $branch)
            <label class="sw-check"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked($promotion->exists && $promotion->branches->contains($branch))><span class="sw-check__box"></span><span>{{ $branch->name }}</span></label>
        @endforeach
        <h3>الخدمات</h3>
        @foreach($services as $service)
            <label class="sw-check"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked($promotion->exists && $promotion->services->contains($service))><span class="sw-check__box"></span><span>{{ $service->name }}</span></label>
        @endforeach
        <h3>الباقات</h3>
        @foreach($packages as $package)
            <label class="sw-check"><input type="checkbox" name="package_ids[]" value="{{ $package->id }}" @checked($promotion->exists && $promotion->packages->contains($package))><span class="sw-check__box"></span><span>{{ $package->name }}</span></label>
        @endforeach
        <h3>المنتجات</h3>
        @foreach($products as $product)
            <label class="sw-check"><input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked($promotion->exists && $promotion->products->contains($product))><span class="sw-check__box"></span><span>{{ $product->sku }} — {{ $product->name }}</span></label>
        @endforeach
        <input type="hidden" name="is_active" value="0">
        <x-form.checkbox name="is_active" label="نشط" :checked="old('is_active', $promotion->is_active)" />
        <div class="sw-form-actions"><x-button type="submit">حفظ العرض</x-button></div>
    </form>
</x-card>
@endsection
