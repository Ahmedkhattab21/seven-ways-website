@extends('layouts.app')
@section('title', $config['title'])
@section('page-title', $config['title'])
@section('breadcrumb', 'الإعدادات / '.$config['title'])
@section('page-actions')
@php
    $systemOnly = in_array($section, ['currencies', 'vehicle-brands', 'vehicle-models'], true);
    $canManage = auth()->user()->hasRole('system_admin') || (!$systemOnly && auth()->user()->hasPermission($config['manage_permission']));
@endphp
@if($canManage)<a class="sw-button sw-button--primary" href="{{ route('reference.create', $section) }}">إضافة سجل</a>@endif
@endsection
@section('content')
<x-table-shell>
    <x-slot:tools>
        <form method="GET" class="sw-filter-bar">
            <input class="sw-input" type="search" name="search" value="{{ request('search') }}" placeholder="بحث">
            <select class="sw-input" name="status">
                <option value="">كل الحالات</option>
                <option value="active" @selected(request('status') === 'active')>نشط</option>
                <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
            </select>
            <x-button type="submit" variant="outline">تصفية</x-button>
        </form>
    </x-slot:tools>
    <thead><tr><th>الكود / الاسم</th><th>التفاصيل</th><th>النطاق</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
    <tbody>
    @forelse($items as $item)
        @php
            $documentTypeLabel = config("document_sequences.types.{$item->document_type}.label");
            $primary = $item->code ?? $item->name ?? $item->name_ar
                ?? ($documentTypeLabel ? $item->document_type.' — '.$documentTypeLabel : $item->document_type);
            $details = match($section) {
                'currencies' => $item->name_ar.' — '.$item->symbol,
                'taxes' => $item->name.' — '.rtrim(rtrim($item->rate, '0'), '.').'%',
                'units' => $item->name.' — '.$item->symbol,
                'payment-methods' => $item->name.' — '.$item->type,
                'vehicle-brands' => $item->name_en ?: '—',
                'vehicle-models' => ($item->brand?->name_ar ?? '—').' — '.($item->start_year ?: '—').' / '.($item->end_year ?: '—'),
                'vehicle-sizes', 'vehicle-types' => $item->name,
                'fiscal-years' => $item->start_date->format('Y-m-d').' — '.$item->end_date->format('Y-m-d'),
                'document-sequences' => $item->prefix.' / '.$item->current_number,
                default => '—',
            };
            $active = $section === 'fiscal-years' ? $item->status !== 'closed' : $item->is_active;
            $editable = $canManage && (!($item->is_system ?? false)) && ($systemOnly || (int) ($item->company_id ?? 0) === (int) auth()->user()->company_id);
        @endphp
        <tr>
            <td>{{ $primary }}</td>
            <td>{{ $details }}</td>
            <td>{{ ($item->is_system ?? false) || $systemOnly ? 'نظامي' : ($item->branch?->name ?? 'الشركة') }}</td>
            <td><x-status-badge :status="$active ? 'active' : 'inactive'" /></td>
            <td>@if($editable)<a href="{{ route('reference.edit', [$section, $item->id]) }}">تعديل</a>@else — @endif</td>
        </tr>
    @empty
        <tr><td colspan="5">لا توجد بيانات.</td></tr>
    @endforelse
    </tbody>
    <x-slot:footer>{{ $items->links() }}</x-slot:footer>
</x-table-shell>
@endsection
