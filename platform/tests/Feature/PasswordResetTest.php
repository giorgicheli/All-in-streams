<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PartnerResetPassword;
use App\Services\SmtpConfiguration;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://streams.example.test',
            'mail.default' => 'log',
        ]);
        $this->withoutVite();
    }

    private function smtp(bool $ready = true): void
    {
        $this->mock(SmtpConfiguration::class, function ($mock) use ($ready): void {
            $mock->shouldReceive('isReady')->andReturn($ready);
            if ($ready) {
                $mock->shouldReceive('apply')->andReturn(['address' => 'accounts@example.test', 'name' => 'All In Streams']);
            } else {
                $mock->shouldNotReceive('apply');
            }
        });
    }

    private function resetData(User $user, string $token, array $overrides = []): array
    {
        return array_replace([
            'email' => $user->email,
            'token' => $token,
            'password' => 'New-Account-Password-927',
            'password_confirmation' => 'New-Account-Password-927',
        ], $overrides);
    }

    public function test_recovery_forms_use_private_responses_without_account_disclosure(): void
    {
        $this->smtp(false);
        $this->get('/forgot-password')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $token = str_repeat('a', 64);
        $response = $this->get('/reset-password/'.$token.'?email=Person%40example.test');
        $response->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('name="token"', false)->assertSee($token)->assertSee('person@example.test');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/reset-password/invalid')->assertRedirect('/forgot-password');
        $this->assertGuest();
    }

    public function test_every_role_receives_only_a_canonical_https_smtp_notification_and_hashed_token(): void
    {
        $this->smtp();
        Notification::fake();
        foreach (['admin', 'business', 'agency', 'creator'] as $index => $role) {
            $user = User::factory()->create(['email' => $role.'+recovery@example.test', 'role' => $role]);
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)])
                ->post('https://untrusted.example/forgot-password', ['email' => '  '.strtoupper($user->email).'  '])
                ->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
            Notification::assertSentTo($user, PartnerResetPassword::class, function ($notification, $channels) use ($user): bool {
                $message = $notification->toMail($user);
                $this->assertSame(['mail'], $channels);
                $this->assertSame(SmtpConfiguration::MAILER, $message->mailer);
                $this->assertSame(['accounts@example.test', 'All In Streams'], $message->from);
                $this->assertStringStartsWith('https://streams.example.test/reset-password/', $message->actionUrl);
                $this->assertStringNotContainsString('untrusted.example', $message->actionUrl);
                $this->assertStringContainsString('%2B', $message->actionUrl);
                parse_str(parse_url($message->actionUrl, PHP_URL_QUERY), $query);
                $this->assertSame($user->email, $query['email']);
                $rawToken = basename(parse_url($message->actionUrl, PHP_URL_PATH));
                $storedToken = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
                $this->assertNotSame($rawToken, $storedToken);
                $this->assertTrue(Hash::check($rawToken, $storedToken));
                return true;
            });
        }
        Notification::assertCount(4);
        $this->assertGuest();
    }

    public function test_known_unknown_and_broker_throttled_requests_have_the_same_public_acknowledgement(): void
    {
        $this->smtp();
        Notification::fake();
        $user = User::factory()->create(['role' => 'business']);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');
        $acknowledgement = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('status', $acknowledgement);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $acknowledgement);
        Notification::assertSentToTimes($user, PartnerResetPassword::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->travel(61)->seconds();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $acknowledgement);
        Notification::assertSentToTimes($user, PartnerResetPassword::class, 2);
    }

    public function test_normalized_email_request_limit_cannot_be_bypassed_by_changing_case_or_ip(): void
    {
        $this->smtp();
        Notification::fake();
        $user = User::factory()->create(['email' => 'limited@example.test', 'role' => 'creator']);
        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$attempt])
                ->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
            $this->travel(61)->seconds();
        }
        Notification::assertSentToTimes($user, PartnerResetPassword::class, 5);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->post('/forgot-password', ['email' => '  LIMITED@EXAMPLE.TEST  '])->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, PartnerResetPassword::class, 5);
        $this->assertSame(5, RateLimiter::attempts('password-reset-email:'.hash('sha256', $user->email)));
    }

    public function test_disabled_smtp_fails_closed_for_known_and_unknown_addresses_before_creating_tokens(): void
    {
        $this->smtp(false);
        Notification::fake();
        $user = User::factory()->create(['role' => 'business']);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');
        $unavailable = session('status');
        $this->assertStringContainsString('temporarily unavailable', $unavailable);
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('status', $unavailable);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_runtime_smtp_failure_removes_the_token_without_exposing_or_logging_secrets(): void
    {
        $this->smtp();
        Log::spy();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('PRIVATE_SMTP_CREDENTIAL_962'));
        $user = User::factory()->create(['role' => 'agency']);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');
        $knownAcknowledgement = session('status');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('status', $knownAcknowledgement);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertStringNotContainsString('PRIVATE_SMTP_CREDENTIAL_962', json_encode(session()->all()));
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_reset_rotates_credentials_revokes_database_sessions_and_preserves_partner_state(): void
    {
        $this->smtp(false);
        Event::fake([PasswordReset::class]);
        $user = User::factory()->unverified()->pendingPartner()->create(['role' => 'business']);
        $other = User::factory()->create(['role' => 'creator']);
        $oldRemember = $user->remember_token;
        foreach (['target-phone' => $user->id, 'target-desktop' => $user->id, 'other-partner' => $other->id] as $sessionId => $userId) {
            DB::table('sessions')->insert(['id' => $sessionId, 'user_id' => $userId, 'payload' => '', 'last_activity' => now()->timestamp]);
        }
        $token = Password::broker()->createToken($user);
        $this->post('/reset-password', $this->resetData($user, $token, ['email' => '  '.strtoupper($user->email).'  ']))
            ->assertRedirect('/login')->assertSessionHasNoErrors();
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('New-Account-Password-927', $fresh->password));
        $this->assertNotSame($oldRemember, $fresh->remember_token);
        $this->assertSame('business', $fresh->role);
        $this->assertSame('pending', $fresh->partner_verification_status);
        $this->assertNull($fresh->partner_verified_at);
        $this->assertNull($fresh->email_verified_at);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-partner']);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Event::assertDispatchedTimes(PasswordReset::class, 1);
        $this->assertGuest();
    }

    public function test_pending_rejected_and_verified_accounts_of_every_role_can_recover_without_approval_changes(): void
    {
        $this->smtp(false);
        foreach (['admin' => 'verified', 'business' => 'pending', 'agency' => 'rejected', 'creator' => 'verified'] as $role => $state) {
            $user = User::factory()->create(['role' => $role, 'partner_verification_status' => $state]);
            $token = Password::broker()->createToken($user);
            $verifiedAt = $user->partner_verified_at;
            $this->post('/reset-password', $this->resetData($user, $token))->assertRedirect('/login');
            $fresh = $user->fresh();
            $this->assertSame($role, $fresh->role);
            $this->assertSame($state, $fresh->partner_verification_status);
            $this->assertEquals($verifiedAt, $fresh->partner_verified_at);
            $this->assertTrue(Hash::check('New-Account-Password-927', $fresh->password));
            $this->assertGuest();
        }
    }

    public function test_successful_token_is_single_use_and_cannot_reset_a_different_account(): void
    {
        $this->smtp(false);
        $user = User::factory()->create(['role' => 'creator']);
        $other = User::factory()->create(['role' => 'agency']);
        $token = Password::broker()->createToken($user);
        $oldOtherPassword = $other->password;
        $this->post('/reset-password', $this->resetData($other, $token))->assertSessionHasErrors('email');
        $this->assertSame($oldOtherPassword, $other->fresh()->password);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->post('/reset-password', $this->resetData($user, $token))->assertRedirect('/login');
        $newHash = $user->fresh()->password;
        $this->post('/reset-password', $this->resetData($user, $token, ['password' => 'Another-Password-928', 'password_confirmation' => 'Another-Password-928']))
            ->assertSessionHasErrors('email');
        $this->assertSame($newHash, $user->fresh()->password);
    }

    public function test_expired_or_invalid_tokens_cannot_change_credentials(): void
    {
        $this->smtp(false);
        $user = User::factory()->create(['role' => 'business']);
        $token = Password::broker()->createToken($user);
        $oldHash = $user->password;
        $this->travel(61)->minutes();
        $this->post('/reset-password', $this->resetData($user, $token))->assertSessionHasErrors('email');
        $this->post('/reset-password', $this->resetData($user, str_repeat('b', 64)))->assertSessionHasErrors('email');
        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_invalid_passwords_preserve_only_email_and_never_flash_tokens_or_passwords(): void
    {
        $this->smtp(false);
        $user = User::factory()->create(['role' => 'creator']);
        $token = Password::broker()->createToken($user);
        foreach ([
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => 'LettersOnlyPassword', 'password_confirmation' => 'LettersOnlyPassword'],
            ['password_confirmation' => 'Different-Password-123'],
        ] as $invalid) {
            $this->post('/reset-password', $this->resetData($user, $token, $invalid))->assertSessionHasErrors('password');
            $this->assertSame(['email' => $user->email], session()->getOldInput());
            $this->assertTrue(Password::broker()->tokenExists($user, $token));
        }
        $this->post('/reset-password', $this->resetData($user, $token, ['token' => ['malformed']]))->assertRedirect('/forgot-password');
    }

    public function test_notification_rejects_a_non_https_canonical_origin(): void
    {
        config(['app.url' => 'http://streams.example.test']);
        $user = User::factory()->make(['role' => 'business']);
        $notification = new PartnerResetPassword(str_repeat('a', 64), ['address' => 'accounts@example.test', 'name' => 'All In Streams']);
        $this->expectException(RuntimeException::class);
        $notification->toMail($user);
    }
}
