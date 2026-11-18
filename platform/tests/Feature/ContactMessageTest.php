<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use App\Services\ContactFormChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_TOKEN = 'isolated-contact-session-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32))]);
        $this->withoutVite();
        $this->withSession(['_token' => self::SESSION_TOKEN]);
        Mail::fake();
    }

    private function challenge(int $age = 3): string
    {
        $issued = now()->subSeconds($age);
        return $this->travelTo($issued, function (): string {
            $request = Request::create('/contact');
            $request->setLaravelSession(app('session')->driver());

            return app(ContactFormChallenge::class)->issue($request);
        });
    }

    private function submission(array $changes = []): array
    {
        return array_replace([
            'name' => 'Alex Partner', 'email' => 'alex@example.test',
            'subject' => 'A creator campaign', 'message' => 'We would like to discuss our next creator campaign.',
            'contact_challenge' => $this->challenge(), 'website' => '',
        ], $changes);
    }

    public function test_contact_page_issues_a_private_session_challenge(): void
    {
        $response = $this->get('/contact')->assertOk()
            ->assertViewIs('site.contact')
            ->assertViewHas('contactMinimumSeconds', 3)
            ->assertSee('name="contact_challenge"', false)
            ->assertSee('name="_token"', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertIsString($response->viewData('contactChallenge'));
        $this->assertDatabaseCount('contact_messages', 0);
        $data = $this->submission(['contact_challenge' => $response->viewData('contactChallenge')]);
        $this->travel(3)->seconds();
        $this->post('/contact', $data)
            ->assertRedirect('/contact')->assertSessionHas('contact_status');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_guest_message_is_private_normalized_and_cannot_set_staff_fields(): void
    {
        $this->post('/contact', $this->submission([
            'name' => '  Alex Partner  ', 'email' => '  ALEX@EXAMPLE.TEST ',
            'status' => 'replied', 'staff_notes' => 'Injected staff note', 'submission_key' => 'chosen-by-client',
        ]))->assertRedirect('/contact')->assertSessionHas('contact_status');

        $message = ContactMessage::sole();
        $this->assertSame('Alex Partner', $message->name);
        $this->assertSame('alex@example.test', $message->email);
        $this->assertSame('new', $message->status);
        $this->assertNull($message->staff_notes);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $message->submission_key);
        $this->assertArrayNotHasKey('submission_key', $message->toArray());
        $this->assertArrayNotHasKey('staff_notes', $message->toArray());
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingSent();
        $this->get('/contact/'.$message->id)->assertNotFound();
    }

    public function test_each_partner_role_can_contact_staff_without_creating_another_account(): void
    {
        foreach (['business', 'agency', 'creator', 'admin'] as $index => $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post('/contact', $this->submission(['email' => $role.'@example.test']))
                ->assertRedirect('/contact')->assertSessionHas('contact_status');
            $this->assertDatabaseCount('contact_messages', $index + 1);
        }
        $this->assertDatabaseCount('users', 4);
        Mail::assertNothingSent();
    }

    public function test_replayed_challenge_does_not_duplicate_or_replace_the_original_message(): void
    {
        $data = $this->submission();
        $this->post('/contact', $data)->assertRedirect('/contact');
        $original = ContactMessage::sole();
        $this->post('/contact', array_replace($data, ['message' => 'This replay must not overwrite the original.']))
            ->assertRedirect('/contact')->assertSessionHas('contact_status');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertSame($original->message, $original->fresh()->message);
    }

    public function test_invalid_and_expired_forms_can_retry_without_losing_the_message(): void
    {
        $cases = [
            ['contact_challenge' => 'invalid'],
            ['contact_challenge' => $this->challenge(7201)],
            ['contact_challenge' => $this->challenge(-60)],
        ];
        foreach ($cases as $index => $changes) {
            $data = $this->submission($changes + ['email' => 'expired-'.$index.'@example.test']);
            $this->from('/contact')->post('/contact', $data)->assertRedirect('/contact')
                ->assertSessionHasErrors(['contact_challenge' => 'This form has expired. Please try again.'])
                ->assertSessionHasInput('message', $data['message']);
        }
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_honeypot_submissions_are_quietly_discarded(): void
    {
        foreach (['https://spam.example.test', ['bot-filled-array']] as $website) {
            $this->post('/contact', $this->submission(['website' => $website]))
                ->assertRedirect('/contact')->assertSessionHas('contact_status')->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_fast_legitimate_submission_can_retry_without_losing_its_fields(): void
    {
        $data = $this->submission(['contact_challenge' => $this->challenge(2)]);
        $this->from('/contact')->post('/contact', $data)->assertRedirect('/contact')
            ->assertSessionHasErrors(['contact_challenge' => 'Please wait a moment and try again.'])
            ->assertSessionHasInput('message', $data['message']);
        $this->assertDatabaseCount('contact_messages', 0);
        $this->travel(1)->seconds();
        $this->post('/contact', $data)->assertRedirect('/contact')->assertSessionHas('contact_status');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_correcting_fields_after_validation_does_not_restart_the_form_wait(): void
    {
        $token = $this->get('/contact')->assertOk()->viewData('contactChallenge');
        $data = $this->submission(['contact_challenge' => $token, 'subject' => 'x']);
        $this->travel(3)->seconds();
        $this->from('/contact')->post('/contact', $data)->assertSessionHasErrors('subject');
        $retry = $this->get('/contact')->assertOk();
        $this->assertSame($token, $retry->viewData('contactChallenge'));
        $this->assertDatabaseCount('contact_messages', 0);
        $this->post('/contact', array_replace($data, ['subject' => 'Corrected subject']))
            ->assertRedirect('/contact')->assertSessionHas('contact_status');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_expired_old_challenge_is_replaced_without_discarding_entered_fields(): void
    {
        $data = $this->submission(['contact_challenge' => $this->challenge(7201)]);
        $this->from('/contact')->post('/contact', $data)->assertSessionHasErrors('contact_challenge');
        $retry = $this->get('/contact')->assertOk()->assertSee($data['message']);
        $newToken = $retry->viewData('contactChallenge');
        $this->assertNotSame($data['contact_challenge'], $newToken);
        $request = Request::create('/contact');
        $request->setLaravelSession(app('session')->driver());
        $this->assertNotNull(app(ContactFormChallenge::class)->read($request, $newToken, 0));
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_challenge_is_bound_to_its_original_session_and_rejects_tampering(): void
    {
        $data = $this->submission();
        $this->withSession(['_token' => 'a-different-browser-session']);
        $this->from('/contact')->post('/contact', $data)->assertRedirect('/contact')->assertSessionHasErrors('contact_challenge');
        $this->withSession(['_token' => self::SESSION_TOKEN]);
        $data['contact_challenge'][20] = $data['contact_challenge'][20] === 'a' ? 'b' : 'a';
        $this->from('/contact')->post('/contact', $data)->assertRedirect('/contact')->assertSessionHasErrors('contact_challenge');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_valid_challenge_keeps_structured_validation_errors_and_rejects_markup(): void
    {
        $this->from('/contact')->post('/contact', $this->submission([
            'name' => '<script>alert(1)</script>', 'email' => 'not-an-email',
            'subject' => str_repeat('x', 161), 'message' => '<img src=x onerror=alert(1)>',
        ]))->assertRedirect('/contact')->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);
        $this->from('/contact')->post('/contact', $this->submission(['message' => str_repeat('x', 5001)]))
            ->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_email_limit_applies_across_different_ip_addresses(): void
    {
        for ($index = 1; $index <= 4; $index++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$index])
                ->post('/contact', $this->submission())->assertRedirect('/contact');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.5'])
            ->post('/contact', $this->submission())->assertStatus(429);
        $this->assertDatabaseCount('contact_messages', 4);
    }

    public function test_ip_limit_applies_even_when_bots_change_email_addresses(): void
    {
        for ($index = 0; $index < 8; $index++) {
            $this->post('/contact', $this->submission([
                'email' => 'ip-limit-'.$index.'@example.test', 'website' => 'bot-filled',
            ]))->assertRedirect('/contact');
        }
        $this->post('/contact', $this->submission(['email' => 'one-more@example.test']))->assertStatus(429);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_csrf_is_required_outside_the_framework_unit_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        try {
            $this->post('/contact', $this->submission())->assertStatus(419);
            $this->post('/contact', $this->submission(['_token' => self::SESSION_TOKEN]))->assertRedirect('/contact');
            $this->assertDatabaseCount('contact_messages', 1);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_model_permissions_only_allow_administrators_to_read_or_manage_messages(): void
    {
        $message = ContactMessage::create([
            'name' => 'Private Contact', 'email' => 'private@example.test', 'subject' => 'Private subject',
            'message' => 'This message is only visible to administrators.', 'submission_key' => str_repeat('a', 64),
        ]);
        foreach (['business', 'agency', 'creator', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $gate = Gate::forUser($user);
            $allowed = $role === 'admin';
            $this->assertSame($allowed, $gate->allows('viewAny', ContactMessage::class));
            foreach (['view', 'update', 'delete'] as $action) {
                $this->assertSame($allowed, $gate->allows($action, $message));
            }
            $this->assertSame($allowed, $gate->allows('deleteAny', ContactMessage::class));
            $this->assertFalse($gate->allows('create', ContactMessage::class));
        }
        $this->assertFalse(Gate::allows('view', $message));
    }
}
