@extends('layouts.app')
@php($editing = $vehicle->exists)
@section('title', $editing ? 'تعديل السيارة' : 'إضافة سيارة')
@section('page-title', $editing ? 'تعديل السيارة' : 'إضافة سيارة')
@section('breadcrumb', 'إدارة السيارات')
@section('content')
<x-card><form method="POST" action="{{ $editing ? route('vehicles.update',$vehicle) : route('vehicles.store') }}" class="sw-form">
    @csrf @if($editing) @method('PUT') @endif
    <div class="sw-form-grid">
        <x-form.select name="customer_id" label="العميل" required>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string)old('customer_id',$vehicle->customer_id)===(string)$customer->id)>{{ $customer->customer_code }} — {{ $customer->name }}</option>@endforeach</x-form.select>
        <x-form.select name="vehicle_brand_id" label="الماركة" required>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('vehicle_brand_id',$vehicle->vehicle_brand_id)===(string)$brand->id)>{{ $brand->name_ar }}</option>@endforeach</x-form.select>
        <x-form.select name="vehicle_model_id" label="الموديل" required>@foreach($models as $model)<option value="{{ $model->id }}" @selected((string)old('vehicle_model_id',$vehicle->vehicle_model_id)===(string)$model->id)>{{ $model->brand?->name_ar }} {{ $model->name_ar }}</option>@endforeach</x-form.select>
        <x-form.select name="vehicle_type_id" label="النوع"><option value="">—</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected((string)old('vehicle_type_id',$vehicle->vehicle_type_id)===(string)$type->id)>{{ $type->name }}</option>@endforeach</x-form.select>
        <x-form.select name="vehicle_size_id" label="الحجم"><option value="">—</option>@foreach($sizes as $size)<option value="{{ $size->id }}" @selected((string)old('vehicle_size_id',$vehicle->vehicle_size_id)===(string)$size->id)>{{ $size->name }}</option>@endforeach</x-form.select>
        <x-form.input name="manufacturing_year" type="number" min="1900" max="2200" label="سنة الصنع" :value="$vehicle->manufacturing_year" />
        <x-form.input name="color" label="اللون" :value="$vehicle->color" />
        <x-form.input name="plate_number" label="رقم اللوحة" :value="$vehicle->plate_number" />
        <x-form.input name="vin" label="VIN" :value="$vehicle->vin" />
        <x-form.input name="odometer" type="number" min="0" label="العداد" :value="$vehicle->odometer" />
        <x-form.select name="status" label="الحالة" required>@foreach(['active'=>'نشطة','inactive'=>'غير نشطة','sold'=>'مباعة','archived'=>'مؤرشفة'] as $value=>$label)<option value="{{ $value }}" @selected(old('status',$vehicle->status ?? 'active')===$value)>{{ $label }}</option>@endforeach</x-form.select>
        <x-form.textarea name="notes" label="ملاحظات">{{ $vehicle->notes }}</x-form.textarea>
    </div><div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('vehicles.index') }}">إلغاء</a></div>
</form></x-card>
@endsection
