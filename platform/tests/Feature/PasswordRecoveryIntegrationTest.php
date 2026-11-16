<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordRecoveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    public function test_public_and_admin_login_use_the_same_safe_recovery_entry(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/admin/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk()->assertSee('Send reset link')
            ->assertSee('name="referrer" content="no-referrer"', false)->assertDontSee('livewire.js');
    }

    public function test_recovery_redirects_and_validation_keep_sensitive_response_headers(): void
    {
        $token = str_repeat('a', 64);
        $responses = [
            $this->get('/forgot-password'),
            $this->get('/reset-password/'.$token.'?email=test@example.test'),
            $this->get('/reset-password/invalid'),
            $this->post('/forgot-password', ['email' => 'not an email']),
            $this->post('/reset-password', ['email' => 'test@example.test', 'token' => $token, 'password' => 'short', 'password_confirmation' => 'different']),
        ];
        foreach ($responses as $response) {
            $response->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-transform', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
            $this->assertStringContainsString("connect-src 'self'", $response->headers->get('Content-Security-Policy'));
        }
        $this->assertSame(['email' => 'test@example.test'], session('_old_input'));
    }

    public function test_unconfigured_smtp_fails_closed_without_tokens_or_public_settings_writes(): void
    {
        foreach (['known@example.test', 'unknown@example.test'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertRedirect('/forgot-password')
                ->assertSessionHas('status', 'Password reset email is temporarily unavailable. Please try again later.');
        }
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_recovery_ip_throttle_preserves_private_headers(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->post('/forgot-password', ['email' => 'unregistered-'.$index.'@example.test'])->assertRedirect('/forgot-password');
        }
        $response = $this->post('/forgot-password', ['email' => 'unregistered-11@example.test']);
        $response->assertStatus(429)->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-transform', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }
}
