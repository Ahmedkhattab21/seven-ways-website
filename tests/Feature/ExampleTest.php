<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_the_public_home_page_is_available_to_guests()
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Seven Ways');
    }
}
