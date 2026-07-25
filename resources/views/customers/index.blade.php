@extends('layouts.app')
@section('title', 'العملاء')
@section('page-title', 'العملاء')
@section('breadcrumb', 'إدارة العملاء / القائمة')
@section('page-actions')
@if(auth()->user()->hasPermission('customers.create'))<a class="sw-button sw-button--primary" href="{{ route('customers.create') }}">إضافة عميل</a>@endif
@endsection
@section('content')
<x-card title="البحث والفلاتر">
    <form method="GET" class="sw-form"><div class="sw-form-grid">
        <x-form.input name="search" label="بحث" :value="request('search')" placeholder="الاسم أو الهاتف أو الكود أو الرقم الضريبي" />
        <x-form.select name="type" label="النوع"><option value="">الكل</option>@foreach(['individual'=>'فرد','company'=>'شركة','car_showroom'=>'معرض سيارات','rental_company'=>'شركة تأجير','fleet'=>'أسطول'] as $value=>$label)<option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>@endforeach</x-form.select>
        <x-form.select name="status" label="الحالة"><option value="">الكل</option>@foreach(['active'=>'نشط','inactive'=>'غير نشط','blocked'=>'محظور'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</x-form.select>
        <x-form.select name="branch_id" label="الفرع المسؤول"><option value="">الكل</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)request('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</x-form.select>
        <x-form.select name="source_id" label="المصدر"><option value="">الكل</option>@foreach($sources as $source)<option value="{{ $source->id }}" @selected((string)request('source_id')===(string)$source->id)>{{ $source->name }}</option>@endforeach</x-form.select>
    </div><div class="sw-form-actions"><x-button type="submit">تطبيق</x-button><a class="sw-button sw-button--outline" href="{{ route('customers.index') }}">مسح</a></div></form>
</x-card>
<x-table-shell>
    <thead><tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>الهاتف</th><th>الفرع المسؤول</th><th>السيارات</th><th>آخر تواصل</th><th>الحالة</th></tr></thead>
    <tbody>@forelse($customers as $customer)<tr>
        <td><a href="{{ route('customers.show', $customer) }}">{{ $customer->customer_code }}</a></td>
        <td>{{ $customer->name }}</td><td>{{ $customer->customer_type }}</td><td>{{ $customer->phone ?? '—' }}</td>
        <td>{{ $customer->assignedBranch?->name ?? '—' }}</td><td>{{ $customer->vehicles_count }}</td>
        <td>{{ $customer->last_contact_at?->format('Y-m-d') ?? '—' }}</td><td><x-status-badge :status="$customer->status" /></td>
    </tr>@empty<tr><td colspan="8">لا يوجد عملاء.</td></tr>@endforelse</tbody>
    <x-slot:footer>{{ $customers->links() }}</x-slot:footer>
</x-table-shell>
@endsection
