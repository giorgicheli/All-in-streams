<?php

namespace App\Http\Requests;

use App\Services\ContactFormChallenge;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    private bool $challengeChecked = false;

    private ?array $contactChallenge = null;

    private bool $challengePending = false;

    private bool $challengeRejected = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $changes = [];
        if (! $this->exists('subject')) {
            $changes['subject'] = 'Website enquiry';
        }
        foreach (['name', 'email', 'subject', 'message'] as $field) {
            if (is_string($this->input($field))) {
                $changes[$field] = trim($this->input($field));
            }
        }
        if (isset($changes['email'])) {
            $changes['email'] = mb_strtolower($changes['email']);
        }
        $this->merge($changes);
    }

    public function rules(): array
    {
        // Honeypot submissions are quietly discarded; expired human forms can retry.
        if ($this->submissionKey() === null) {
            $message = $this->challengePending ? 'Please wait a moment and try again.' : 'This form has expired. Please try again.';

            return $this->challengePending || $this->challengeRejected ? [
                'contact_challenge' => ['required', fn (string $attribute, mixed $value, \Closure $fail) => $fail($message)],
            ] : [];
        }

        $plainText = function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && (strip_tags($value) !== $value || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value))) {
                $fail('Use plain text without HTML or control characters.');
            }
        };

        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:120', 'not_regex:/[\r\n\t]/', $plainText],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:254'],
            'subject' => ['bail', 'required', 'string', 'min:3', 'max:160', 'not_regex:/[\r\n\t]/', $plainText],
            'message' => ['bail', 'required', 'string', 'min:10', 'max:5000', $plainText],
        ];
    }

    public function submissionKey(): ?string
    {
        if (! $this->challengeChecked) {
            $this->challengeChecked = true;
            $honeypot = $this->input('website');
            if ($honeypot === null || (is_string($honeypot) && trim($honeypot) === '')) {
                $this->contactChallenge = app(ContactFormChallenge::class)->read($this, $this->input('contact_challenge'), 0);
                $this->challengeRejected = $this->contactChallenge === null;
                if ($this->contactChallenge !== null && now()->getTimestamp() - $this->contactChallenge['issued_at'] < ContactFormChallenge::MINIMUM_SECONDS) {
                    $this->challengePending = true;
                    $this->contactChallenge = null;
                }
            }
        }

        return $this->contactChallenge === null ? null : hash('sha256', $this->contactChallenge['submission_key']);
    }

    public function messages(): array
    {
        return ['contact_challenge.required' => 'This form has expired. Please try again.'];
    }
}
