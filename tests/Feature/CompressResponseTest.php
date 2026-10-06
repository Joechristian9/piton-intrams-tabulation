<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompressResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_pages_are_gzipped_for_browsers_that_accept_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.events.index'), ['Accept-Encoding' => 'gzip, deflate, br']);

        $response->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $this->assertContains('Accept-Encoding', $response->getVary());   // alongside Inertia's own Vary
        $html = gzdecode($response->getContent());
        $this->assertStringContainsString('data-page', $html);
        $this->assertLessThan(strlen($html), strlen($response->getContent()));
    }

    public function test_no_gzip_without_accept_encoding_or_for_tiny_responses(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.events.index'))->assertHeaderMissing('Content-Encoding');

        // The live-update pollers return a few bytes of JSON: not worth compressing.
        $judge = User::factory()->create(['role' => 'judge']);
        $this->actingAs($judge)->getJson(route('judge.notifications'), ['Accept-Encoding' => 'gzip'])
            ->assertOk()->assertHeaderMissing('Content-Encoding');
    }
}
