<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PartnerResetPassword;
use App\Services\SmtpConfiguration;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class PasswordResetController extends Controller
{
    private const ACKNOWLEDGEMENT = 'If an account matches that email address, a password reset link will be sent. If it does not arrive, please try again later.';
    private const UNAVAILABLE = 'Password reset email is temporarily unavailable. Please try again later.';
    private const INVALID_LINK = 'This password reset link is invalid or has expired. Request a new link.';

    public function __construct(private readonly SmtpConfiguration $smtp) {}

    public function requestForm(Request $request): Response
    {
        return $this->privateView('auth.forgot-password', ['email' => $this->normalizeEmail($request->old('email'))]);
    }

    public function resetForm(Request $request, string $token): Response|RedirectResponse
    {
        if (!$this->validTokenFormat($token)) {
            return redirect('/forgot-password')->with('status', self::INVALID_LINK);
        }

        return $this->privateView('auth.reset-password', [
            'token' => $token,
            'email' => $this->normalizeEmail($request->old('email', $request->query('email'))),
        ]);
    }

    public function email(Request $request): RedirectResponse
    {
        $email = $this->normalizeEmail($request->input('email'));
        $validator = Validator::make(['email' => $email], ['email' => ['bail', 'required', 'string', 'email', 'max:255']]);
        if ($validator->fails()) {
            return redirect('/forgot-password')->withErrors($validator)->withInput(['email' => $email]);
        }

        $limitKey = 'password-reset-email:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return redirect('/forgot-password')->with('status', self::ACKNOWLEDGEMENT);
        }
        RateLimiter::hit($limitKey, 15 * 60);

        try {
            if (!$this->smtp->isReady()) {
                return redirect('/forgot-password')->with('status', self::UNAVAILABLE);
            }
            $sender = $this->smtp->apply();
        } catch (Throwable) {
            return redirect('/forgot-password')->with('status', self::UNAVAILABLE);
        }

        $broker = Password::broker();
        try {
            $broker->sendResetLink(['email' => $email], function (User $user, #[\SensitiveParameter] string $token) use ($broker, $sender): void {
                try {
                    $user->notify(new PartnerResetPassword($token, $sender));
                } catch (Throwable) {
                    $broker->deleteToken($user);
                }
            });
        } catch (Throwable) {
            // Delivery failures use the same public response as unknown accounts.
        }

        return redirect('/forgot-password')->with('status', self::ACKNOWLEDGEMENT);
    }

    public function update(Request $request): RedirectResponse
    {
        $email = $this->normalizeEmail($request->input('email'));
        $token = $request->input('token');
        if (!is_string($token) || !$this->validTokenFormat($token)) {
            return redirect('/forgot-password')->with('status', self::INVALID_LINK);
        }

        $data = [
            'email' => $email,
            'token' => $token,
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
        ];
        $validator = Validator::make($data, [
            'email' => ['bail', 'required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $returnUrl = '/reset-password/'.rawurlencode($token).'?'.http_build_query(['email' => $email], '', '&', PHP_QUERY_RFC3986);
        if ($validator->fails()) {
            return redirect($returnUrl)->withErrors($validator)->withInput(['email' => $email]);
        }

        $brokerName = (string) config('auth.defaults.passwords');
        $tokenTable = (string) config('auth.passwords.'.$brokerName.'.table', 'password_reset_tokens');
        $tokenConnection = config('auth.passwords.'.$brokerName.'.connection');
        $connection = DB::connection($tokenConnection);
        try {
            $status = $connection->transaction(function () use ($connection, $tokenTable, $data): string {
                $connection->table($tokenTable)->where('email', $data['email'])->lockForUpdate()->first();
                return Password::broker()->reset($data, function (User $user, #[\SensitiveParameter] string $password): void {
                    $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                    DB::connection(config('session.connection'))->table((string) config('session.table', 'sessions'))
                        ->where('user_id', $user->getKey())->delete();
                    event(new PasswordReset($user));
                });
            }, 3);
        } catch (Throwable) {
            return redirect($returnUrl)->withErrors(['email' => 'The password could not be reset. Please try again.'])->withInput(['email' => $email]);
        }

        if ($status !== Password::PASSWORD_RESET) {
            return redirect($returnUrl)->withErrors(['email' => self::INVALID_LINK])->withInput(['email' => $email]);
        }
        if (Auth::check()) {
            Auth::logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }

    private function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? Str::lower(trim($email)) : '';
    }

    private function validTokenFormat(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/i', $token) === 1;
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
