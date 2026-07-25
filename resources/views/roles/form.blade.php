@extends('layouts.app')
@php($editing = $role->exists)
@section('title', $editing ? 'صلاحيات الدور' : 'إضافة دور')
@section('page-title', $editing ? 'صلاحيات الدور' : 'إضافة دور')
@section('breadcrumb', 'الإعدادات / الأدوار')
@section('content')
<x-card>
    <form method="POST" action="{{ $editing ? route('roles.update', $role) : route('roles.store') }}" class="sw-form">
        @csrf @if($editing) @method('PUT') @endif
        @if(!$editing)
            <div class="sw-form-grid"><x-form.input name="name" label="الاسم البرمجي" required /><x-form.input name="display_name" label="اسم العرض" required /></div>
        @else
            <h2>{{ $role->display_name }}</h2>
        @endif
        <fieldset class="sw-option-group"><legend>الصلاحيات</legend>
            @foreach($permissions as $permission)<label class="sw-check"><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids', $role->permissions->pluck('id')->all())))> {{ $permission->display_name }}</label>@endforeach
        </fieldset>
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('roles.index') }}">إلغاء</a></div>
    </form>
</x-card>
@endsection
