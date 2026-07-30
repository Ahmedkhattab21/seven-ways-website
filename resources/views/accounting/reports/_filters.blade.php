<form class="sw-card sw-form accounting-report-filters" method="GET">
    <div class="sw-card__header">
        <div>
            <h2>فلاتر التقرير</h2>
            <p>حدد الفترة والنطاق المطلوبين ثم اعرض النتائج.</p>
        </div>
    </div>
    <div class="sw-card__body">
        <div class="sw-form-grid">
            <label class="sw-field">
                <span class="sw-field__label">من</span>
                <input class="sw-input" type="date" name="date_from" value="{{ request('date_from') }}">
            </label>
            <label class="sw-field">
                <span class="sw-field__label">إلى</span>
                <input class="sw-input" type="date" name="date_to" value="{{ request('date_to') }}">
            </label>

            @isset($accounts)
                <label class="sw-field">
                    <span class="sw-field__label">الحساب</span>
                    <select class="sw-input" name="account_id">
                        <option value="">الكل</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ $account->account_code }} — {{ $account->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @isset($branches)
                <label class="sw-field">
                    <span class="sw-field__label">الفرع</span>
                    <select class="sw-input" name="branch_id">
                        <option value="">كل الفروع المتاحة</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @isset($costCenters)
                <label class="sw-field">
                    <span class="sw-field__label">مركز التكلفة</span>
                    <select class="sw-input" name="cost_center_id">
                        <option value="">الكل</option>
                        @foreach($costCenters as $center)
                            <option value="{{ $center->id }}" @selected(request('cost_center_id') == $center->id)>{{ $center->code }} — {{ $center->name_ar }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @if(!empty($allowTrialOptions))
                <label class="sw-field">
                    <span class="sw-field__label">طريقة العرض</span>
                    <select class="sw-input" name="summary_by">
                        <option value="account">الحسابات</option>
                        <option value="group" @selected(request('summary_by') === 'group')>المجموعات</option>
                        <option value="type" @selected(request('summary_by') === 'type')>الأنواع</option>
                    </select>
                </label>
            @endif

            @if(!empty($allowComparative))
                <label class="sw-field">
                    <span class="sw-field__label">المقارنة</span>
                    <select class="sw-input" name="comparison">
                        <option value="">بدون مقارنة</option>
                        <option value="previous_period" @selected(request('comparison') === 'previous_period')>الفترة السابقة</option>
                        <option value="previous_year" @selected(request('comparison') === 'previous_year')>العام السابق</option>
                    </select>
                </label>
            @endif
        </div>

        @if(!empty($allowTrialOptions))
            <div class="accounting-report-options">
                <label class="sw-check">
                    <input class="sw-check__box" type="checkbox" name="include_header" value="1" @checked(request()->boolean('include_header'))>
                    <span>إظهار الحسابات الرئيسية</span>
                </label>
                <label class="sw-check">
                    <input class="sw-check__box" type="checkbox" name="include_zero" value="1" @checked(request()->boolean('include_zero'))>
                    <span>إظهار الأرصدة الصفرية</span>
                </label>
            </div>
        @endif

        <div class="sw-form-actions accounting-form-actions">
            <button class="sw-button sw-button--primary" type="submit">عرض التقرير</button>
            @if(isset($allowExport) && $allowExport)
                <button class="sw-button sw-button--secondary" type="submit" name="export" value="csv">تصدير CSV</button>
            @endif
        </div>
    </div>
</form>
