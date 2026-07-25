@extends('layouts.app')
@section('title', 'إضافة مخزن')
@section('page-title', 'إضافة مخزن')
@section('breadcrumb', 'المخزون / المخازن')
@section('content')
<x-card title="بيانات المخزن"><form class="sw-form" method="POST" action="{{ route('warehouses.store') }}">@csrf
<div class="sw-form-grid">
<x-form.input name="code" label="الكود" required /><x-form.input name="name" label="الاسم" required />
<x-form.select name="branch_id" label="الفرع">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.select name="warehouse_type" label="النوع">@foreach(['main','workshop','damaged','quarantine','other'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</x-form.select>
<x-form.input name="address" label="العنوان" />
</div>
@foreach(['is_main'=>'المخزن الرئيسي','allows_sale_issue'=>'يسمح بصرف البيع','allows_work_order_issue'=>'يسمح بصرف أوامر العمل','allows_damaged_stock'=>'يسمح بالمخزون التالف'] as $field=>$label)<input type="hidden" name="{{ $field }}" value="0"><label><input type="checkbox" name="{{ $field }}" value="1"> {{ $label }}</label>@endforeach
<div class="sw-form-actions"><x-button type="submit">حفظ</x-button></div></form></x-card>
@endsection
