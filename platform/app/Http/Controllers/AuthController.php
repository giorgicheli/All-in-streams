<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\SocialProfileUrl;
use App\Rules\StreamingChannelUrl;
use App\Rules\TelegramHandle;
use App\Services\CreatorProofVideo;
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
        $role = $request->input('role');
        $email = $request->input('email');
        $accountType = $request->input('account_type');
        if ($role === 'business' && $request->input('account_type') === 'agency') $role = 'agency';
        $creator = $role === 'creator';
        $streamer = $creator && $request->input('creator_type') === 'streamer';
        $influencer = $creator && $request->input('creator_type') === 'influencer';
        $request->merge([
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'role' => $role,
            'account_type' => is_array($accountType) ? $accountType : ($creator ? null : ($role === 'agency' ? 'agency' : 'business')),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['nullable', 'string', 'max:180'], 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'role' => ['required', 'string', 'in:business,agency,creator'],
            'account_type' => ['nullable', 'string', 'in:business,agency'],
            'telegram' => ['required', 'string', 'max:100', new TelegramHandle],
            'creator_type' => $creator ? ['required', 'string', 'in:streamer,influencer'] : ['exclude'],
            'twitch_url' => $streamer ? ['nullable', 'required_without:kick_url', 'string', 'max:255', new StreamingChannelUrl('Twitch')] : ['exclude'],
            'kick_url' => $streamer ? ['nullable', 'required_without:twitch_url', 'string', 'max:255', new StreamingChannelUrl('Kick')] : ['exclude'],
            'facebook_url' => $influencer ? ['nullable', 'required_without_all:instagram_url,youtube_url,tiktok_url', 'string', 'max:255', new SocialProfileUrl('Facebook')] : ['exclude'],
            'instagram_url' => $influencer ? ['nullable', 'required_without_all:facebook_url,youtube_url,tiktok_url', 'string', 'max:255', new SocialProfileUrl('Instagram')] : ['exclude'],
            'youtube_url' => $influencer ? ['nullable', 'required_without_all:facebook_url,instagram_url,tiktok_url', 'string', 'max:255', new SocialProfileUrl('YouTube')] : ['exclude'],
            'tiktok_url' => $influencer ? ['nullable', 'required_without_all:facebook_url,instagram_url,youtube_url', 'string', 'max:255', new SocialProfileUrl('TikTok')] : ['exclude'],
            'audience_geos' => $creator ? ['required', 'array', 'min:1', 'max:30'] : ['exclude'],
            'audience_geos.*' => $creator ? ['required', 'string', 'max:80', 'distinct'] : ['exclude'],
            'verification_video' => $creator ? ['required', ...app(CreatorProofVideo::class)->rules()] : ['exclude'],
        ]);
        $data['telegram'] = TelegramHandle::canonicalize($data['telegram']);
        $video = $creator ? app(CreatorProofVideo::class)->store($request->file('verification_video')) : [];
        try {
        $user = DB::transaction(function () use ($data, $creator, $streamer, $video) {
            $user = User::create(collect($data)->only(['name', 'email', 'password', 'company', 'role', 'account_type', 'telegram'])->all());

            if ($creator) {
                $twitch = StreamingChannelUrl::canonicalize($data['twitch_url'] ?? null, 'Twitch');
                $kick = StreamingChannelUrl::canonicalize($data['kick_url'] ?? null, 'Kick');
                $social = [];
                foreach (SocialProfileUrl::FIELDS as $field => $platform) $social[$field] = SocialProfileUrl::canonicalize($data[$field] ?? null, $platform);
                $primarySocial = collect($social)->filter()->keys()->first();
                $user->creatorProfile()->create([
                    'creator_type' => $data['creator_type'],
                    'public_name' => $user->name, 'country' => '', 'language' => '',
                    'platform' => $streamer ? ($twitch ? 'Twitch' : 'Kick') : SocialProfileUrl::FIELDS[$primarySocial],
                    'channel_url' => $streamer ? ($twitch ?: $kick) : $social[$primarySocial],
                    'twitch_url' => $twitch, 'kick_url' => $kick,
                    'followers' => 0, 'average_viewers' => 0, 'audience_geos' => array_values(array_map('trim', $data['audience_geos'])),
                    'vertical' => 'Gaming', 'rate' => 0, 'commercial_model' => 'Open',
                    'availability' => 'Open', 'status' => 'draft',
                ] + $social + $video);
            } elseif ($user->role === 'agency') {
                $user->agencyProfile()->create([
                    'services' => ['supply', 'request'], 'country' => '', 'languages' => [],
                    'audience_geos' => [], 'platforms' => [], 'status' => 'draft',
                ]);
            }

            return $user;
        });
        } catch (\Throwable $exception) {
            app(CreatorProofVideo::class)->deletePath($video['verification_video_path'] ?? null);
            throw $exception;
        }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect(match ($user->role) { 'creator' => '/creator/apply', 'agency' => '/agency/dashboard', default => '/dashboard' });
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
        return match ($user->role) { 'admin' => '/admin', 'creator' => '/creator/opportunities', 'agency' => '/agency/dashboard', default => '/dashboard' };
    }
}
