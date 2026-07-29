<aside class="sw-sidebar" id="app-sidebar" data-sidebar aria-label="التنقل الرئيسي">
    <div class="sw-sidebar__header">
        <x-brand />
        <button class="sw-icon-button sw-sidebar__close" type="button" data-sidebar-close aria-label="إغلاق القائمة">
            <x-icon name="close" />
        </button>
    </div>

    <nav class="sw-sidebar__nav">
        @if($sidebarNavigation['setup'])
            @php($setup = $sidebarNavigation['setup'])
            <section class="sw-setup-progress" aria-labelledby="sidebar-setup-title">
                <div class="sw-setup-progress__header">
                    <div>
                        <strong id="sidebar-setup-title">إكمال إعداد النظام</strong>
                        <small>{{ $setup['completed'] }} من {{ $setup['total'] }} خطوات مكتملة</small>
                    </div>
                    <span>{{ round(($setup['completed'] / max($setup['total'], 1)) * 100) }}%</span>
                </div>
                <div class="sw-setup-progress__track" aria-hidden="true">
                    <span style="width: {{ ($setup['completed'] / max($setup['total'], 1)) * 100 }}%"></span>
                </div>
                <div class="sw-setup-progress__steps">
                    @foreach($setup['steps'] as $step)
                        <a class="sw-setup-step @if($step['complete']) sw-setup-step--complete @endif" href="{{ $step['url'] }}">
                            <span aria-hidden="true">{{ $step['complete'] ? '✓' : $loop->iteration }}</span>
                            <strong>{{ $step['label'] }}</strong>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @foreach($sidebarNavigation['sections'] as $section)
            @php($openByDefault = $section['active'] || $loop->first)
            <section
                class="sw-nav-group @if($section['active']) sw-nav-group--active @endif"
                data-sidebar-group
                data-sidebar-group-key="{{ $section['key'] }}"
                data-sidebar-group-active="{{ $section['active'] ? 'true' : 'false' }}"
            >
                <button
                    class="sw-nav-group__toggle"
                    type="button"
                    data-sidebar-group-toggle
                    aria-controls="sidebar-group-{{ $section['key'] }}"
                    aria-expanded="{{ $openByDefault ? 'true' : 'false' }}"
                >
                    <x-icon :name="$section['icon']" />
                    <span>{{ $section['label'] }}</span>
                    <small>{{ count($section['items']) }}</small>
                    <x-icon class="sw-nav-group__chevron" name="chevron" size="16" />
                </button>
                <div
                    class="sw-nav-group__items"
                    id="sidebar-group-{{ $section['key'] }}"
                    data-sidebar-group-panel
                    @if(! $openByDefault) hidden @endif
                >
                    @foreach($section['items'] as $item)
                        <a
                            class="sw-nav-item @if($item['active']) sw-nav-item--active @endif"
                            href="{{ $item['url'] }}"
                            @if($item['active']) aria-current="page" @endif
                        >
                            <x-icon :name="$item['icon']" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </nav>

    <div class="sw-sidebar__footer">
        <div class="sw-sidebar__status">
            <span class="sw-status-dot"></span>
            <div><strong>النظام متصل</strong><small>الأساس متعدد الفروع</small></div>
        </div>
    </div>
</aside>
