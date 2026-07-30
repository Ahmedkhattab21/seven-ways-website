@extends('layouts.app')

@section('title', 'دليل الحسابات')
@section('page-title', 'دليل الحسابات')

@section('content')
<div class="accounting-page">
    <div class="accounting-page-actions">
        @can('create', App\Models\Account::class)
            <a class="sw-button sw-button--primary" href="{{ route('accounting.accounts.create') }}">إنشاء حساب</a>
        @endcan
        @if(auth()->user()->hasPermission('accounting.account_groups.view'))
            <a class="sw-button sw-button--secondary" href="{{ route('accounting.groups.index') }}">مجموعات الحسابات</a>
        @endif
    </div>

    <form class="sw-card sw-form accounting-filter-card" method="GET">
        <div class="sw-card__header">
            <div>
                <h2>البحث والتصفية</h2>
                <p>ابحث بالكود أو الاسم، وحدد نوع الحساب ومجموعته وحالته.</p>
            </div>
        </div>
        <div class="sw-card__body">
            <div class="sw-form-grid">
                <label class="sw-field">
                    <span class="sw-field__label">بحث</span>
                    <input class="sw-input" name="search" value="{{ request('search') }}" placeholder="الكود أو اسم الحساب">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">النوع</span>
                    <select class="sw-input" name="account_type_id">
                        <option value="">الكل</option>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" @selected(request('account_type_id') == $type->id)>{{ $type->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">المجموعة</span>
                    <select class="sw-input" name="account_group_id">
                        <option value="">الكل</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" @selected(request('account_group_id') == $group->id)>{{ $group->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">الحالة</span>
                    <select class="sw-input" name="is_active">
                        <option value="">الكل</option>
                        <option value="1" @selected(request('is_active') === '1')>فعال</option>
                        <option value="0" @selected(request('is_active') === '0')>معطل</option>
                    </select>
                </label>
            </div>
            <div class="sw-form-actions accounting-form-actions">
                <button class="sw-button sw-button--primary" type="submit">تطبيق التصفية</button>
            </div>
        </div>
    </form>

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>قائمة الحسابات</h2>
                <p>عرض تفصيلي للحسابات ومستوياتها وطبيعتها المحاسبية.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>الاسم</th>
                        <th>النوع</th>
                        <th>المجموعة</th>
                        <th>المستوى</th>
                        <th>الطبيعة</th>
                        <th>التصنيف</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td><a href="{{ route('accounting.accounts.edit', $account) }}">{{ $account->account_code }}</a></td>
                            <td>{{ $account->name_ar }}</td>
                            <td>{{ $account->type->name_ar }}</td>
                            <td>{{ $account->group?->name_ar }}</td>
                            <td>{{ $account->account_level }}</td>
                            <td>{{ $account->normal_balance }}</td>
                            <td>{{ $account->is_header ? 'رئيسي' : 'حركة' }}</td>
                            <td>{{ $account->is_active ? 'فعال' : 'معطل' }}</td>
                        </tr>
                    @empty
                        <tr><td class="accounting-empty-row" colspan="8">لا توجد حسابات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sw-card__body">{{ $accounts->links() }}</div>
    </section>

    <section class="sw-card">
        <div class="sw-card__header">
            <div>
                <h2>شجرة الحسابات</h2>
                <p>عرض هرمي يوضح الحسابات الرئيسية والحسابات التابعة.</p>
            </div>
        </div>
        <div class="sw-card__body accounting-tree">
            @forelse($tree as $root)
                <div class="accounting-tree__item" style="margin-inline-start: {{ $root->account_level * 1.25 }}rem">
                    <strong>{{ $root->account_code }} — {{ $root->name_ar }}</strong>
                    @foreach($root->children as $child)
                        <div class="accounting-tree__branch">
                            {{ $child->account_code }} — {{ $child->name_ar }}
                            @foreach($child->children as $leaf)
                                <div class="accounting-tree__branch">{{ $leaf->account_code }} — {{ $leaf->name_ar }}</div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="accounting-empty-row">لا توجد شجرة حسابات.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
