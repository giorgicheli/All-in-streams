<?php

namespace Tests\Feature;

use App\Filament\Pages\MailSettings;
use App\Models\MailSetting;
use App\Models\User;
use App\Services\SmtpConfiguration;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32))]);
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function settings(array $overrides = []): array
    {
        return array_replace([
            'enabled' => true, 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'sender@example.test', 'password' => 'private-smtp-test-secret',
            'from_address' => 'hello@example.test', 'from_name' => 'All In Streams', 'clear_password' => false,
        ], $overrides);
    }

    private function administrator(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_settings_and_actions_are_available_only_to_current_administrators(): void
    {
        $service = app(SmtpConfiguration::class);
        foreach (['business', 'agency', 'creator'] as $role) {
            $partner = User::factory()->create(['role' => $role]);
            $this->actingAs($partner);
            $this->assertFalse(MailSettings::canAccess());
            Livewire::test(MailSettings::class)->assertForbidden();
            try {
                $service->save($partner, $this->settings());
                $this->fail('A partner must not save SMTP settings.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $admin = $this->administrator();
        Livewire::test(MailSettings::class)->assertSuccessful()->assertSet('data.password', '');
        $component = Livewire::test(MailSettings::class);
        $admin->update(['role' => 'business']);
        $component->call('save')->assertForbidden();
        $this->assertDatabaseMissing('mail_settings', ['enabled' => true]);
    }

    public function test_new_settings_are_disabled_and_credentials_are_encrypted_and_hidden(): void
    {
        $this->assertFalse(app(SmtpConfiguration::class)->isReady());
        $this->assertFalse(MailSetting::current()->enabled);
        $saved = app(SmtpConfiguration::class)->save($this->administrator(), $this->settings());
        $raw = DB::table('mail_settings')->value('password');

        $this->assertNotSame($this->settings()['password'], $raw);
        $this->assertSame($this->settings()['password'], $saved->fresh()->password);
        $this->assertArrayNotHasKey('password', $saved->toArray());
        $this->assertStringNotContainsString($this->settings()['password'], $saved->toJson());
        $this->assertTrue(app(SmtpConfiguration::class)->isReady());
        $this->assertDatabaseCount('mail_settings', 1);
    }

    public function test_contact_notification_recipient_is_editable_validated_and_can_be_cleared(): void
    {
        $service = app(SmtpConfiguration::class);
        $admin = $this->administrator();
        $service->save($admin, $this->settings(['contact_recipient' => '  CONTACT@EXAMPLE.TEST  ']));
        $this->assertSame('contact@example.test', MailSetting::current()->contact_recipient);
        Livewire::test(MailSettings::class)->assertSet('data.contact_recipient', 'contact@example.test')
            ->set('data.contact_recipient', 'new-inbox@example.test')->call('save')->assertHasNoErrors();
        $this->assertSame('new-inbox@example.test', MailSetting::current()->contact_recipient);
        // Callers changing connection settings alone retain the recipient.
        $service->save($admin, $this->settings());
        $this->assertSame('new-inbox@example.test', MailSetting::current()->contact_recipient);
        Livewire::test(MailSettings::class)->set('data.contact_recipient', 'not-an-email')->call('save')->assertHasFormErrors(['contact_recipient']);
        $this->assertSame('new-inbox@example.test', MailSetting::current()->contact_recipient);
        Livewire::test(MailSettings::class)->set('data.contact_recipient', '')->call('save')->assertHasNoErrors();
        $this->assertNull(MailSetting::current()->contact_recipient);
    }

    public function test_model_rejects_mass_assignment_and_service_ignores_unapproved_fields(): void
    {
        try {
            new MailSetting(['enabled' => true, 'password' => 'must-not-be-assigned']);
            $this->fail('SMTP fields must not be mass assignable.');
        } catch (MassAssignmentException) {
            $this->assertDatabaseCount('mail_settings', 0);
        }
        $admin = $this->administrator();
        $saved = app(SmtpConfiguration::class)->save($admin, $this->settings(['id' => 9, 'updated_by' => 999, 'transport' => 'log']));
        $this->assertSame(1, $saved->id);
        $this->assertSame($admin->id, $saved->updated_by);
        $this->assertArrayNotHasKey('transport', $saved->getAttributes());
    }

    public function test_blank_password_retains_the_secret_and_an_explicit_clear_removes_it(): void
    {
        $service = app(SmtpConfiguration::class);
        $admin = $this->administrator();
        $service->save($admin, $this->settings());
        $service->save($admin, $this->settings(['password' => '', 'from_name' => 'Updated sender']));
        $this->assertSame($this->settings()['password'], MailSetting::current()->password);

        $service->save($admin, $this->settings(['enabled' => false, 'password' => '', 'clear_password' => true]));
        $this->assertNull(MailSetting::current()->password);
        $this->assertFalse($service->isReady());
        try {
            $service->save($admin, $this->settings(['password' => '']));
            $this->fail('An enabled SMTP configuration must include a password.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
    }

    public function test_admin_form_never_loads_the_saved_password_and_clears_a_new_password_after_save(): void
    {
        app(SmtpConfiguration::class)->save($this->administrator(), $this->settings());
        $component = Livewire::test(MailSettings::class)->assertSet('data.password', '')
            ->assertDontSee($this->settings()['password']);
        $component->set('data.password', 'replacement-smtp-secret')->call('save')->assertHasNoErrors()
            ->assertSet('data.password', '')->assertSet('data.clear_password', false);
        $this->assertSame('replacement-smtp-secret', MailSetting::current()->password);
        $this->assertStringNotContainsString('replacement-smtp-secret', json_encode($component->get('data')));
    }

    public function test_invalid_hosts_ports_header_values_and_plaintext_modes_are_rejected(): void
    {
        $service = app(SmtpConfiguration::class);
        $admin = $this->administrator();
        foreach ([
            ['host' => 'https://smtp.example.test'], ['host' => 'smtp.example.test/path'],
            ['host' => 'smtp.example.test:587'], ['host' => "smtp.example.test\r\nEHLO attacker"],
            ['port' => 0], ['port' => 65536], ['encryption' => 'none'],
            ['host' => null], ['username' => null], ['from_address' => null],
            ['host' => ['smtp.example.test']],
            ['from_name' => "Sender\r\nBcc: victim@example.test"], ['username' => "sender\nINJECT"],
        ] as $overrides) {
            try {
                $service->save($admin, $this->settings($overrides));
                $this->fail('Unsafe SMTP configuration must be rejected.');
            } catch (ValidationException) {
                $this->assertFalse($service->isReady());
            }
        }
    }

    public function test_apply_requires_verified_tls_and_purges_cached_mailers_without_using_the_network(): void
    {
        $service = app(SmtpConfiguration::class);
        $admin = $this->administrator();
        $default = config('mail.default');
        $service->save($admin, $this->settings());
        $this->assertSame(['address' => 'hello@example.test', 'name' => 'All In Streams'], $service->apply());
        $first = Mail::mailer(SmtpConfiguration::MAILER);
        $transport = $first->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertTrue($transport->isTlsRequired());
        $this->assertTrue($transport->isAutoTls());
        $this->assertSame(10.0, $transport->getStream()->getTimeout());
        $this->assertTrue(config('mail.mailers.'.SmtpConfiguration::MAILER.'.verify_peer'));

        $service->save($admin, $this->settings(['encryption' => 'ssl', 'port' => 465]));
        $service->apply();
        $second = Mail::mailer(SmtpConfiguration::MAILER);
        $this->assertNotSame($first, $second);
        $this->assertTrue($second->getSymfonyTransport()->getStream()->isTLS());
        $this->assertTrue($second->getSymfonyTransport()->isTlsRequired());
        $this->assertSame($default, config('mail.default'));
    }

    public function test_disabled_or_incomplete_settings_fail_closed_and_remove_stale_transport_configuration(): void
    {
        $service = app(SmtpConfiguration::class);
        $admin = $this->administrator();
        $service->save($admin, $this->settings());
        $service->apply();
        Mail::mailer(SmtpConfiguration::MAILER);
        $setting = MailSetting::current();
        $setting->enabled = false;
        $setting->save();
        $this->assertFalse($service->isReady());
        try {
            $service->apply();
            $this->fail('Disabled SMTP must not fall back to a different transport.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP email is not configured.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertNull(config('mail.mailers.'.SmtpConfiguration::MAILER));
        }
    }

    public function test_test_mail_uses_saved_sender_and_sanitizes_transport_errors_without_sending_external_mail(): void
    {
        $service = app(SmtpConfiguration::class);
        $admin = $this->administrator();
        $service->save($admin, $this->settings());
        $mailer = Mockery::mock();
        $mailer->shouldReceive('raw')->once()->with(Mockery::type('string'), Mockery::on(function ($callback): bool {
            $message = new Message(new Email);
            $callback($message);
            $this->assertSame('test-recipient@example.test', $message->getSymfonyMessage()->getTo()[0]->getAddress());
            $this->assertSame('hello@example.test', $message->getSymfonyMessage()->getFrom()[0]->getAddress());

            return true;
        }));
        Mail::shouldReceive('purge')->twice()->with(SmtpConfiguration::MAILER);
        Mail::shouldReceive('mailer')->with(SmtpConfiguration::MAILER)->once()->andReturn($mailer);
        $service->sendTest($admin, 'test-recipient@example.test');
        Mail::shouldReceive('mailer')->with(SmtpConfiguration::MAILER)->once()
            ->andThrow(new RuntimeException('Connection failed smtp://username:private-smtp-test-secret@smtp.example.test'));
        try {
            $service->sendTest($admin, 'test-recipient@example.test');
            $this->fail('The transport failure must surface as a sanitized error.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('private-smtp-test-secret', $exception->getMessage());
            $this->assertStringNotContainsString('smtp.example.test', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_disabled_test_mail_never_connects_and_repeated_admin_requests_are_throttled(): void
    {
        $admin = $this->administrator();
        Mail::shouldReceive('purge')->zeroOrMoreTimes()->with(SmtpConfiguration::MAILER);
        Mail::shouldReceive('mailer')->never();
        $service = app(SmtpConfiguration::class);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $service->sendTest($admin, 'test-recipient@example.test');
                $this->fail('Disabled SMTP must not send a test email.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Unable to send the test email.', $exception->getMessage());
            }
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please wait a minute before sending another test email.');
        $service->sendTest($admin, 'test-recipient@example.test');
    }
}
