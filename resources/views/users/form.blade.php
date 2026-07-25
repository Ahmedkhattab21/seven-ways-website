@extends('layouts.app')
@php($editing = $user->exists)
@section('title', $editing ? 'تعديل المستخدم' : 'إضافة مستخدم')
@section('page-title', $editing ? 'تعديل المستخدم' : 'إضافة مستخدم')
@section('breadcrumb', 'الإعدادات / المستخدمون')
@section('content')
<x-card>
    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="sw-form">
        @csrf @if($editing) @method('PUT') @endif
        <div class="sw-form-grid">
            <x-form.input name="name" label="الاسم" :value="$user->name" required />
            <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="$user->email" required />
            <x-form.input name="phone" label="الهاتف" :value="$user->phone" />
            <x-form.select name="status" label="الحالة" required><option value="active" @selected(old('status', $user->status ?: 'active') === 'active')>نشط</option><option value="inactive" @selected(old('status', $user->status) === 'inactive')>غير نشط</option><option value="suspended" @selected(old('status', $user->status) === 'suspended')>موقوف</option></x-form.select>
            <x-form.input name="password" type="password" label="كلمة المرور" :required="!$editing" />
            <x-form.input name="password_confirmation" type="password" label="تأكيد كلمة المرور" :required="!$editing" />
            <x-form.select name="branch_id" label="الفرع الافتراضي">
                <option value="">بدون فرع افتراضي</option>
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('branch_id', $user->branch_id) === $branch->id)>{{ $branch->name }}</option>@endforeach
            </x-form.select>
        </div>
        <fieldset class="sw-option-group"><legend>الفروع المتاحة</legend>
            @foreach($branches as $branch)<label class="sw-check"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, old('branch_ids', $user->accessibleBranches->pluck('id')->all())))> {{ $branch->name }}</label>@endforeach
        </fieldset>
        <fieldset class="sw-option-group"><legend>الأدوار</legend>
            @foreach($roles as $role)<label class="sw-check"><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, old('role_ids', $user->roles->pluck('id')->all())))> {{ $role->display_name }}</label>@endforeach
        </fieldset>
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('users.index') }}">إلغاء</a></div>
    </form>
</x-card>
@endsection
