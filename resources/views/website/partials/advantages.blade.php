<section
    class="sw-advantages sw-section"
    style="--sw-advantages-background: url('{{ asset(config('website.assets.advantages_background')) }}')"
>
    <div class="sw-advantages__decoration" aria-hidden="true">
        <div class="sw-advantages__sliders">
            @for ($index = 0; $index < 12; $index++)
                <span
                    class="sw-advantages__slider sw-reveal"
                    style="--sw-slider-offset-wide: {{ $index * 120 }}px; --sw-slider-offset-medium: {{ $index * 100 }}px; --sw-slider-delay: {{ $index * 50 }}ms"
                ></span>
            @endfor
        </div>

        <div class="sw-advantages__car-motion sw-reveal">
            <img
                class="sw-advantages__car"
                src="{{ asset(config('website.assets.advantages_car')) }}"
                alt=""
                width="248"
                height="549"
                loading="lazy"
            >
        </div>
    </div>

    <div class="sw-shell sw-advantages__inner">
        <header class="sw-advantages__heading sw-reveal">
            <div>
                <img
                    src="{{ asset(config('website.assets.secondary_logo')) }}"
                    alt=""
                    width="702"
                    height="668"
                    aria-hidden="true"
                >
                <h2>{{ __('website.home.why_title') }}</h2>
            </div>
        </header>

        <div class="sw-advantages__grid">
            @foreach (__('website.home.advantages') as $index => $advantage)
                @php($hasXpelLink = ($advantage['xpel_link'] ?? false) === true)
                <article @class([
                    'sw-advantage',
                    'sw-reveal',
                    'sw-advantage--xpel' => $hasXpelLink,
                ])>
                    <h3><span>{{ $advantage['title'] }}</span></h3>
                    <div class="sw-advantage__content">
                        <span class="sw-advantage__number" aria-hidden="true">{{ $index + 1 }}</span>
                        <p>{{ $advantage['body'] }}</p>
                    </div>

                    @if ($hasXpelLink)
                        <a
                            class="sw-advantage__xpel"
                            href="{{ config('website.socials.xpel') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="{{ __('website.home.visit_xpel') }}"
                        >
                            <img
                                src="{{ asset(config('website.assets.xpel_logo')) }}"
                                alt="XPEL"
                                width="795"
                                height="215"
                                loading="lazy"
                            >
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>

    <div class="sw-advantages__tyre-divider" aria-hidden="true">
        @for ($index = 0; $index < 10; $index++)
            <img
                src="{{ asset(config('website.assets.tyre_mark_1')) }}"
                alt=""
                width="1200"
                height="155"
                loading="lazy"
            >
        @endfor
    </div>
</section>
