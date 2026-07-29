@extends('layouts.app')
@php($editing = $branch->exists)
@section('title', $editing ? 'تعديل الفرع' : 'إضافة فرع')
@section('page-title', $editing ? 'تعديل الفرع' : 'إضافة فرع')
@section('breadcrumb', 'الإعدادات / الفروع')
@section('content')
<x-card>
    <form method="POST" action="{{ $editing ? route('branches.update', $branch) : route('branches.store') }}" class="sw-form">
        @csrf @if($editing) @method('PUT') @endif
        <div class="sw-form-grid">
            <x-form.input name="code" label="كود الفرع" :value="$branch->code" required />
            <x-form.input name="name" label="اسم الفرع" :value="$branch->name" required />
            <x-form.input name="commercial_name" label="الاسم التجاري" :value="$branch->commercial_name" />
            <x-form.input name="email" type="email" label="البريد الإلكتروني" :value="$branch->email" />
            <x-form.input name="phone" label="الهاتف" :value="$branch->phone" />
            <x-form.input name="tax_number" label="الرقم الضريبي" :value="$branch->tax_number" />
            <x-form.input name="latitude" type="number" step="0.0000001" label="خط العرض" :value="$branch->latitude" />
            <x-form.input name="longitude" type="number" step="0.0000001" label="خط الطول" :value="$branch->longitude" />
            <x-form.textarea name="address" label="العنوان">{{ $branch->address }}</x-form.textarea>
        </div>
        <label class="sw-check"><input type="checkbox" name="is_main" value="1" @checked(old('is_main', $branch->is_main))> فرع رئيسي</label>
        <label class="sw-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $branch->is_active : true))> نشط</label>
        @unless($editing)
            <div class="sw-form-section">
                <h3>إنشاء حساب مسؤول الفرع (اختياري)</h3>
                <p class="sw-help">يُنشأ الحساب ويُربط بهذا الفرع داخل نفس المعاملة.</p>
                <div class="sw-form-grid">
                    <x-form.input name="responsible_name" label="اسم المسؤول" />
                    <x-form.input name="responsible_email" type="email" label="البريد الوظيفي" />
                    <x-form.input name="responsible_password" type="password" label="كلمة المرور" />
                    <x-form.input name="responsible_password_confirmation" type="password" label="تأكيد كلمة المرور" />
                    <x-form.select name="responsible_status" label="حالة الحساب">
                        <option value="active" @selected(old('responsible_status', 'active') === 'active')>نشط</option>
                        <option value="inactive" @selected(old('responsible_status') === 'inactive')>غير نشط</option>
                    </x-form.select>
                </div>
            </div>
        @endunless
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('branches.index') }}">إلغاء</a></div>
    </form>
</x-card>
@if($editing && auth()->user()->hasPermission('branches.assign_responsible'))
<x-card title="مسؤول تشغيل الفرع">
    @if($branch->responsibleUser)
        <dl class="sw-details-grid">
            <div><dt>الاسم</dt><dd>{{ $branch->responsibleUser->name }}</dd></div>
            <div><dt>البريد</dt><dd>{{ $branch->responsibleUser->email }}</dd></div>
            <div><dt>الحالة</dt><dd>{{ $branch->responsibleUser->status === 'active' ? 'نشط' : 'غير نشط' }}</dd></div>
            <div><dt>تاريخ التعيين</dt><dd>{{ $branch->responsible_assigned_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt>آخر تسجيل دخول</dt><dd>{{ $branch->responsibleUser->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
        <div class="sw-form-actions">
            @if(auth()->user()->hasPermission('users.update'))
                <a class="sw-button sw-button--outline" href="{{ route('users.edit', $branch->responsibleUser) }}">إعادة تعيين كلمة المرور</a>
            @endif
            @if($branch->responsibleUser->status === 'active' && auth()->user()->hasPermission('users.disable'))
                <form method="POST" action="{{ route('users.disable', $branch->responsibleUser) }}" onsubmit="return confirm('تأكيد تعطيل حساب مسؤول الفرع؟')">
                    @csrf
                    @method('PATCH')
                    <button class="sw-button sw-button--outline" type="submit">تعطيل الحساب</button>
                </form>
            @endif
        </div>
    @endif
    <form method="POST" action="{{ route('branches.responsible-user.update', $branch) }}" class="sw-form">
        @csrf @method('PUT')
        <x-form.select name="responsible_user_id" label="{{ $branch->responsibleUser ? 'تغيير المسؤول' : 'تعيين مسؤول' }}" required>
            <option value="">اختر حساب مسؤول الفرع</option>
            @foreach($responsibleCandidates as $candidate)
                <option value="{{ $candidate->id }}" @selected((int) old('responsible_user_id', $branch->responsible_user_id) === $candidate->id)>
                    {{ $candidate->name }} — {{ $candidate->email }}
                </option>
            @endforeach
        </x-form.select>
        <p class="sw-help">يجب أن يكون الحساب نشطًا، بدور مسؤول الفرع، وغير مسؤول عن فرع آخر.</p>
        <div class="sw-form-actions"><x-button type="submit">حفظ مسؤول الفرع</x-button></div>
    </form>
</x-card>
@endif
@endsection
