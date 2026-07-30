@extends('layouts.app')

@section('title', 'أنواع الحسابات')
@section('page-title', 'أنواع الحسابات')

@section('content')
<div class="accounting-page">
    <div class="sw-alert">
        الأنواع النظامية مشتركة منطقيًا ومحمية من التعديل.
    </div>

    @if(auth()->user()->hasPermission('accounting.account_types.manage'))
        <form class="sw-card sw-form accounting-form-card" method="POST" action="{{ route('accounting.account-types.store') }}">
            @csrf
            <div class="sw-card__header">
                <div>
                    <h2>إنشاء نوع حساب</h2>
                    <p>أدخل بيانات النوع وحدد طبيعته والقائمة المالية وتصنيف التدفق النقدي.</p>
                </div>
            </div>

            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">الكود</span>
                        <input class="sw-input" name="code" required value="{{ old('code') }}" placeholder="مثال: ASSET">
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الاسم العربي</span>
                        <input class="sw-input" name="name_ar" required value="{{ old('name_ar') }}" placeholder="اسم نوع الحساب بالعربية">
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الاسم الإنجليزي</span>
                        <input class="sw-input" name="name_en" value="{{ old('name_en') }}" placeholder="Account type name">
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">التصنيف</span>
                        <select class="sw-input" name="classification">
                            @foreach(['asset' => 'أصول', 'liability' => 'التزامات', 'equity' => 'حقوق ملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('classification') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الطبيعة</span>
                        <select class="sw-input" name="normal_balance">
                            <option value="debit" @selected(old('normal_balance') === 'debit')>مدين</option>
                            <option value="credit" @selected(old('normal_balance') === 'credit')>دائن</option>
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">القائمة المالية</span>
                        <select class="sw-input" name="statement_type">
                            <option value="balance_sheet" @selected(old('statement_type') === 'balance_sheet')>الميزانية العمومية</option>
                            <option value="income_statement" @selected(old('statement_type') === 'income_statement')>قائمة الدخل</option>
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">التدفق النقدي</span>
                        <select class="sw-input" name="cash_flow_category">
                            @foreach(['none' => 'بدون', 'operating' => 'تشغيلي', 'investing' => 'استثماري', 'financing' => 'تمويلي'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('cash_flow_category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <input type="hidden" name="is_active" value="1">

                <div class="sw-form-actions accounting-form-actions">
                    <button class="sw-button sw-button--primary" type="submit">إنشاء نوع الحساب</button>
                </div>
            </div>
        </form>
    @endif

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>أنواع الحسابات المسجلة</h2>
                <p>الأنواع المتاحة وتصنيفها وطبيعتها والقائمة المالية المرتبطة بها.</p>
            </div>
        </div>

        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>التصنيف</th>
                        <th>الطبيعة</th>
                        <th>القائمة</th>
                        <th>النطاق</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $type)
                        <tr>
                            <td>{{ $type->code }}</td>
                            <td>{{ $type->name_ar }}</td>
                            <td>{{ $type->classification }}</td>
                            <td>{{ $type->normal_balance }}</td>
                            <td>{{ $type->statement_type }}</td>
                            <td>{{ $type->company_id ? 'الشركة' : 'النظام' }}</td>
                            <td>{{ $type->is_active ? 'فعال' : 'معطل' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="accounting-empty-row" colspan="7">لا توجد أنواع حسابات مسجلة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
