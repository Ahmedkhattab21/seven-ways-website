<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $definition['name'] }}</title>
    <style>
        @font-face{font-family:Cairo;src:url('/assets/website/fonts/cairo-arabic.woff2') format('woff2')}
        *{box-sizing:border-box}body{font-family:Cairo,Arial,sans-serif;direction:rtl;color:#171717;margin:24px}
        header{border-bottom:3px solid #b5121b;margin-bottom:18px;padding-bottom:12px}
        h1{margin:0;color:#b5121b}p{margin:4px 0}.meta{display:grid;grid-template-columns:repeat(2,1fr);gap:6px}
        table{width:100%;border-collapse:collapse;margin-top:18px;font-size:11px}
        th,td{border:1px solid #bbb;padding:6px;text-align:right;vertical-align:top}th{background:#f1f1f1}
        footer{margin-top:16px;border-top:1px solid #ddd;padding-top:8px;font-size:10px}
        @page{size:A4 landscape;margin:12mm}@media print{button{display:none}thead{display:table-header-group}tr{break-inside:avoid}}
    </style>
</head>
<body>
    <button onclick="window.print()">طباعة / حفظ PDF</button>
    <header>
        @include('partials.print-brand')
        <h1>{{ $metadata['company_name'] }}</h1>
        <h2>{{ $definition['name'] }}</h2>
        <div class="meta">
            <p>الفترة: {{ $filters->dateFrom }} — {{ $filters->dateTo }}</p>
            <p>الفروع: {{ $metadata['branch_names'] }}</p>
            <p>العملة: {{ $metadata['currency_code'] }}</p>
            <p>أنشأه: {{ $metadata['generated_by'] }}</p>
        </div>
    </header>
    <table>
        <thead><tr>@foreach($definition['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
                @php($values = (array) $row)
                <tr>@foreach($definition['columns'] as $key => $label)<td>{{ $values[$key] ?? '—' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($definition['columns']) }}">لا توجد بيانات</td></tr>
            @endforelse
        </tbody>
    </table>
    <footer>تم الإنشاء: {{ now()->format('Y-m-d H:i:s') }} — المصدر: {{ $result->meta['data_source'] }}</footer>
    @if($pdfReady)<script>window.addEventListener('load',()=>window.print())</script>@endif
</body>
</html>
