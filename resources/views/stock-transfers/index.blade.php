@extends('layouts.app')
@section('title', 'تحويلات المخزون')
@section('page-title', 'تحويلات المخزون')
@section('breadcrumb', 'المخزون / التحويلات')
@section('page-actions')
@if(auth()->user()->hasPermission('stock_transfers.create'))<a class="sw-button sw-button--primary" href="{{ route('stock-transfers.create') }}">طلب تحويل</a>@endif
@endsection
@section('content')
<div class="sw-stats">
@foreach(['pending_approval'=>'بانتظار الاعتماد','preparation'=>'بانتظار التجهيز','in_transit'=>'قيد النقل/الاستلام','discrepancies'=>'بها فروق','late'=>'متأخرة'] as $key=>$label)
<x-card :title="$label"><strong>{{ $summary[$key] }}</strong></x-card>
@endforeach
</div>
<x-card title="الفلاتر"><form method="GET" class="sw-form"><div class="sw-form-grid">
<x-form.select name="status" label="الحالة"><option value="">الكل</option>@foreach(['draft','pending_approval','approved','ready_to_ship','shipped','partially_received','received','cancelled','reversed'] as $value)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $value }}</option>@endforeach</x-form.select>
<x-form.select name="transfer_type" label="النوع"><option value="">الكل</option><option value="internal">داخلي</option><option value="inter_branch">بين الفروع</option></x-form.select>
<x-form.select name="from_branch_id" label="فرع المصدر"><option value="">الكل</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.select name="to_branch_id" label="فرع الوجهة"><option value="">الكل</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
</div><div class="sw-form-actions"><x-button type="submit">تطبيق</x-button></div></form></x-card>
<x-table-shell><thead><tr><th>الرقم</th><th>من</th><th>إلى</th><th>الحالة</th><th>العناصر</th><th>الطالب</th><th>الطلب</th><th>الشحن</th><th>الاستلام</th></tr></thead>
<tbody>@forelse($transfers as $transfer)<tr>
<td><a href="{{ route('stock-transfers.show', $transfer) }}">{{ $transfer->transfer_number }}</a></td>
<td>{{ $transfer->fromBranch?->name }} / {{ $transfer->fromWarehouse?->name }}</td><td>{{ $transfer->toBranch?->name }} / {{ $transfer->toWarehouse?->name }}</td>
<td><x-status-badge :status="$transfer->status" /></td><td>{{ $transfer->items_count }}</td><td>{{ $transfer->requester?->name }}</td>
<td>{{ $transfer->requested_at?->format('Y-m-d') }}</td><td>{{ $transfer->shipped_at?->format('Y-m-d') ?? '—' }}</td><td>{{ $transfer->received_at?->format('Y-m-d') ?? '—' }}</td>
</tr>@empty<tr><td colspan="9">لا توجد تحويلات.</td></tr>@endforelse</tbody><x-slot:footer>{{ $transfers->links() }}</x-slot:footer></x-table-shell>
@endsection
