<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

class ContactFormChallenge
{
    public const MINIMUM_SECONDS = 3;
    public const MAXIMUM_SECONDS = 7200;

    public function issue(Request $request): string
    {
        return Crypt::encryptString(json_encode([
            'version' => 1,
            'issued_at' => now()->getTimestamp(),
            'submission_key' => (string) Str::uuid(),
            'session' => $this->sessionFingerprint($request),
        ], JSON_THROW_ON_ERROR));
    }

    /** Return only an authenticated, sufficiently old challenge from this session. */
    public function read(Request $request, mixed $token, int $minimumAge = self::MINIMUM_SECONDS): ?array
    {
        if (! is_string($token) || strlen($token) > 4096 || $token === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            return null;
        }

        if (! is_array($data)
            || ($data['version'] ?? null) !== 1
            || ! is_int($data['issued_at'] ?? null)
            || ! is_string($data['submission_key'] ?? null)
            || ! Str::isUuid($data['submission_key'])
            || ! is_string($data['session'] ?? null)
            || ! hash_equals($this->sessionFingerprint($request), $data['session'])) {
            return null;
        }

        $age = now()->getTimestamp() - $data['issued_at'];

        return $age >= $minimumAge && $age <= self::MAXIMUM_SECONDS ? $data : null;
    }

    private function sessionFingerprint(Request $request): string
    {
        return hash('sha256', $request->session()->token());
    }
}
