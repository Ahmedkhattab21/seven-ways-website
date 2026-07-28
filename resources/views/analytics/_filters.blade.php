<form method="GET" class="sw-report-filters" aria-label="فلاتر التقرير">
    <label>
        <span>من تاريخ</span>
        <input type="date" name="date_from" value="{{ request('date_from', $filters->dateFrom ?? now()->startOfMonth()->toDateString()) }}">
    </label>
    <label>
        <span>إلى تاريخ</span>
        <input type="date" name="date_to" value="{{ request('date_to', $filters->dateTo ?? now()->toDateString()) }}">
    </label>
    <label>
        <span>الفرع</span>
        <select name="branch_id">
            @if(auth()->user()->hasPermission('reports.view_all_branches'))
                <option value="">كل الفروع المسموحة</option>
            @endif
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" @selected((int) request('branch_id') === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </label>
    @isset($currencies)
        <label>
            <span>العملة</span>
            <select name="currency_id">
                @foreach($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((int) request('currency_id', auth()->user()->company->currency_id) === $currency->id)>
                        {{ $currency->code }} — {{ $currency->name_ar }}
                    </option>
                @endforeach
            </select>
        </label>
    @endisset
    <button class="sw-button sw-button--primary" type="submit">تطبيق</button>
</form>

