@extends('layouts.app')

@php
    $editing = $employee->exists;
    $selectedBranch = old('branch_id', $employee->branch_id ?: $selectedBranchId);
    $skillRows = old('skills');
    if ($skillRows === null) {
        $skillRows = $employee->serviceSkills->map(fn ($skill) => [
            'service_id' => $skill->service_id,
            'skill_level' => $skill->skill_level,
            'is_primary' => (int) $skill->is_primary,
            'is_active' => (int) $skill->is_active,
            'certified_at' => $skill->certified_at?->format('Y-m-d'),
            'certification_expires_at' => $skill->certification_expires_at?->format('Y-m-d'),
            'notes' => $skill->notes,
        ])->values()->all();
    }
    if (! $editing && $prefillServiceId && empty($skillRows)) {
        $skillRows = [[
            'service_id' => $prefillServiceId, 'skill_level' => 'intermediate',
            'is_primary' => 1, 'is_active' => 1, 'certified_at' => null,
            'certification_expires_at' => null, 'notes' => null,
        ]];
    }
    $serviceOptions = $services->map(fn ($service) => [
        'id' => $service->id,
        'label' => trim($service->name.' — '.($service->category?->name ?? ''), ' —'),
        'branches' => $service->branchServices->pluck('branch_id')->values()->all(),
    ])->values()->all();
    $employeeSkillsConfig = [
        'initialRows' => array_values($skillRows),
        'services' => $serviceOptions,
        'levels' => $skillLevels,
        'errors' => $errors->getBag('default')->toArray(),
    ];
@endphp

@section('title', $editing ? 'تعديل الموظف' : 'إضافة موظف أو فني')
@section('page-title', $editing ? 'تعديل الموظف' : 'إضافة موظف أو فني')
@section('breadcrumb', 'الإدارة / الموظفون والفنيون')

@section('content')
<div class="employee-page">
    <form method="POST" action="{{ $editing ? route('employees.update', $employee) : route('employees.store') }}" class="employee-form">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="return_url" value="{{ old('return_url', $returnUrl) }}">

        <x-card title="البيانات الأساسية">
            <div class="sw-form-grid employee-form-grid">
                <x-form.select name="branch_id" label="الفرع" required>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="employee_code" label="كود الموظف" :value="$employee->employee_code" required />
                <x-form.input name="name" label="الاسم" :value="$employee->name" required />
                <x-form.input name="job_title" label="المسمى الوظيفي" :value="$employee->job_title" required />
                <x-form.input name="phone" label="الهاتف" :value="$employee->phone" />
                <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="$employee->email" />
                <x-form.select name="employment_type" label="نوع التوظيف" required>
                    @foreach($employmentTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_type', $employee->employment_type ?: 'full_time') === $value)>{{ $label }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="hire_date" type="date" label="تاريخ التعيين" :value="$employee->hire_date?->format('Y-m-d') ?: now()->format('Y-m-d')" required />
                <x-form.select name="status" label="الحالة" required>
                    <option value="active" @selected(old('status', $employee->status ?: 'active') === 'active')>نشط</option>
                    <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>غير نشط</option>
                </x-form.select>
                <x-form.select name="user_id" label="حساب مستخدم مرتبط (اختياري)">
                    <option value="">بدون حساب نظام</option>
                    @foreach($users as $user)
                        @php($userBranchIds = $user->accessibleBranches->pluck('id')->push($user->branch_id)->filter()->unique()->implode(','))
                        <option value="{{ $user->id }}" data-branches="{{ $userBranchIds }}" @selected((string) old('user_id', $employee->user_id) === (string) $user->id)>
                            {{ $user->name }} — {{ $user->email }}
                        </option>
                    @endforeach
                </x-form.select>
            </div>
        </x-card>

        @if(auth()->user()->hasPermission('employees.manage_skills'))
            <input type="hidden" name="skills_managed" value="1">
            <x-card title="مهارات الخدمات">
                <p class="employee-form-help">يمكن إسناد الخدمات المتاحة في فرع الموظف فقط. المهارة المنتهية لا تؤهل الفني لأوامر العمل.</p>
                @if($prefillWarning)
                    <p class="employee-skills-warning" role="alert">{{ $prefillWarning }}</p>
                @endif
                <p class="employee-skills-warning" data-employee-skills-warning role="alert" hidden></p>
                <div
                    id="employee-skills"
                    class="employee-skills"
                    data-employee-skills
                    aria-live="polite"
                ></div>
                <div class="employee-skills-empty" data-employee-skills-empty hidden>
                    لم تتم إضافة مهارات خدمات لهذا الموظف.
                </div>
                <div class="employee-skills-toolbar">
                    <span>عدد المهارات الحالية: <strong data-employee-skills-count>0</strong></span>
                    <button
                        class="sw-button sw-button--outline"
                        type="button"
                        id="add-employee-skill"
                        data-add-employee-skill
                    >+ إضافة مهارة خدمة</button>
                </div>
                <script type="application/json" data-employee-skills-config>@json($employeeSkillsConfig)</script>
            </x-card>
        @endif

        <div class="sw-form-actions employee-form-actions">
            <x-button type="submit">حفظ الموظف</x-button>
            <a class="sw-button sw-button--outline" href="{{ $returnUrl ?: route('employees.index') }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
