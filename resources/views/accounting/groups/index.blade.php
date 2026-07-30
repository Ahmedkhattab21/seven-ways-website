@extends('layouts.app')

@section('title', 'مجموعات الحسابات')
@section('page-title', 'مجموعات الحسابات')

@section('content')
<div class="accounting-page">
    <div class="accounting-page-actions">
        <a class="sw-button sw-button--secondary" href="{{ route('accounting.account-types.index') }}">أنواع الحسابات</a>
        <a class="sw-button sw-button--outline" href="{{ route('accounting.accounts.index') }}">دليل الحسابات</a>
    </div>

    @if(auth()->user()->hasPermission('accounting.account_groups.create'))
        <form class="sw-card sw-form accounting-form-card" method="POST" action="{{ route('accounting.groups.store') }}">
            @csrf
            <div class="sw-card__header">
                <div>
                    <h2>إنشاء مجموعة حسابات</h2>
                    <p>أضف مجموعة جديدة وحدد نوعها وموقعها داخل الهيكل المحاسبي.</p>
                </div>
            </div>
            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">الكود</span>
                        <input class="sw-input" name="code" required value="{{ old('code') }}" placeholder="مثال: 111">
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">الاسم</span>
                        <input class="sw-input" name="name_ar" required value="{{ old('name_ar') }}" placeholder="اسم المجموعة بالعربية">
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">نوع الحساب</span>
                        <select class="sw-input" name="account_type_id">
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" @selected(old('account_type_id') == $type->id)>{{ $type->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">المجموعة الأب</span>
                        <select class="sw-input" name="parent_group_id">
                            <option value="">مجموعة جذرية</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected(old('parent_group_id') == $group->id)>{{ $group->code }} — {{ $group->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="sw-form-actions accounting-form-actions">
                    <button class="sw-button sw-button--primary" type="submit">إنشاء المجموعة</button>
                </div>
            </div>
        </form>
    @endif

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>المجموعات المسجلة</h2>
                <p>هيكل مجموعات الحسابات ومستوياتها وحالتها الحالية.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>النوع</th>
                        <th>المجموعة الأب</th>
                        <th>المستوى</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td>{{ $group->code }}</td>
                            <td>{{ $group->name_ar }}</td>
                            <td>{{ $group->type->name_ar }}</td>
                            <td>{{ $group->parent?->name_ar ?? '—' }}</td>
                            <td>{{ $group->level }}</td>
                            <td>{{ $group->is_active ? 'فعال' : 'معطل' }}</td>
                        </tr>
                    @empty
                        <tr><td class="accounting-empty-row" colspan="6">لا توجد مجموعات حسابات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
