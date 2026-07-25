@props(['status'])
@php
    $labels = [
        'active' => 'نشط', 'inactive' => 'غير نشط', 'suspended' => 'موقوف',
        'draft' => 'مسودة', 'pending' => 'قيد الانتظار', 'approved' => 'معتمد',
        'posted' => 'مرحّل', 'cancelled' => 'ملغي', 'completed' => 'مكتمل', 'rejected' => 'مرفوض',
    ];
@endphp
<span {{ $attributes->class(['sw-badge', "sw-badge--{$status}"]) }}>
    <span class="sw-badge__dot"></span>{{ $labels[$status] ?? $status }}
</span>
