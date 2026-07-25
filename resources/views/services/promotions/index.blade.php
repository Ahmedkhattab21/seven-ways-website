@extends('layouts.app')
@section('title', 'العروض الترويجية')
@section('page-title', 'العروض الترويجية')
@section('breadcrumb', 'الخدمات / العروض')
@section('page-actions')@if(auth()->user()->hasPermission('promotions.manage'))<a class="sw-button sw-button--primary" href="{{ route('promotions.create') }}">إضافة عرض</a>@endif@endsection
@section('content')
<x-alert type="warning">هذه عروض Foundation فقط؛ لا يتم تطبيقها على أي مستند أو تسجيل استخدام فعلي.</x-alert>
<x-table-shell><thead><tr><th>الكود</th><th>العرض</th><th>النوع</th><th>الخصم</th><th>الفترة</th><th>الروابط</th><th>الحالة</th><th></th></tr></thead><tbody>@forelse($promotions as $promotion)<tr><td>{{ $promotion->code }}</td><td>{{ $promotion->name }}</td><td>{{ $promotion->promotion_type }}</td><td>{{ $promotion->discount_value }} {{ $promotion->discount_type }}</td><td>{{ $promotion->start_at->format('Y-m-d') }} — {{ $promotion->end_at->format('Y-m-d') }}</td><td>{{ $promotion->services_count + $promotion->packages_count + $promotion->branches_count }}</td><td><x-status-badge :status="$promotion->is_active ? 'active' : 'inactive'" /></td><td>@if(auth()->user()->hasPermission('promotions.manage'))<a href="{{ route('promotions.edit',$promotion) }}">تعديل</a>@endif</td></tr>@empty<tr><td colspan="8">لا توجد عروض.</td></tr>@endforelse</tbody><x-slot:footer>{{ $promotions->links() }}</x-slot:footer></x-table-shell>
@endsection
