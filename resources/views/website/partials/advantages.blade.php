<section class="sw-advantages sw-section">
    <div class="sw-shell">
        <x-website.section-heading :title="__('website.home.why_title')" light />

        <div class="sw-advantages__grid">
            @foreach (__('website.home.advantages') as $index => $advantage)
                <article class="sw-advantage sw-reveal">
                    <span class="sw-advantage__number">{{ $index + 1 }}</span>
                    <h3>{{ $advantage['title'] }}</h3>
                    <p>{{ $advantage['body'] }}</p>
                    @if (($advantage['xpel_link'] ?? false) === true)
                        <a href="{{ config('website.socials.xpel') }}" target="_blank" rel="noopener noreferrer">
                            {{ __('website.home.visit_xpel') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
