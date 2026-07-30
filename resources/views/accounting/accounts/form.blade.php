@extends('layouts.app')

@section('title', $account->exists ? 'تعديل حساب' : 'إنشاء حساب')
@section('page-title', $account->exists ? 'تعديل حساب' : 'إنشاء حساب')

@section('content')
<form class="accounting-account-form" method="POST" action="{{ $account->exists ? route('accounting.accounts.update', $account) : route('accounting.accounts.store') }}">
    @csrf
    @if($account->exists)
        @method('PUT')
    @endif

    <section class="sw-card accounting-form-card">
        <div class="sw-card__header">
            <div>
                <h2>بيانات الحساب الأساسية</h2>
                <p>أدخل كود الحساب وأسماءه وحدد موقعه داخل دليل الحسابات.</p>
            </div>
        </div>
        <div class="sw-card__body">
            <div class="sw-form-grid accounting-account-main-grid">
                <label class="sw-field">
                    <span class="sw-field__label">الكود</span>
                    <input class="sw-input" name="account_code" required value="{{ old('account_code', $account->account_code) }}">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">الاسم العربي</span>
                    <input class="sw-input" name="name_ar" required value="{{ old('name_ar', $account->name_ar) }}">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">الاسم الإنجليزي</span>
                    <input class="sw-input" name="name_en" value="{{ old('name_en', $account->name_en) }}">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">النوع</span>
                    <select class="sw-input" name="account_type_id">
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" @selected(old('account_type_id', $account->account_type_id) == $type->id)>{{ $type->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">المجموعة</span>
                    <select class="sw-input" name="account_group_id">
                        <option value="">بدون مجموعة</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" @selected(old('account_group_id', $account->account_group_id) == $group->id)>{{ $group->code }} — {{ $group->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">الحساب الأب</span>
                    <select class="sw-input" name="parent_account_id">
                        <option value="">حساب جذري</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_account_id', $account->parent_account_id) == $parent->id)>{{ $parent->account_code }} — {{ $parent->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </section>

    <section class="sw-card accounting-form-card">
        <div class="sw-card__header">
            <div>
                <h2>التصنيف والمعالجة</h2>
                <p>حدد شكل الحساب وعملته ونوع التحكم المحاسبي.</p>
            </div>
        </div>
        <div class="sw-card__body">
            <div class="sw-form-grid">
                <label class="sw-field">
                    <span class="sw-field__label">شكل الحساب</span>
                    <select class="sw-input" name="is_header" onchange="this.form.is_posting.value = this.value === '1' ? '0' : '1'">
                        <option value="1" @selected(old('is_header', $account->exists ? $account->is_header : true))>حساب رئيسي</option>
                        <option value="0" @selected(!old('is_header', $account->exists ? $account->is_header : true))>حساب حركة</option>
                    </select>
                    <input type="hidden" name="is_posting" value="{{ old('is_posting', $account->exists ? (int) $account->is_posting : 0) }}">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">العملة</span>
                    <select class="sw-input" name="currency_id">
                        <option value="">عملة الشركة</option>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}" @selected(old('currency_id', $account->currency_id) == $currency->id)>{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field accounting-account-control-type">
                    <span class="sw-field__label">نوع الحساب الرقابي</span>
                    <select class="sw-input" name="control_type">
                        <option value="">بدون</option>
                        @foreach(['accounts_receivable', 'accounts_payable', 'inventory', 'vat_input', 'vat_output', 'customer_advances', 'supplier_advances', 'employee_advances', 'fixed_assets', 'accumulated_depreciation'] as $type)
                            <option value="{{ $type }}" @selected(old('control_type', $account->control_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </section>

    <section class="sw-card accounting-form-card">
        <div class="sw-card__header">
            <div>
                <h2>قواعد الاستخدام</h2>
                <p>حدد الأبعاد المطلوبة وطريقة استخدام الحساب في العمليات.</p>
            </div>
        </div>
        <div class="sw-card__body">
            <div class="accounting-account-options">
                @foreach([
                    'allows_multi_currency' => 'متعدد العملات',
                    'requires_cost_center' => 'يتطلب مركز تكلفة',
                    'requires_branch' => 'يتطلب فرع',
                    'requires_customer' => 'يتطلب عميل',
                    'requires_supplier' => 'يتطلب مورد',
                    'requires_employee' => 'يتطلب موظف',
                    'requires_vehicle' => 'يتطلب سيارة',
                    'is_control_account' => 'حساب رقابي',
                    'is_bank_account' => 'حساب بنكي',
                    'is_cash_account' => 'حساب نقدي',
                    'is_inventory_account' => 'حساب مخزون',
                    'is_tax_account' => 'حساب ضريبة',
                    'allow_manual_entry' => 'يسمح بإدخال يدوي',
                ] as $field => $label)
                    <label class="sw-check accounting-account-option">
                        <input type="hidden" name="{{ $field }}" value="0">
                        <input class="sw-check__box" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $account->$field))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    <section class="sw-card accounting-form-card">
        <div class="sw-card__header">
            <div>
                <h2>ملاحظات الحساب</h2>
                <p>أضف وصفًا اختياريًا يوضح الغرض من الحساب.</p>
            </div>
        </div>
        <div class="sw-card__body">
            <label class="sw-field">
                <span class="sw-field__label">الوصف</span>
                <textarea class="sw-input accounting-account-description" name="description" rows="4">{{ old('description', $account->description) }}</textarea>
            </label>
        </div>
    </section>

    <div class="accounting-account-actions">
        <a class="sw-button sw-button--secondary" href="{{ route('accounting.accounts.index') }}">إلغاء</a>
        <button class="sw-button sw-button--primary" type="submit">{{ $account->exists ? 'حفظ التعديلات' : 'إنشاء الحساب' }}</button>
    </div>
</form>
@endsection
