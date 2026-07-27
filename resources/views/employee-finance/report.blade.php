@extends('layouts.app')
@section('title', 'تقارير مالية الموظفين')
@section('page-title', 'تقارير مالية الموظفين')
@section('content')
<div class="sw-card">
    <h2>{{ $report }}</h2>
    <form method="GET" class="sw-form-grid">
        <input name="branch_id" type="number" value="{{ $filters['branch_id'] ?? '' }}" placeholder="رقم الفرع">
        <input name="employee_id" type="number" value="{{ $filters['employee_id'] ?? '' }}" placeholder="رقم الموظف">
        <input name="status" value="{{ $filters['status'] ?? '' }}" placeholder="الحالة">
        <input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
        <input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
        <button class="sw-btn">تطبيق</button>
    </form>
    <table class="sw-table">
        @if($rows->isNotEmpty())
        <thead><tr>@foreach(array_keys((array) $rows->first()) as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>@foreach($rows as $row)<tr>@foreach((array) $row as $value)<td>{{ $value }}</td>@endforeach</tr>@endforeach</tbody>
        @else
        <tbody><tr><td>لا توجد بيانات مطابقة.</td></tr></tbody>
        @endif
    </table>
</div>
@endsection
