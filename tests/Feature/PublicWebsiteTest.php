<?php

namespace Tests\Feature;

use App\Mail\WebsiteContactMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    public function test_public_website_pages_are_available_to_guests(): void
    {
        foreach ([
            'website.home',
            'website.about',
            'website.services',
            'website.contact',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertSee('name="_token"', false);
    }

    public function test_authenticated_user_sees_dashboard_link_on_public_home(): void
    {
        $this->actingAs(User::factory()->make())
            ->get(route('website.home'))
            ->assertOk()
            ->assertSee(route('dashboard'), false);
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
                'phone' => '+966500000000',
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
                'phone' => '+966500000000',
                'email' => 'ahmed@example.com',
                'branch' => 'qadisiyah',
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
            'phone' => '+966500000001',
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
            'assets/website/images/logo-DHNnkSwZ.webp',
            'assets/website/images/home-bg-DkJ_mK4W.webp',
            'assets/website/images/g-class-ar-Cv_phCfN.webp',
            'assets/website/fonts/cairo-arabic.woff2',
            'assets/website/videos/paint-protection-films-BFI-I27J.mp4',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }
}
