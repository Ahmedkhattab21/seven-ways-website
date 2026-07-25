@extends('layouts.app')
@php($editing = $lead->exists)
@section('title', $editing ? 'تعديل العميل المحتمل' : 'إضافة عميل محتمل')
@section('page-title', $editing ? 'تعديل العميل المحتمل' : 'إضافة عميل محتمل')
@section('breadcrumb', 'CRM')
@section('content')
<x-card><form method="POST" action="{{ $editing ? route('leads.update',$lead) : route('leads.store') }}" class="sw-form">@csrf @if($editing) @method('PUT') @endif
<div class="sw-form-grid">
    <x-form.input name="name" label="الاسم" :value="$lead->name" required /><x-form.input name="phone" label="الهاتف" :value="$lead->phone" required /><x-form.input name="email" type="email" label="البريد" :value="$lead->email" />
    <x-form.select name="vehicle_brand_id" label="الماركة"><option value="">—</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('vehicle_brand_id',$lead->vehicle_brand_id)===(string)$brand->id)>{{ $brand->name_ar }}</option>@endforeach</x-form.select>
    <x-form.select name="vehicle_model_id" label="الموديل"><option value="">—</option>@foreach($models as $model)<option value="{{ $model->id }}" @selected((string)old('vehicle_model_id',$lead->vehicle_model_id)===(string)$model->id)>{{ $model->name_ar }}</option>@endforeach</x-form.select>
    <x-form.input name="vehicle_year" type="number" min="1900" max="2200" label="سنة السيارة" :value="$lead->vehicle_year" />
    <x-form.select name="source_id" label="المصدر"><option value="">—</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((string)old('source_id',$lead->source_id)===(string)$source->id)>{{ $source->name }}</option>@endforeach</x-form.select>
    <x-form.select name="status" label="الحالة" required>@foreach(['new','contacted','qualified','proposal_requested','follow_up','lost','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status',$lead->status ?? 'new')===$status)>{{ $status }}</option>@endforeach</x-form.select>
    <x-form.select name="priority" label="الأولوية" required>@foreach(['low','normal','high','urgent'] as $priority)<option value="{{ $priority }}" @selected(old('priority',$lead->priority ?? 'normal')===$priority)>{{ $priority }}</option>@endforeach</x-form.select>
    <x-form.select name="assigned_to" label="المسؤول"><option value="">—</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('assigned_to',$lead->assigned_to)===(string)$user->id)>{{ $user->name }}</option>@endforeach</x-form.select>
    <x-form.input name="next_follow_up_at" type="datetime-local" label="المتابعة القادمة" :value="$lead->next_follow_up_at?->format('Y-m-d\\TH:i')" />
    <x-form.textarea name="requested_service_text" label="الخدمة المطلوبة">{{ $lead->requested_service_text }}</x-form.textarea><x-form.textarea name="lost_reason" label="سبب الخسارة">{{ $lead->lost_reason }}</x-form.textarea>
</div><div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('leads.index') }}">إلغاء</a></div></form></x-card>
@endsection
