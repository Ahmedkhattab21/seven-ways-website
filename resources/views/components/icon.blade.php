@props(['name', 'size' => 20])

<svg {{ $attributes->merge(['class' => 'sw-icon', 'width' => $size, 'height' => $size]) }}
    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/>
            <rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>
            @break
        @case('sales')
        @case('trend')
            <path d="M3 17l6-6 4 4 8-9"/><path d="M15 6h6v6"/>
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
            @break
        @case('clipboard')
            <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6"/>
            @break
        @case('wrench')
            <path d="M14.7 6.3a4 4 0 00-5-5L12 3.6 8.4 7.2 6.1 4.9a4 4 0 005 5L19 17.8a1.7 1.7 0 01-2.4 2.4l-7.9-7.9"/>
            @break
        @case('box')
            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
            <path d="M3.3 7L12 12l8.7-5M12 22V12"/>
            @break
        @case('cart')
            <circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/>
            <path d="M3 4h2l2.7 11h10.8l2-8H6"/>
            @break
        @case('wallet')
            <rect x="2" y="5" width="20" height="15" rx="3"/><path d="M16 12h6M18 10v4M5 5V3h13v2"/>
            @break
        @case('building')
            <path d="M3 21h18M6 21V5l6-3 6 3v16M9 9h.01M15 9h.01M9 13h.01M15 13h.01M10 21v-4h4v4"/>
            @break
        @case('chart')
            <path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0015 19.4a1.7 1.7 0 00-1 .6 1.7 1.7 0 00-.4 1V21H9.6v-.08a1.7 1.7 0 00-1.1-1.56 1.7 1.7 0 00-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 004.6 15a1.7 1.7 0 00-.6-1 1.7 1.7 0 00-1-.4H3V9.6h.08A1.7 1.7 0 004.64 8.5a1.7 1.7 0 00-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 009 4.6a1.7 1.7 0 001-.6 1.7 1.7 0 00.4-1V3h4v.08a1.7 1.7 0 001.1 1.56 1.7 1.7 0 001.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0019.4 9c.1.38.3.73.6 1 .27.27.62.47 1 .56h.08v4H21A1.7 1.7 0 0019.4 15z"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
            @break
        @case('chevron')
            <path d="M9 18l6-6-6-6"/>
            @break
        @case('logout')
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
            @break
        @case('eye')
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('lock')
            <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>
            @break
        @case('car')
            <path d="M5 17H3v-5l2-2 2-4h10l2 4 2 2v5h-2M5 17h14M7 17v2M17 17v2M6 12h12"/>
            <circle cx="7.5" cy="14.5" r=".5" fill="currentColor"/><circle cx="16.5" cy="14.5" r=".5" fill="currentColor"/>
            @break
        @case('alert')
            <path d="M10.3 3.7L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.7a2 2 0 00-3.4 0z"/>
            <path d="M12 9v4M12 17h.01"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('receipt')
            <path d="M5 3h14v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21V3z"/>
            <path d="M8 8h8M8 12h8M8 16h5"/>
            @break
        @case('close')
            <path d="M18 6L6 18M6 6l12 12"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>
    @endswitch
</svg>
