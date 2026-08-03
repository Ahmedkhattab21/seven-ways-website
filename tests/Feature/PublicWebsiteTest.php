<?php

namespace Tests\Feature;

use App\Mail\WebsiteContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['website.contact.recipient' => 'website@example.com']);
    }

    public function test_public_website_pages_are_available_to_guests(): void
    {
        foreach ([
            'website.home',
            'website.about',
            'website.services',
            'website.contact',
            'website.register',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee(route('website.register'), false)
            ->assertSee('name="_token"', false);
    }

    public function test_authenticated_user_sees_registration_link_instead_of_dashboard_on_public_home(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('website.home'))
            ->assertOk()
            ->assertSee(route('website.register'), false)
            ->assertDontSee(route('dashboard'), false);
    }

    public function test_home_hero_keeps_the_reference_composition_in_both_locales(): void
    {
        $this->get(route('website.home', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('حماية متقدمة، أناقة مستمرة')
            ->assertSee('من نحن')
            ->assertSee('data-locale="ar"', false)
            ->assertSee('sw-hero__cta', false)
            ->assertSee('sw-hero__cta-frame', false)
            ->assertSee('sw-hero__cta-arrow', false);

        $this->get(route('website.home', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Premium Protection, Enduring Elegance')
            ->assertSee('ABOUT US')
            ->assertSee('data-locale="en"', false)
            ->assertSee('sw-hero__cta', false)
            ->assertSee('sw-hero__cta-frame', false)
            ->assertSee('sw-hero__cta-arrow', false);
    }

    public function test_home_advantages_keep_the_reference_composition_in_both_locales(): void
    {
        $this->get(route('website.home', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('لماذا أختار سفن وايز ؟')
            ->assertSee('sw-advantages__sliders', false)
            ->assertSee('sw-advantages__car', false)
            ->assertSee('sw-advantages__tyre-divider', false)
            ->assertSee('sw-advantage__xpel', false);

        $this->get(route('website.home', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Why choose SevenWays?')
            ->assertSee('Automatic cutting machines')
            ->assertSee('sw-advantages__sliders', false)
            ->assertSee('sw-advantages__car', false)
            ->assertSee('sw-advantages__tyre-divider', false)
            ->assertSee('sw-advantage__xpel', false);
    }

    public function test_home_services_and_products_keep_the_reference_composition(): void
    {
        $this->get(route('website.home', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('خدماتنا ومنتجاتنا')
            ->assertSee('sw-home-service__shape', false)
            ->assertSee('sw-home-brands', false)
            ->assertSee('sw-home-services__cta', false)
            ->assertSee('href="'.route('website.services').'#xpel"', false);

        $this->get(route('website.home', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Our Services &amp; Products', false)
            ->assertSee('Paint Protection Films PPF')
            ->assertSee('SHOW MORE')
            ->assertSee('sw-home-service__shape', false)
            ->assertSee('sw-home-brands', false);

        $this->get(route('website.services'))
            ->assertOk()
            ->assertSee('id="xpel"', false)
            ->assertSee('id="project3"', false)
            ->assertSee('id="osren"', false)
            ->assertSee('OSREN NAO GLAZE 28');
    }

    public function test_upx_brand_and_products_are_removed_from_the_public_website(): void
    {
        $this->assertFalse(collect(config('website.brand_logos'))->contains('id', 'upx'));
        $this->assertFalse(collect(config('website.product_packages'))->contains('id', 'upx'));

        foreach ([route('website.home'), route('website.services')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('UPX')
                ->assertDontSee('id="upx"', false)
                ->assertDontSee('uxp-package-', false)
                ->assertDontSee('logo-D3wbkwtS.webp', false);
        }
    }

    public function test_project_three_is_the_first_public_product(): void
    {
        $this->assertSame('project3', config('website.product_packages.0.id'));

        $this->get(route('website.services'))
            ->assertOk()
            ->assertSeeInOrder(['id="project3"', 'id="xpel"'], false);
    }

    public function test_polishing_products_from_the_approved_list_are_public(): void
    {
        $polishing = collect(config('website.product_packages'))
            ->filter(fn (array $product) => in_array('polishing', $product['sections'], true))
            ->pluck('id');

        foreach (['rupes', 'carpro', 'sonax', 'koch-chemie', 'meguiars', '3m', 'zerox'] as $product) {
            $this->assertTrue($polishing->contains($product), $product);
        }

        $this->get(route('website.services', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('RUPES Polishing Products')
            ->assertSee('CarPro Polishing Products')
            ->assertSee('SONAX Polishing Products')
            ->assertSee('Koch-Chemie Polishing Products')
            ->assertSee("Meguiar's Polishing Products")
            ->assertSee('3M Polishing Products')
            ->assertSee('ZeroX Polishing Products')
            ->assertSee('sw-product__brand-name', false);

        $this->get(route('website.home', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('RUPES')
            ->assertSee('SONAX')
            ->assertSee('Koch-Chemie')
            ->assertSee("Meguiar's")
            ->assertSee('ZeroX')
            ->assertSee('sw-home-brand__name', false);
    }

    public function test_about_page_keeps_the_reference_composition_in_both_locales(): void
    {
        $this->get(route('website.about', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('من نحن')
            ->assertSee('تاريخنا')
            ->assertSee('sw-about-hero__title', false)
            ->assertSee('sw-about__media', false)
            ->assertSee(config('website.assets.about_video'), false);

        $about = $this->get(route('website.about', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Who We Are')
            ->assertSee('Our History')
            ->assertSee('sw-about__copy', false)
            ->assertSee('sw-about__heading', false);

        $this->assertVideosRequireUserPlayback($about->getContent());
    }

    public function test_services_page_keeps_the_reference_composition_in_both_locales(): void
    {
        $this->get(route('website.services', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('sw-services-hero__title', false)
            ->assertSee('data-sw-services-slider', false)
            ->assertSee('sw-service-slide__media', false)
            ->assertSee('sw-product__packages', false)
            ->assertSee(config('website.assets.products_background'), false);

        $services = $this->get(route('website.services', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Our Services &amp; Products', false)
            ->assertSee('Our Services')
            ->assertSee('Our Products')
            ->assertSee('id="xpel"', false)
            ->assertSee('id="project3"', false);

        $this->assertVideosRequireUserPlayback(
            $services->getContent(),
            count(config('website.service_media', []))
        );
    }

    public function test_service_carousel_never_starts_video_or_sound_programmatically(): void
    {
        $script = file_get_contents(resource_path('js/website/website.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString('enableActiveSound', $script);
        $this->assertStringNotContainsString('playVideo(video', $script);
        $this->assertStringNotContainsString('.play()', $script);
    }

    public function test_contact_page_keeps_the_reference_composition_in_both_locales(): void
    {
        $arabic = $this->get(route('website.contact', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('sw-contact-hero__title', false)
            ->assertSee('sw-contact-countries', false)
            ->assertSee('sw-contact-branch__map', false)
            ->assertSee('<iframe', false)
            ->assertSee('https://wa.me/966534899166', false);

        $this->assertStringContainsString(
            'frame-src https://www.google.com https://maps.google.com',
            (string) $arabic->headers->get('Content-Security-Policy')
        );

        $this->get(route('website.contact', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Contact Us')
            ->assertSee('Our Branches')
            ->assertSee('Saudi Arabia')
            ->assertSee('Egypt')
            ->assertSee('Riyadh Branch')
            ->assertSee('Nasr City');
    }

    public function test_floating_call_button_uses_the_egypt_contact_number(): void
    {
        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('href="tel:+201099025564"', false);
    }

    public function test_floating_whatsapp_button_uses_the_egypt_contact_number(): void
    {
        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('href="https://wa.me/201118742044"', false);
    }

    public function test_alexandria_branch_uses_the_provided_coordinates(): void
    {
        $alexandria = collect(config('website.branches'))->firstWhere('id', 'alexandria');

        $this->assertNotNull($alexandria);
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query=31.26125,29.98375',
            $alexandria['map_link']
        );

        $this->get(route('website.contact', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('فرع الإسكندرية')
            ->assertSee('31.26125,29.98375', false);

        $this->get(route('website.contact', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Alexandria Branch')
            ->assertSee('31.26125,29.98375', false);
    }

    public function test_makkah_replaces_dammam_in_public_locations(): void
    {
        $makkah = collect(config('website.branches'))->firstWhere('id', 'makkah');

        $this->assertNotNull($makkah);
        $this->assertNull(collect(config('website.branches'))->firstWhere('id', 'dammam'));

        $this->get(route('website.contact', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('فرع مكة')
            ->assertDontSee('الدمام');

        $this->get(route('website.contact', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Makkah Branch')
            ->assertDontSee('Dammam');
    }

    public function test_registration_page_has_the_google_form_composition_in_both_locales(): void
    {
        $this->get(route('website.register', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('sw-registration-form', false)
            ->assertSee('sw-registration-card--intro', false)
            ->assertSee('name="country"', false)
            ->assertSee('name="service"', false);

        $this->get(route('website.register', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Registration Form')
            ->assertSee('Full name')
            ->assertSee('Vehicle model')
            ->assertSee('Clear form');
    }

    public function test_registration_form_validates_and_sends_the_request(): void
    {
        $this->from(route('website.register'))
            ->post(route('website.register.submit'), [])
            ->assertRedirect(route('website.register'))
            ->assertSessionHasErrors(['full_name', 'phone', 'country', 'city', 'vehicle_type', 'vehicle_model', 'service']);

        Mail::fake();

        $this->from(route('website.register'))
            ->post(route('website.register.submit'), [
                'full_name' => 'Ahmed Test',
                'phone' => '+201000000000',
                'email' => 'ahmed@example.com',
                'country' => 'egypt',
                'city' => 'Cairo',
                'vehicle_type' => 'Mercedes',
                'vehicle_model' => 'G Class',
                'vehicle_year' => 2025,
                'service' => 'ppf',
                'preferred_branch' => 'nasr-city',
                'notes' => 'Please contact me in the evening.',
                'website' => '',
            ])
            ->assertRedirect(route('website.register'))
            ->assertSessionHas('registration_success');

        Mail::assertSent(WebsiteContactMessage::class, 1);
    }

    public function test_footer_keeps_the_reference_composition_in_both_locales(): void
    {
        $this->get(route('website.home', ['lang' => 'ar']))
            ->assertOk()
            ->assertSee('فروعنا')
            ->assertSee('السعودية')
            ->assertSee('مصر')
            ->assertSee('sw-footer__country-switch', false)
            ->assertSee('sw-footer__car', false);

        $this->get(route('website.home', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Our Branches')
            ->assertSee('Saudi Arabia')
            ->assertSee('Egypt')
            ->assertSee('data-sw-footer-social', false);
    }

    public function test_language_switch_is_scoped_to_public_website(): void
    {
        $this->post(route('website.language', 'en'), [
            'redirect_to' => '/about-us',
        ])->assertRedirect('/about-us?lang=en');

        $this->get(route('website.about', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('hreflang="ar"', false)
            ->assertSee('hreflang="en"', false);
    }

    public function test_language_switch_rejects_external_redirects(): void
    {
        $this->post(route('website.language', 'en'), [
            'redirect_to' => '//example.com',
        ])->assertRedirect('/?lang=en');

        $this->post(route('website.language', 'ar'), [
            'redirect_to' => '/\\example.com',
        ])->assertRedirect('/?lang=ar');
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $this->from(route('website.contact'))
            ->post(route('website.contact.submit'), [])
            ->assertRedirect(route('website.contact'))
            ->assertSessionHasErrors(['name', 'phone', 'subject', 'message']);
    }

    public function test_contact_form_rejects_an_unknown_branch(): void
    {
        $this->from(route('website.contact'))
            ->post(route('website.contact.submit'), [
                'name' => 'Ahmed Test',
                'phone' => '+201000000000',
                'branch' => 'unknown-branch',
                'subject' => 'PPF service',
                'message' => 'I would like to ask about the available PPF packages.',
                'website' => '',
            ])
            ->assertRedirect(route('website.contact'))
            ->assertSessionHasErrors('branch');
    }

    public function test_contact_form_sends_a_message(): void
    {
        Mail::fake();

        $this->from(route('website.contact'))
            ->post(route('website.contact.submit'), [
                'name' => 'Ahmed Test',
                'phone' => '+201000000000',
                'email' => 'ahmed@example.com',
                'branch' => 'nasr-city',
                'subject' => 'PPF service',
                'message' => 'I would like to ask about the available PPF packages.',
                'website' => '',
            ])
            ->assertRedirect(route('website.contact'))
            ->assertSessionHas('contact_success');

        Mail::assertSent(WebsiteContactMessage::class, 1);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        Mail::fake();
        $request = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77']);
        $payload = [
            'name' => 'Rate Limit Test',
            'phone' => '+201000000001',
            'subject' => 'Service question',
            'message' => 'This is a valid website contact message for the throttle test.',
            'website' => '',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $request->post(route('website.contact.submit'), $payload)->assertRedirect();
        }

        $request->post(route('website.contact.submit'), $payload)->assertStatus(429);
    }

    public function test_public_sitemap_excludes_system_routes(): void
    {
        $this->get(route('website.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('?lang=ar')
            ->assertSee('?lang=en')
            ->assertDontSee('/login')
            ->assertDontSee('/dashboard');
    }

    public function test_system_service_route_is_preserved_without_public_collision(): void
    {
        $systemRoute = Route::getRoutes()->getByName('services.index');

        $this->assertSame('services', $systemRoute->uri());
        $this->assertSame('our-services', Route::getRoutes()->getByName('website.services')->uri());

        foreach (['auth', 'active.user', 'tenant', 'permission:services.view'] as $middleware) {
            $this->assertContains($middleware, $systemRoute->gatherMiddleware());
        }
    }

    public function test_required_public_assets_exist(): void
    {
        foreach ([
            'assets/brand/seven-ways-logo.webp',
            'assets/brand/seven-ways-logo.png',
            'assets/brand/seven-ways-mark.webp',
            'assets/brand/seven-ways-icon.webp',
            'assets/website/images/home-bg-DkJ_mK4W.webp',
            'assets/website/images/g-class-ar-Cv_phCfN.webp',
            'assets/website/images/white-car-2kDVYj1h.webp',
            'assets/website/images/tyre-mark-1-Bcet6rRb.png',
            'assets/website/images/logo-BBmOnB6N.webp',
            'assets/website/images/logo-D0I4roVz.webp',
            'assets/website/images/services-bg-BTO8wyrl.webp',
            'assets/website/images/protection-Bojyp1bE.webp',
            'assets/website/images/thermal-insulation-D5pv4bAo.webp',
            'assets/website/images/nano-ceramic-ClspLSNk.webp',
            'assets/website/images/polishing-kT06SIma.webp',
            'assets/website/images/osren-logo.webp',
            'assets/website/images/osren-nao-glaze-28.webp',
            'assets/website/images/audi-ar-DVxr30Bb.webp',
            'assets/website/images/tyre-mark-2-BISH33e4.png',
            'assets/website/fonts/cairo-arabic.woff2',
            'assets/website/videos/paint-protection-films-BFI-I27J.mp4',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }

        $this->assertFileExists(public_path(config('website.assets.about_video')));
        foreach (config('website.service_media', []) as $service) {
            $this->assertFileExists(public_path($service['video']));
        }
    }

    private function assertVideosRequireUserPlayback(string $html, int $expectedCount = 1): void
    {
        preg_match_all('/<video\b([^>]*)>/i', $html, $matches);

        $this->assertCount($expectedCount, $matches[1]);
        foreach ($matches[1] as $attributes) {
            $this->assertMatchesRegularExpression('/\bcontrols\b/i', $attributes);
            $this->assertMatchesRegularExpression('/\bplaysinline\b/i', $attributes);
            $this->assertDoesNotMatchRegularExpression('/\bautoplay\b/i', $attributes);
            $this->assertDoesNotMatchRegularExpression('/\bmuted\b/i', $attributes);
        }
    }
}
