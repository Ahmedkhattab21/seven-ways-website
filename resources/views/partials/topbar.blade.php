@php
    $user = auth()->user();
    $tenant = app(\App\Core\Tenancy\TenantContext::class);
    $currentBranch = $tenant->branch();
    $branches = $tenant->accessibleBranches();
    $initials = collect(preg_split('/\s+/', trim($user->name ?? 'مستخدم')))
        ->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $unreadNotifications = \App\Models\SystemNotification::query()
        ->where('company_id', $user->company_id)->where('user_id', $user->id)->whereNull('read_at')->count();
@endphp

<header class="sw-topbar">
    <div class="sw-topbar__start">
        <button class="sw-icon-button" type="button" data-sidebar-toggle aria-label="فتح أو إغلاق القائمة" aria-controls="app-sidebar" aria-expanded="true"><x-icon name="menu" /></button>
        <div class="sw-topbar__title"><strong>@yield('page-title', 'لوحة التحكم')</strong><span>Seven Ways ERP</span></div>
    </div>
    <div class="sw-topbar__actions">
        @if($branches->count() > 1)
            <form method="POST" action="{{ route('branch-context.store') }}" class="sw-branch-selector">
                @csrf
                <x-icon name="building" :size="18" />
                <select name="branch_id" aria-label="الفرع الحالي" onchange="this.form.submit()">
                    @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($currentBranch?->id === $branch->id)>{{ $branch->name }}</option>@endforeach
                </select>
            </form>
        @elseif($currentBranch)
            <div class="sw-branch-selector"><x-icon name="building" :size="18" /><strong>{{ $currentBranch->name }}</strong></div>
        @endif
        @if($user->hasPermission('notifications.view'))<a class="sw-icon-button sw-topbar__notification" href="{{ route('notifications.index') }}" aria-label="الإشعارات"><x-icon name="bell" />@if($unreadNotifications)<small>{{ $unreadNotifications }}</small>@endif</a>@endif
        <div class="sw-user-menu" data-dropdown>
            <button class="sw-user-menu__trigger" type="button" data-dropdown-trigger aria-expanded="false">
                <span class="sw-avatar">{{ $initials ?: 'SW' }}</span>
                <span class="sw-user-menu__copy"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
                <x-icon name="chevron" :size="15" />
            </button>
            <div class="sw-dropdown" data-dropdown-menu hidden>
                <div class="sw-dropdown__identity"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="sw-dropdown__item"><x-icon name="logout" :size="18" />تسجيل الخروج</button></form>
            </div>
        </div>
    </div>
</header>
