@extends('layouts.app')
@section('title','عروض الأسعار')
@section('content')
@php
    $statusLabels = [
        'draft' => 'مسودة',
        'pending_approval' => 'في انتظار الاعتماد',
        'approved' => 'معتمد',
        'sent' => 'مُرسل للعميل',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'expired' => 'منتهي',
        'converted' => 'تم تحويله',
        'cancelled' => 'ملغي',
        'superseded' => 'مستبدل بإصدار أحدث',
    ];
@endphp
<div class="configuration-page quotations-index-layout">
<div class="sw-page-header quotations-index-header"><div><h1>عروض الأسعار</h1><p>Snapshots ثابتة لدورة ما قبل التنفيذ.</p></div>@if(auth()->user()->hasPermission('quotations.create'))<a class="sw-btn sw-btn--primary" href="{{ route('quotations.create') }}">إضافة عرض سعر</a>@endif</div>
<form class="sw-card sw-form quotation-filter-form" method="GET"><select name="status"><option value="">كل الحالات</option>@foreach($statusLabels as $status => $label)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $label }}</option>@endforeach</select><select name="branch_id"><option value="">كل الفروع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select><button class="sw-btn">تصفية</button></form>
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>الرقم/الإصدار</th><th>العميل</th><th>السيارة</th><th>الفرع</th><th>الإجمالي</th><th>الحالة</th><th>الصلاحية</th></tr></thead><tbody>@forelse($quotations as $quotation)<tr><td><a href="{{ route('quotations.show',$quotation) }}">{{ $quotation->quotation_number }} / V{{ $quotation->version_number }}</a></td><td>{{ $quotation->customer->name }}</td><td>{{ $quotation->vehicle->plate_number ?: $quotation->vehicle->vin }}</td><td>{{ $quotation->branch->name }}</td><td>{{ number_format($quotation->total,2) }}</td><td>{{ $statusLabels[$quotation->status] ?? $quotation->status }}</td><td>{{ $quotation->valid_until->format('Y-m-d') }}</td></tr>@empty<tr><td colspan="7">لا توجد عروض.</td></tr>@endforelse</tbody></table></div>{{ $quotations->links() }}
</div>
@endsection
