@extends('layouts.app')

@section('title', 'الموظفون والفنيون')
@section('page-title', 'الموظفون والفنيون')
@section('breadcrumb', 'الإدارة / الموظفون والفنيون')

@section('page-actions')
    @if(auth()->user()->hasPermission('employees.create'))
        <a class="sw-button sw-button--primary" href="{{ route('employees.create') }}">إضافة موظف أو فني</a>
    @endif
@endsection

@section('content')
<div class="employee-page">
    <x-card title="البحث والتصفية">
        <form method="GET" class="sw-form">
            <div class="sw-form-grid employee-filter-grid">
                <x-form.input name="search" label="بحث" :value="request('search')" placeholder="الكود أو الاسم أو الهاتف أو المسمى الوظيفي" />
                <x-form.select name="branch_id" label="الفرع">
                    <option value="">كل الفروع</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="service_id" label="الخدمة المؤهل لها">
                    <option value="">كل الخدمات</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected((string) request('service_id') === (string) $service->id)>{{ $service->code }} — {{ $service->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="status" label="الحالة">
                    <option value="">كل الحالات</option>
                    <option value="active" @selected(request('status') === 'active')>نشط</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
                </x-form.select>
                <x-form.select name="employment_type" label="نوع التوظيف">
                    <option value="">كل الأنواع</option>
                    @foreach(['full_time' => 'دوام كامل', 'part_time' => 'دوام جزئي', 'contract' => 'عقد', 'temporary' => 'مؤقت', 'intern' => 'متدرب'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('employment_type') === $value)>{{ $label }}</option>
                    @endforeach
                </x-form.select>
                <x-form.select name="has_skills" label="مهارات الخدمات">
                    <option value="">الكل</option>
                    <option value="yes" @selected(request('has_skills') === 'yes')>لديه مهارات نشطة</option>
                    <option value="no" @selected(request('has_skills') === 'no')>بدون مهارات نشطة</option>
                </x-form.select>
            </div>
            <div class="sw-form-actions">
                <x-button type="submit">تطبيق</x-button>
                <a class="sw-button sw-button--outline" href="{{ route('employees.index') }}">مسح</a>
            </div>
        </form>
    </x-card>

    <x-table-shell>
        <thead>
            <tr>
                <th>الكود</th>
                <th>الاسم</th>
                <th>المسمى الوظيفي</th>
                <th>نوع التوظيف</th>
                <th>الفرع</th>
                <th>الهاتف</th>
                <th>حساب النظام</th>
                <th>المهارات النشطة</th>
                <th>الحالة</th>
                <th>الإجراء</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $employee)
                <tr>
                    <td><a href="{{ route('employees.show', $employee) }}">{{ $employee->employee_code }}</a></td>
                    <td>{{ $employee->name }}</td>
                    <td>{{ $employee->job_title }}</td>
                    <td>{{ ['full_time' => 'دوام كامل', 'part_time' => 'دوام جزئي', 'contract' => 'عقد', 'temporary' => 'مؤقت', 'intern' => 'متدرب'][$employee->employment_type] ?? '—' }}</td>
                    <td>{{ $employee->branch?->name }}</td>
                    <td>{{ $employee->phone ?: '—' }}</td>
                    <td>{{ $employee->user?->email ?: 'غير مرتبط' }}</td>
                    <td>{{ $employee->active_skills_count }}</td>
                    <td><x-status-badge :status="$employee->status" /></td>
                    <td>
                        @if(auth()->user()->hasPermission('employees.update'))
                            <a class="sw-button sw-button--outline" href="{{ route('employees.edit', $employee) }}">تعديل</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">لا يوجد موظفون أو فنيون مطابقون.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>{{ $employees->links() }}</x-slot:footer>
    </x-table-shell>
</div>
@endsection
