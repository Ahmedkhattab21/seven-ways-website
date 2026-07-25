import './bootstrap';

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
};

ready(() => {
    const appShell = document.querySelector('[data-app-shell]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');
    const desktopBreakpoint = window.matchMedia('(min-width: 992px)');

    const syncSidebarState = () => {
        if (!appShell || !sidebarToggle) return;

        const expanded = desktopBreakpoint.matches
            ? !appShell.classList.contains('is-sidebar-collapsed')
            : appShell.classList.contains('is-sidebar-open');

        sidebarToggle.setAttribute('aria-expanded', String(expanded));
    };

    if (appShell && localStorage.getItem('sw-sidebar-collapsed') === 'true' && desktopBreakpoint.matches) {
        appShell.classList.add('is-sidebar-collapsed');
    }

    sidebarToggle?.addEventListener('click', () => {
        if (desktopBreakpoint.matches) {
            appShell?.classList.toggle('is-sidebar-collapsed');
            localStorage.setItem(
                'sw-sidebar-collapsed',
                String(appShell?.classList.contains('is-sidebar-collapsed')),
            );
        } else {
            appShell?.classList.toggle('is-sidebar-open');
        }

        syncSidebarState();
    });

    sidebarCloseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            appShell?.classList.remove('is-sidebar-open');
            syncSidebarState();
        });
    });

    desktopBreakpoint.addEventListener('change', () => {
        appShell?.classList.remove('is-sidebar-open');
        syncSidebarState();
    });

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        trigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu?.hasAttribute('hidden');
            menu?.toggleAttribute('hidden');
            trigger.setAttribute('aria-expanded', String(willOpen));
        });

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target)) {
                menu?.setAttribute('hidden', '');
                trigger?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = toggle.closest('.sw-field__control')?.querySelector('[data-password-input]');
            if (!input) return;

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-label', showing ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
        });
    });

    document.querySelectorAll('[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-submit-button]');
            button?.setAttribute('disabled', '');
            button?.querySelector('[data-button-label]')?.setAttribute('hidden', '');
            button?.querySelector('[data-button-loading]')?.removeAttribute('hidden');
        });
    });

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            document.getElementById(trigger.dataset.modalOpen)?.removeAttribute('hidden');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            document.getElementById(trigger.dataset.modalClose)?.setAttribute('hidden', '');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.sw-modal:not([hidden])').forEach((modal) => modal.setAttribute('hidden', ''));
            appShell?.classList.remove('is-sidebar-open');
            syncSidebarState();
        }
    });

    syncSidebarState();
});
