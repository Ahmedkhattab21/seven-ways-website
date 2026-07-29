@extends('layouts.app')

@section('title', $employee->name)
@section('page-title', $employee->name)
@section('breadcrumb', 'الإدارة / الموظفون والفنيون')

@section('page-actions')
    @if(auth()->user()->hasPermission('employees.update'))
        <a class="sw-button sw-button--primary" href="{{ route('employees.edit', $employee) }}">تعديل الموظف</a>
    @endif
@endsection

@section('content')
<div class="employee-page">
    <x-card title="بيانات الموظف">
        <div class="employee-details-grid">
            <div><span>الكود</span><strong>{{ $employee->employee_code }}</strong></div>
            <div><span>الاسم</span><strong>{{ $employee->name }}</strong></div>
            <div><span>المسمى الوظيفي</span><strong>{{ $employee->job_title }}</strong></div>
            <div><span>الفرع</span><strong>{{ $employee->branch?->name }}</strong></div>
            <div><span>الهاتف</span><strong>{{ $employee->phone ?: '—' }}</strong></div>
            <div><span>البريد</span><strong>{{ $employee->email ?: '—' }}</strong></div>
            <div><span>حساب النظام</span><strong>{{ $employee->user?->email ?: 'غير مرتبط' }}</strong></div>
            <div><span>تاريخ التعيين</span><strong>{{ $employee->hire_date?->format('Y-m-d') }}</strong></div>
            <div><span>الحالة</span><x-status-badge :status="$employee->status" /></div>
        </div>
    </x-card>

    <x-table-shell title="مهارات الخدمات">
        <thead>
            <tr>
                <th>الخدمة</th>
                <th>المستوى</th>
                <th>رئيسية</th>
                <th>تاريخ الاعتماد</th>
                <th>انتهاء الاعتماد</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employee->serviceSkills as $skill)
                <tr>
                    <td>{{ $skill->service?->code }} — {{ $skill->service?->name }}</td>
                    <td>{{ ['trainee' => 'متدرب', 'junior' => 'مبتدئ', 'intermediate' => 'متوسط', 'senior' => 'متقدم', 'expert' => 'خبير'][$skill->skill_level] ?? $skill->skill_level }}</td>
                    <td>{{ $skill->is_primary ? 'نعم' : 'لا' }}</td>
                    <td>{{ $skill->certified_at?->format('Y-m-d') ?: '—' }}</td>
                    <td>{{ $skill->certification_expires_at?->format('Y-m-d') ?: 'بدون انتهاء' }}</td>
                    <td>{{ ! $skill->is_active ? 'معطلة' : ($skill->certification_expired ? 'منتهية' : 'سارية') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">لا توجد مهارات مسجلة لهذا الموظف.</td></tr>
            @endforelse
        </tbody>
    </x-table-shell>

    @if($employee->status === 'active' && auth()->user()->hasPermission('employees.disable'))
        <form method="POST" action="{{ route('employees.disable', $employee) }}" class="employee-disable-form">
            @csrf @method('PATCH')
            <button class="sw-button sw-button--danger" type="submit">تعطيل الموظف</button>
        </form>
    @endif
</div>
@endsection
