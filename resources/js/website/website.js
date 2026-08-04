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

    const revealItems = Array.from(websiteRoot.querySelectorAll('.sw-reveal, .sw-product-reveal'));

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

    websiteRoot.querySelectorAll('[data-sw-services-slider]').forEach((slider) => {
        const slides = Array.from(slider.querySelectorAll('[data-sw-services-slide]'));
        const previous = slider.querySelector('[data-sw-services-previous]');
        const next = slider.querySelector('[data-sw-services-next]');
        let activeIndex = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));

        const setActiveSlide = (index) => {
            activeIndex = (index + slides.length) % slides.length;

            slides.forEach((slide, slideIndex) => {
                const active = slideIndex === activeIndex;
                const video = slide.querySelector('video');

                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));

                if (!(video instanceof HTMLVideoElement)) return;

                if (active) {
                    video.currentTime = 0;
                } else {
                    video.pause();
                }
            });
        };

        previous?.addEventListener('click', () => setActiveSlide(activeIndex - 1));
        next?.addEventListener('click', () => setActiveSlide(activeIndex + 1));
        setActiveSlide(activeIndex);
    });

    websiteRoot.querySelectorAll('[data-sw-customer-stories]').forEach((gallery) => {
        const track = gallery.querySelector('[data-sw-customer-track]');
        const previous = gallery.querySelector('[data-sw-customer-previous]');
        const next = gallery.querySelector('[data-sw-customer-next]');
        const videos = Array.from(gallery.querySelectorAll('video'));

        const scrollByCard = (direction) => {
            const card = track?.querySelector('.sw-customer-story-video');
            if (!track || !card) return;

            const rtlDirection = document.documentElement.dir === 'rtl' ? -1 : 1;
            track.scrollBy({
                left: direction * rtlDirection * (card.getBoundingClientRect().width + 20),
                behavior: reducedMotion ? 'auto' : 'smooth',
            });
        };

        previous?.addEventListener('click', () => scrollByCard(-1));
        next?.addEventListener('click', () => scrollByCard(1));

        videos.forEach((video) => {
            video.addEventListener('play', () => {
                videos.filter((item) => item !== video).forEach((item) => item.pause());
            });
        });
    });

    websiteRoot.querySelectorAll('[data-sw-footer-country]').forEach((countryInput) => {
        countryInput.addEventListener('change', () => {
            if (!(countryInput instanceof HTMLInputElement) || !countryInput.checked) return;

            websiteRoot.querySelectorAll('[data-sw-footer-social]').forEach((socialLink) => {
                const countryKey = countryInput.value === 'egypt' ? 'egyptUrl' : 'saudiArabiaUrl';
                const url = socialLink.dataset[countryKey];

                if (url) socialLink.setAttribute('href', url);
            });
        });
    });

    const websiteVideos = Array.from(websiteRoot.querySelectorAll('video'));
    if (reducedMotion) {
        websiteVideos.forEach((video) => {
            video.autoplay = false;
            video.pause();
        });
    }
}
