@extends('layouts.app')

@section('title', 'البنوك')
@section('page-title', 'دليل البنوك')

@section('content')
<div class="treasury-maintenance-page">
    @if(auth()->user()->hasPermission('treasury.banks.manage'))
        <form class="sw-card sw-form treasury-form-card" method="POST" action="{{ route('treasury.banks.store') }}">
            @csrf
            <header class="sw-card__header">
                <div>
                    <h3 class="sw-card__title">إضافة بنك خاص بالشركة</h3>
                    <p class="sw-card__subtitle">سجل البيانات التعريفية للبنك قبل إنشاء الحسابات البنكية.</p>
                </div>
            </header>

            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">الكود</span>
                        <input class="sw-input" name="code" required>
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">الاسم العربي</span>
                        <input class="sw-input" name="name_ar" required>
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">الاسم الإنجليزي</span>
                        <input class="sw-input" name="name_en">
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">SWIFT</span>
                        <input class="sw-input" name="swift_code" dir="ltr">
                    </label>
                    <label class="sw-field treasury-field-wide">
                        <span class="sw-field__label">الموقع الإلكتروني</span>
                        <input class="sw-input" name="website" type="url" dir="ltr">
                    </label>
                </div>

                <div class="sw-form-actions treasury-form-actions">
                    <button class="sw-button sw-button--primary" type="submit">حفظ البنك</button>
                </div>
            </div>
        </form>
    @endif

    <div class="sw-card sw-table-wrap">
        <table class="sw-table">
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>البنك</th>
                    <th>النطاق</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($banks as $bank)
                    <tr>
                        <td>{{ $bank->code }}</td>
                        <td>{{ $bank->name_ar }}</td>
                        <td>{{ $bank->is_system ? 'نظامي' : 'خاص بالشركة' }}</td>
                        <td><span class="sw-badge {{ $bank->is_active ? 'sw-badge--active' : 'sw-badge--inactive' }}">{{ $bank->is_active ? 'نشط' : 'معطل' }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="treasury-empty-row">لا توجد بنوك مسجلة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
