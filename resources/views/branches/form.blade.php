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
@if($editing)
<x-card title="مسؤول تشغيل الفرع">
    @if($branch->responsibleUser)
        <dl class="sw-details-grid">
            <div><dt>الاسم</dt><dd>{{ $branch->responsibleUser->name }}</dd></div>
            <div><dt>البريد الإلكتروني</dt><dd>{{ $branch->responsibleUser->email }}</dd></div>
            <div><dt>الحالة</dt><dd>{{ $branch->responsibleUser->status === 'active' ? 'نشط' : 'غير نشط' }}</dd></div>
            <div><dt>الدور</dt><dd>{{ $branch->responsibleUser->roles->firstWhere('name', 'branch_manager')?->display_name ?? 'مسؤول الفرع' }}</dd></div>
            <div><dt>تاريخ التعيين</dt><dd>{{ $branch->responsible_assigned_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div><dt>آخر تسجيل دخول</dt><dd>{{ $branch->responsibleUser->last_login_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
        </dl>
    @else
        <p class="sw-help"><strong>المسؤول الحالي:</strong> غير معين</p>
    @endif

    @if($canManageResponsible)
        <form method="POST" action="{{ route('branches.responsible-user.update', $branch) }}" class="sw-form">
            @csrf @method('PUT')
            <x-form.select name="responsible_user_id" label="اختيار مسؤول تشغيل الفرع" required>
                <option value="">اختر مستخدمًا مؤهلًا</option>
                @foreach($responsibleCandidates as $candidate)
                    <option value="{{ $candidate->id }}" @selected((int) old('responsible_user_id', $branch->responsible_user_id) === $candidate->id)>
                        {{ $candidate->name }} — {{ $candidate->email }}
                    </option>
                @endforeach
            </x-form.select>
            <p class="sw-help">تظهر فقط الحسابات النشطة التي تملك دور مسؤول الفرع ووصولًا لهذا الفرع وليست مسؤولة عن فرع آخر.</p>
            <div class="sw-form-actions">
                <x-button type="submit">{{ $branch->responsibleUser ? 'تغيير المسؤول' : 'تعيين كمسؤول الفرع' }}</x-button>
                @if(auth()->user()->hasPermission('users.create') || auth()->user()->hasRole('system_admin'))
                    <a class="sw-button sw-button--outline" href="{{ route('users.create', [
                        'branch_id' => $branch->id,
                        'role' => 'branch_manager',
                        'return_url' => route('branches.edit', $branch),
                        'assign_as_responsible' => 1,
                    ]) }}">إنشاء حساب مسؤول جديد</a>
                @endif
            </div>
        </form>

        @if($branch->responsibleUser)
            <form method="POST" action="{{ route('branches.responsible-user.destroy', $branch) }}" class="sw-form" onsubmit="return confirm('هل تريد إلغاء تعيين مسؤول تشغيل الفرع؟')">
                @csrf @method('DELETE')
                <x-form.textarea name="reason" label="سبب إلغاء التعيين" required>{{ old('reason') }}</x-form.textarea>
                <div class="sw-form-actions">
                    <button class="sw-button sw-button--outline" type="submit">إلغاء تعيين المسؤول</button>
                </div>
            </form>
        @endif
    @endif
</x-card>
@endif
@endsection
