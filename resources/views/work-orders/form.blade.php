@extends('layouts.app')
@section('title','أمر عمل جديد')
@section('breadcrumb','أمر عمل جديد')
@section('page-title','إنشاء أمر عمل')
@section('content')
<form class="sw-card" method="POST" action="{{ route('work-orders.store') }}">@csrf
    <label>المصدر<select name="source" required><option value="appointment">موعد تم تسجيل وصوله</option><option value="direct">دخول مباشر</option></select></label>
    <label>الموعد<select name="appointment_id"><option value="">—</option>@foreach($appointments as $appointment)<option value="{{ $appointment->id }}">{{ $appointment->appointment_number }}</option>@endforeach</select></label>
    <label>الفرع<select name="branch_id"><option value="">—</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
    <label>المخزن<select name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label>
    <label>العميل<select name="customer_id"><option value="">—</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></label>
    <label>السيارة<select name="vehicle_id"><option value="">—</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}">{{ $vehicle->plate_number ?: $vehicle->vin }}</option>@endforeach</select></label>
    <h2>خدمة الدخول المباشر</h2>
    <select name="services[0][service_id]">@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>
    <input name="services[0][description]" placeholder="وصف الخدمة"><input type="number" step="0.000001" name="services[0][quantity]" value="1">
    <input type="number" name="services[0][planned_duration_minutes]" placeholder="المدة بالدقائق"><input type="number" step="0.0001" name="services[0][unit_price_snapshot]" placeholder="السعر">
    <button class="sw-btn sw-btn--primary">إنشاء</button>
</form>
@endsection
