<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['nullable', 'string', 'max:180'], 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'role' => ['required', 'in:business,creator'],
        ]);
        $user = User::create($data);
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
