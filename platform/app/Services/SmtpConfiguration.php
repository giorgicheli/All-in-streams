<?php

namespace App\Services;

use App\Models\MailSetting;
use App\Models\User;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SmtpConfiguration
{
    public const MAILER = 'site_smtp';

    private const FIELDS = ['enabled', 'host', 'port', 'encryption', 'username', 'from_address', 'from_name', 'contact_recipient'];

    public function isReady(): bool
    {
        try {
            $setting = MailSetting::query()->find(1);

            return $setting?->enabled && $this->validConfiguration($setting);
        } catch (Throwable) {
            return false;
        }
    }

    /** Configure only this named mailer; unrelated application mailers are unchanged. */
    public function apply(): array
    {
        $this->forgetMailer();
        try {
            $setting = MailSetting::query()->find(1);
            if (! $setting?->enabled || ! $this->validConfiguration($setting)) {
                throw new RuntimeException('SMTP email is not configured.');
            }

            config(['mail.mailers.'.self::MAILER => [
                'transport' => 'smtp',
                'scheme' => $setting->encryption === 'ssl' ? 'smtps' : 'smtp',
                'host' => $setting->host,
                'port' => $setting->port,
                'username' => $setting->username,
                'password' => $setting->password,
                // Symfony's EsmtpTransportFactory accepts these DSN options.
                // require_tls rejects servers that omit or fail STARTTLS.
                'auto_tls' => true,
                'require_tls' => true,
                'verify_peer' => true,
                'timeout' => 10,
            ]]);

            return ['address' => $setting->from_address, 'name' => $setting->from_name];
        } catch (Throwable) {
            $this->forgetMailer();
            throw new RuntimeException('SMTP email is not configured.');
        }
    }

    public function save(User $actor, #[\SensitiveParameter] array $data): MailSetting
    {
        $this->guardAdministrator($actor);
        $setting = MailSetting::current();
        $input = Arr::only($data, [...self::FIELDS, 'password', 'clear_password']);
        if (! array_key_exists('contact_recipient', $input)) {
            $input['contact_recipient'] = $setting->contact_recipient;
        }
        if (is_string($input['contact_recipient'])) {
            $input['contact_recipient'] = mb_strtolower(trim($input['contact_recipient'])) ?: null;
        }
        $input['password'] ??= '';
        $input['clear_password'] ??= false;
        $validated = Validator::make($input, $this->rules())->validate();

        // A blank form field always preserves the existing encrypted password.
        $password = $validated['password'] !== '' && $validated['password'] !== null
            ? $validated['password']
            : ($validated['clear_password'] ? null : $setting->password);
        if ($validated['enabled'] && blank($password)) {
            throw ValidationException::withMessages(['password' => 'Enter an SMTP password before enabling email.']);
        }

        foreach (self::FIELDS as $field) $setting->{$field} = $validated[$field] ?? null;
        $setting->password = $password;
        $setting->updated_by = $actor->id;
        $setting->save();
        $this->forgetMailer();

        return $setting;
    }

    public function sendTest(User $actor, string $recipient): void
    {
        $this->guardAdministrator($actor);
        Validator::make(['recipient' => $recipient], ['recipient' => ['required', 'email:rfc', 'max:254']])->validate();
        $limitKey = 'smtp-test:'.$actor->id;
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            throw new RuntimeException('Please wait a minute before sending another test email.');
        }
        RateLimiter::hit($limitKey, 60);
        try {
            $from = $this->apply();
            Mail::mailer(self::MAILER)->raw(
                'Your All In Streams SMTP settings are working. This test contains no account or password-reset information.',
                function (Message $message) use ($recipient, $from): void {
                    $message->to($recipient)->from($from['address'], $from['name'])->subject('All In Streams email test');
                },
            );
        } catch (Throwable) {
            // Transport exceptions can contain connection details: never expose or log them.
            throw new RuntimeException('Unable to send the test email. Check the saved SMTP settings and server connection.');
        }
    }

    private function guardAdministrator(User $actor): void
    {
        abort_unless($actor->role === 'admin' && User::query()->whereKey($actor->id)->where('role', 'admin')->exists(), 403);
    }

    private function forgetMailer(): void
    {
        config(['mail.mailers.'.self::MAILER => null]);
        Mail::purge(self::MAILER);
    }

    private function validConfiguration(MailSetting $setting): bool
    {
        $data = $setting->only(self::FIELDS);
        $data['password'] = $setting->password;
        $data['clear_password'] = false;

        return Validator::make($data, $this->rules())->passes() && filled($setting->password);
    }

    private function rules(): array
    {
        $singleLine = 'regex:/\A[^\r\n\x00]*\z/u';

        return [
            'enabled' => ['required', 'boolean'],
            'host' => ['bail', 'nullable', 'required_if:enabled,true,1', 'string', 'max:253', function (string $attribute, mixed $value, \Closure $fail): void {
                if (preg_match('/[\x00-\x20\x7f\/\\\\@?#]/', $value)
                    || (! filter_var($value, FILTER_VALIDATE_IP) && ! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
                    $fail('Enter an SMTP hostname or IP address without a URL, path or port.');
                }
            }],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl'])],
            'username' => ['nullable', 'required_if:enabled,true,1', 'string', 'max:255', $singleLine],
            'password' => ['nullable', 'string', 'max:4096', $singleLine],
            'clear_password' => ['required', 'boolean'],
            'from_address' => ['nullable', 'required_if:enabled,true,1', 'email:rfc', 'max:254'],
            'from_name' => ['required', 'string', 'max:160', $singleLine],
            'contact_recipient' => ['nullable', 'email:rfc', 'max:254'],
        ];
    }
}
