<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\StreamingChannelUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The email address or password is incorrect.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->intended($this->home($request->user()));
    }

    public function register(Request $request): RedirectResponse
    {
        $creator = $request->input('role') === 'creator';
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'account_type' => $creator ? null : ($request->input('account_type') ?: 'business'),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['nullable', 'string', 'max:180'], 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'role' => ['required', 'in:business,creator'],
            'account_type' => ['nullable', 'in:business,agency'],
            'twitch_url' => $creator ? ['nullable', 'required_without:kick_url', 'string', 'max:255', new StreamingChannelUrl('Twitch')] : ['exclude'],
            'kick_url' => $creator ? ['nullable', 'required_without:twitch_url', 'string', 'max:255', new StreamingChannelUrl('Kick')] : ['exclude'],
        ]);
        $user = DB::transaction(function () use ($data, $creator) {
            $user = User::create(collect($data)->except(['twitch_url', 'kick_url'])->all());

            if ($creator) {
                $twitch = StreamingChannelUrl::canonicalize($data['twitch_url'] ?? null, 'Twitch');
                $kick = StreamingChannelUrl::canonicalize($data['kick_url'] ?? null, 'Kick');
                $user->creatorProfile()->create([
                    'public_name' => $user->name, 'country' => '', 'language' => '',
                    'platform' => $twitch ? 'Twitch' : 'Kick', 'channel_url' => $twitch ?: $kick,
                    'twitch_url' => $twitch, 'kick_url' => $kick,
                    'followers' => 0, 'average_viewers' => 0, 'audience_geos' => [],
                    'vertical' => 'Gaming', 'rate' => 0, 'commercial_model' => 'Open',
                    'availability' => 'Open', 'status' => 'draft',
                ]);
            }

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        return redirect($user->role === 'creator' ? '/creator/apply' : '/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function home(User $user): string
    {
        return match ($user->role) { 'admin' => '/admin', 'creator' => '/creator/opportunities', default => '/dashboard' };
    }
}
