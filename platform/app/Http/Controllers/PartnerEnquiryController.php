<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerEnquiryRequest;
use App\Models\PartnerEnquiry;
use App\Models\CreatorShowcase;
use App\Models\User;
use App\Rules\SocialProfileUrl;
use App\Rules\StreamingChannelUrl;
use App\Rules\TelegramHandle;
use App\Services\CreatorProofVideo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PartnerEnquiryController extends Controller
{
    public function create(Request $request): Response
    {
        if (!in_array($request->query('type'), array_keys(PartnerEnquiry::KINDS), true) && !$request->session()->hasOldInput('kind')) {
            return response()->view('site.choose-role', ['site' => app(\App\Services\SiteContent::class)->getPublished('settings')])->header('Cache-Control', 'private, no-store, max-age=0');
        }
        $kind = in_array($request->query('type'), array_keys(PartnerEnquiry::KINDS), true) ? $request->query('type') : 'brand';
        $creatorType = in_array($request->query('creator_type'), array_keys(PartnerEnquiry::CREATOR_TYPES), true) ? $request->query('creator_type') : 'streamer';
        $profileIds = $request->old('profiles', $request->query('profiles', []));
        $profileIds = is_array($profileIds) ? array_slice(array_filter($profileIds, fn ($id) => is_string($id) && Str::isUuid($id)), 0, 24) : [];
        // SQL may use the UUID index rather than the visitor's shortlist order.
        // Retain that order when rebuilding the form and its role-switch links.
        $selectedProfiles = CreatorShowcase::published()->whereIn('public_id', $profileIds)->get()
            ->sortBy(fn (CreatorShowcase $profile) => array_search($profile->public_id, $profileIds, true))
            ->values()->map->publicData();
        $region = $request->old('requested_region', $request->query('region'));
        return response()->view('site.talk-to-us', [
            'kind' => $kind,
            'creatorType' => $creatorType,
            'countries' => PartnerEnquiry::countries(),
            'currencies' => PartnerEnquiry::CURRENCIES,
            'submissionKey' => (string) Str::uuid(),
            'selectedProfiles' => $selectedProfiles,
            'requestedRegion' => is_string($region) && array_key_exists($region, CreatorShowcase::COUNTRIES_BY_REGION) ? $region : null,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(StorePartnerEnquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['shortlisted_profiles'] = $data['profiles'] ?? [];
        unset($data['profiles']);
        $data['telegram'] = TelegramHandle::canonicalize($data['telegram']);
        if (isset($data['twitch_url'])) $data['twitch_url'] = StreamingChannelUrl::canonicalize($data['twitch_url'], 'Twitch');
        if (isset($data['kick_url'])) $data['kick_url'] = StreamingChannelUrl::canonicalize($data['kick_url'], 'Kick');
        if (isset($data['instagram_url'])) $data['instagram_url'] = SocialProfileUrl::canonicalize($data['instagram_url'], 'Instagram');

        // A replayed key never authenticates an existing account, regardless of
        // the contact details supplied with it.
        if (PartnerEnquiry::where('submission_key', $data['submission_key'])->exists()) return $this->alreadyReceived();
        $creator = $data['kind'] === 'creator';
        $media = app(CreatorProofVideo::class);
        $video = $creator ? $media->store($request->file('verification_video')) : [];

        try {
            $user = DB::transaction(function () use ($data, $creator, $video): ?User {
                if (PartnerEnquiry::where('submission_key', $data['submission_key'])->lockForUpdate()->exists()) return null;
                $role = match ($data['kind']) { 'brand' => 'business', 'agency' => 'agency', default => 'creator' };
                $user = new User(Arr::only($data, ['name', 'email', 'password', 'telegram']));
                $user->forceFill([
                    'role' => $role,
                    'account_type' => $creator ? null : $role,
                    'company' => $creator ? null : Str::limit((string) parse_url($data['website'], PHP_URL_HOST), 180, ''),
                    'partner_verification_status' => 'pending',
                    'partner_verified_at' => null,
                    'partner_verified_by' => null,
                ])->save();

                if ($creator) {
                    $streamer = in_array($data['creator_type'], ['streamer', 'both'], true);
                    $twitch = $data['twitch_url'] ?? null;
                    $kick = $data['kick_url'] ?? null;
                    $user->creatorProfile()->create([
                        'creator_type' => $data['creator_type'], 'public_name' => $user->name,
                        'country' => $data['audience_geos'][0], 'language' => '',
                        'platform' => $streamer ? ($twitch ? 'Twitch' : 'Kick') : 'Instagram',
                        'channel_url' => $streamer ? ($twitch ?: $kick) : $data['instagram_url'],
                        'twitch_url' => $twitch, 'kick_url' => $kick,
                        'instagram_url' => $data['instagram_url'] ?? null,
                        'followers' => 0, 'average_viewers' => 0, 'audience_geos' => $data['audience_geos'],
                        'vertical' => 'Gaming', 'rate' => 0, 'commercial_model' => 'Open',
                        'availability' => 'Open', 'status' => 'pending',
                    ] + $video);
                } elseif ($role === 'agency') {
                    $user->agencyProfile()->create([
                        'services' => ['request'], 'country' => $data['audience_geos'][0],
                        'website' => Str::limit($data['website'], 255, ''), 'languages' => [],
                        'audience_geos' => $data['audience_geos'], 'platforms' => [], 'status' => 'pending',
                    ]);
                }

                $enquiry = Arr::except($data, ['password', 'password_confirmation', 'verification_video', 'age_confirmed']);
                PartnerEnquiry::create($enquiry + ['user_id' => $user->id, 'age_confirmed_at' => now()]);
                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $media->deletePath($video['verification_video_path'] ?? null);
            if (PartnerEnquiry::where('submission_key', $data['submission_key'])->exists()) return $this->alreadyReceived();
            throw ValidationException::withMessages(['email' => 'This email address is already registered. Sign in or use a different email address.']);
        } catch (Throwable $exception) {
            $media->deletePath($video['verification_video_path'] ?? null);
            throw $exception;
        }
        if (!$user) {
            $media->deletePath($video['verification_video_path'] ?? null);
            return $this->alreadyReceived();
        }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect('/account/pending');
    }

    private function alreadyReceived(): RedirectResponse
    {
        return redirect('/login')->with('status', 'This submission has already been received. Sign in to check your account.');
    }
}
