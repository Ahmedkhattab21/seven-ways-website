@extends('layouts.app')
@section('title','الموردون') @section('page-title','الموردون')
@section('content')
<div class="sw-page-actions">@can('create',App\Models\Supplier::class)<a class="sw-btn" href="{{ route('suppliers.create') }}">إضافة مورد</a>@endcan</div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>الهاتف</th><th>الحالة</th></tr></thead><tbody>
@forelse($suppliers as $supplier)<tr><td><a href="{{ route('suppliers.show',$supplier) }}">{{ $supplier->supplier_code }}</a></td><td>{{ $supplier->name }}</td><td>{{ $supplier->supplier_type }}</td><td>{{ $supplier->phone }}</td><td>{{ $supplier->status }}</td></tr>@empty<tr><td colspan="5">لا يوجد موردون.</td></tr>@endforelse
</tbody></table>{{ $suppliers->links() }}</div>
@endsection
