@extends('layouts.app')
@php
    $editing = $user->exists;
    $selectedBranches = collect(old('branch_ids', $user->accessibleBranches->pluck('id')->all()))->map(fn ($id) => (int) $id);
    $selectedRoles = collect(old('role_ids', $user->roles->pluck('id')->all()))->map(fn ($id) => (int) $id);
@endphp
@section('title', $editing ? 'تعديل المستخدم' : 'إضافة مستخدم')
@section('page-title', $editing ? 'تعديل المستخدم' : 'إضافة مستخدم')
@section('breadcrumb', 'الإعدادات / المستخدمون')
@section('content')
<x-card>
    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="sw-form">
        @csrf @if($editing) @method('PUT') @endif
        <div class="sw-form-grid">
            <x-form.input name="name" label="الاسم" :value="old('name', $user->name)" required />
            <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="old('email', $user->email)" required />
            <x-form.input name="phone" label="الهاتف" :value="old('phone', $user->phone)" />
            <x-form.select name="status" label="الحالة" required><option value="active" @selected(old('status', $user->status ?: 'active') === 'active')>نشط</option><option value="inactive" @selected(old('status', $user->status) === 'inactive')>غير نشط</option><option value="suspended" @selected(old('status', $user->status) === 'suspended')>موقوف</option></x-form.select>
            <x-form.input name="password" type="password" label="كلمة المرور" :required="!$editing" />
            <x-form.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" :required="!$editing" />
            <x-form.select name="branch_id" label="الفرع الافتراضي" id="default-branch">
                <option value="">بدون فرع افتراضي</option>
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $user->branch_id) === $branch->id)>{{ $branch->name }}</option>@endforeach
            </x-form.select>
        </div>
        <fieldset class="sw-option-group" aria-describedby="branches-help">
            <legend>الفروع المتاحة</legend>
            <div class="sw-option-grid">
            @foreach($branches as $branch)
                <label class="sw-check" for="branch-{{ $branch->id }}"><input id="branch-{{ $branch->id }}" type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked($selectedBranches->contains($branch->id))><span class="sw-check__box" aria-hidden="true"></span><span>{{ $branch->name }}</span></label>
            @endforeach
            </div>
            <p id="branches-help" class="sw-field__help">يجب اختيار فرع واحد على الأقل، ويجب أن يكون الفرع الافتراضي من الفروع المحددة.</p>
            @error('branch_ids')<p class="sw-field__error">{{ $message }}</p>@enderror @error('branch_id')<p class="sw-field__error">{{ $message }}</p>@enderror
        </fieldset>
        <fieldset class="sw-option-group" aria-describedby="roles-help">
            <legend>الأدوار</legend>
            <div class="sw-option-grid">
            @foreach($roles as $role)
                <label class="sw-check" for="role-{{ $role->id }}"><input id="role-{{ $role->id }}" type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($selectedRoles->contains($role->id))><span class="sw-check__box" aria-hidden="true"></span><span><strong>{{ $role->display_name }}</strong> <small>{{ $role->name }} — {{ $role->company_id ? 'Company' : 'System' }}</small></span></label>
            @endforeach
            </div>
            <p id="roles-help" class="sw-field__help">يجب اختيار دور واحد على الأقل.</p>
            @error('role_ids')<p class="sw-field__error">{{ $message }}</p>@enderror
        </fieldset>
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('users.index') }}">إلغاء</a></div>
    </form>
</x-card>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultBranch = document.getElementById('default-branch');
    if (!defaultBranch) return;
    defaultBranch.addEventListener('change', function () {
        const checkbox = document.getElementById('branch-' + this.value);
        if (checkbox && this.value) checkbox.checked = true;
    });
});
</script>
@endsection
