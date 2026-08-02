@props(['status', 'label' => null])
@php
    $labels = [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'suspended' => 'موقوف',
        'draft' => 'مسودة',
        'pending' => 'قيد الانتظار',
        'recorded' => 'مسجلة',
        'allocated' => 'مخصصة بالكامل',
        'partially_allocated' => 'مخصصة جزئيًا',
        'confirmed' => 'مؤكد',
        'checked_in' => 'تم تسجيل الوصول',
        'in_progress' => 'قيد التنفيذ',
        'approved' => 'معتمد',
        'posted' => 'مُرحّل',
        'cancelled' => 'ملغي',
        'no_show' => 'لم يحضر',
        'completed' => 'مكتمل',
        'rejected' => 'مرفوض',
        'planned' => 'مخطط',
        'awaiting_inspection' => 'في انتظار الفحص',
        'inspection_completed' => 'اكتمل الفحص',
        'awaiting_materials' => 'في انتظار المواد',
        'paused' => 'متوقف مؤقتًا',
        'awaiting_quality' => 'في انتظار الجودة',
        'ready_for_delivery' => 'جاهز للتسليم',
        'delivered' => 'تم التسليم',
        'closed' => 'مغلق',
    ];
@endphp
<span {{ $attributes->class(['sw-badge', "sw-badge--{$status}"]) }}>
    <span class="sw-badge__dot"></span>{{ $label ?? $labels[$status] ?? $status }}
</span>
