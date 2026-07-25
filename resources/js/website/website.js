const websiteRoot = document.querySelector('.sw-website');

if (websiteRoot) {
    const header = websiteRoot.querySelector('[data-sw-header]');
    const menuToggle = websiteRoot.querySelector('[data-sw-menu-toggle]');
    const mobileMenu = websiteRoot.querySelector('[data-sw-mobile-menu]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const closeMenu = () => {
        if (!menuToggle || !mobileMenu) return;

        menuToggle.setAttribute('aria-expanded', 'false');
        mobileMenu.setAttribute('hidden', '');
    };

    const syncHeader = () => {
        header?.classList.toggle('is-scrolled', window.scrollY > 24);
    };

    menuToggle?.addEventListener('click', () => {
        const opening = menuToggle.getAttribute('aria-expanded') !== 'true';
        menuToggle.setAttribute('aria-expanded', String(opening));
        mobileMenu?.toggleAttribute('hidden', !opening);
    });

    mobileMenu?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });

    window.addEventListener('scroll', syncHeader, { passive: true });
    syncHeader();

    const revealItems = Array.from(websiteRoot.querySelectorAll('.sw-reveal'));

    if ('IntersectionObserver' in window && !reducedMotion) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            rootMargin: '0px 0px -8% 0px',
            threshold: 0.08,
        });

        revealItems.forEach((item) => revealObserver.observe(item));
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    websiteRoot.querySelectorAll('[data-sw-slider]').forEach((slider) => {
        const track = slider.querySelector('[data-sw-slider-track]');
        const previous = slider.querySelector('[data-sw-slider-previous]');
        const next = slider.querySelector('[data-sw-slider-next]');

        if (!track) return;

        const scrollByCard = (direction) => {
            const card = track.querySelector('.sw-video-card');
            const distance = card ? card.getBoundingClientRect().width + 24 : track.clientWidth * 0.8;
            const rtlDirection = document.documentElement.dir === 'rtl' ? -1 : 1;

            track.scrollBy({
                left: direction * rtlDirection * distance,
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
        };

        previous?.addEventListener('click', () => scrollByCard(-1));
        next?.addEventListener('click', () => scrollByCard(1));
    });

    const websiteVideos = Array.from(websiteRoot.querySelectorAll('video'));
    const serviceVideos = Array.from(websiteRoot.querySelectorAll('.sw-video-card video'));

    if (reducedMotion) {
        websiteVideos.forEach((video) => {
            video.autoplay = false;
            video.pause();
        });
    }

    if ('IntersectionObserver' in window) {
        const videoObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!(entry.target instanceof HTMLVideoElement)) return;

                if (entry.isIntersecting && !reducedMotion) {
                    entry.target.play().catch(() => {});
                } else {
                    entry.target.pause();
                }
            });
        }, { threshold: 0.45 });

        serviceVideos.forEach((video) => videoObserver.observe(video));
    }
}
