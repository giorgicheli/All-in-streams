<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SiteContent;
use App\Support\SiteContentDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContactPageContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32))]);
        $this->withoutVite();
    }

    public function test_contact_copy_follows_publication_while_the_email_block_stays_hidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SiteContentDefaults::settings();
        $settings['contact']['title'] = 'A private contact headline';
        $settings['contact']['email'] = 'unpublished@example.test';
        app(SiteContent::class)->saveDraft($admin, 'settings', $settings);
        $this->get('/contact')->assertOk()->assertDontSee('info@streams.com')->assertDontSee('Email us')
            ->assertDontSee('A private contact headline')->assertDontSee('unpublished@example.test');

        app(SiteContent::class)->publish($admin, 'settings');
        $this->get('/contact')->assertOk()->assertSee('A private contact headline')
            ->assertDontSee('mailto:unpublished@example.test', false);
        $this->get('/')->assertOk()->assertSee(route('contact.create'))->assertSee('Contact');
    }

    public function test_public_contact_email_validation_rejects_invalid_mailto_targets(): void
    {
        $settings = SiteContentDefaults::settings();
        $settings['contact']['email'] = 'javascript:alert(1)';
        try {
            app(SiteContent::class)->validate('settings', $settings);
            $this->fail('A non-email contact destination was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contact.email', $exception->errors());
        }
    }
}
