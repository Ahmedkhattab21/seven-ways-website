@extends('layouts.app')
@section('title', 'عرض السيارة')
@section('page-title', $vehicle->plate_number ?? $vehicle->vin ?? 'سيارة')
@section('breadcrumb', 'إدارة السيارات / عرض')
@section('page-actions')@can('update',$vehicle)<a class="sw-button sw-button--primary" href="{{ route('vehicles.edit',$vehicle) }}">تعديل</a>@endcan @endsection
@section('content')
<x-card title="بيانات السيارة"><dl class="sw-details-grid">
    <div><dt>العميل</dt><dd><a href="{{ route('customers.show',$vehicle->customer) }}">{{ $vehicle->customer->name }}</a></dd></div><div><dt>الماركة</dt><dd>{{ $vehicle->brand->name_ar }}</dd></div>
    <div><dt>الموديل</dt><dd>{{ $vehicle->model->name_ar }}</dd></div><div><dt>السنة</dt><dd>{{ $vehicle->manufacturing_year ?? '—' }}</dd></div>
    <div><dt>اللوحة</dt><dd>{{ $vehicle->plate_number ?? '—' }}</dd></div><div><dt>VIN</dt><dd>{{ $vehicle->vin ?? '—' }}</dd></div>
    <div><dt>العداد</dt><dd>{{ $vehicle->odometer ?? '—' }}</dd></div><div><dt>الحالة</dt><dd><x-status-badge :status="$vehicle->status" /></dd></div>
</dl></x-card>
@can('transfer',$vehicle)<x-card title="نقل الملكية"><form method="POST" action="{{ route('vehicles.transfer',$vehicle) }}" class="sw-form">@csrf<div class="sw-form-grid"><x-form.select name="customer_id" label="العميل الجديد" required>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->customer_code }} — {{ $customer->name }}</option>@endforeach</x-form.select><x-form.input name="transferred_at" type="datetime-local" label="تاريخ النقل" /><x-form.input name="reason" label="السبب" /><x-form.textarea name="notes" label="ملاحظات"></x-form.textarea></div><x-button type="submit">نقل الملكية</x-button></form></x-card>@endcan
<x-card title="سجل الملكية"><x-table-shell><thead><tr><th>من</th><th>إلى</th><th>التاريخ</th><th>السبب</th></tr></thead><tbody>@forelse($vehicle->ownershipHistory as $history)<tr><td>{{ $history->fromCustomer?->name ?? '—' }}</td><td>{{ $history->toCustomer->name }}</td><td>{{ $history->transferred_at->format('Y-m-d H:i') }}</td><td>{{ $history->reason ?? '—' }}</td></tr>@empty<tr><td colspan="4">لا توجد عمليات نقل.</td></tr>@endforelse</tbody></x-table-shell></x-card>
<x-card title="المرفقات"><ul>@forelse($vehicle->attachments as $attachment)<li><a href="{{ route('attachments.download',$attachment) }}">{{ $attachment->original_name }}</a>@can('delete',$attachment)<form method="POST" action="{{ route('attachments.destroy',$attachment) }}">@csrf @method('DELETE')<button type="submit">حذف</button></form>@endcan</li>@empty<li>لا توجد مرفقات.</li>@endforelse</ul>
@if(auth()->user()->hasPermission('vehicles.manage_attachments'))<form method="POST" enctype="multipart/form-data" action="{{ route('vehicles.attachments.store',$vehicle) }}" class="sw-form">@csrf<x-form.input name="file" type="file" label="صورة أو PDF" required /><x-form.select name="category" label="التصنيف"><option value="vehicle_photo">صورة السيارة</option><option value="vehicle_registration">استمارة</option><option value="insurance">تأمين</option><option value="other">أخرى</option></x-form.select><x-button type="submit">رفع</x-button></form>@endif</x-card>
<x-card title="سجل الخدمات"><x-empty-state title="لا يوجد سجل خدمات بعد" message="سيظهر السجل لاحقًا من أوامر العمل والفواتير والضمانات." /></x-card>
@endsection
