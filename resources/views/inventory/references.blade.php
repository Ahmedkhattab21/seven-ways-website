@extends('layouts.app')
@php($title = $section === 'categories' ? 'تصنيفات المنتجات' : 'العلامات التجارية')
@section('title', $title)
@section('page-title', $title)
@section('breadcrumb', 'المخزون / '.$title)
@section('content')
<x-catalog-navigation :active="$section === 'categories' ? 'product-categories' : 'product-brands'" />
<x-card title="إضافة">
<form class="sw-form" method="POST" action="{{ route('product-references.store', $section) }}">@csrf
<div class="sw-form-grid"><x-form.input name="code" label="الكود" required /><x-form.input name="name" label="الاسم" required />
@if($section === 'categories')<x-form.select name="parent_id" label="التصنيف الأب"><option value="">—</option>@foreach($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</x-form.select>
@else<x-form.input name="country_code" label="كود الدولة" /><x-form.input name="website" label="الموقع" />@endif
</div><div class="sw-form-actions"><x-button type="submit">حفظ</x-button></div></form>
</x-card>
<x-table-shell><thead><tr><th>الكود</th><th>الاسم</th><th>الحالة</th></tr></thead><tbody>
@forelse($records as $record)<tr><td>{{ $record->code }}</td><td>{{ $record->name }}</td><td><x-status-badge :status="$record->is_active ? 'active' : 'inactive'" /></td></tr>@empty<tr><td colspan="3">لا توجد بيانات.</td></tr>@endforelse
</tbody></x-table-shell>
@endsection
