<?php

namespace App\Livewire;

use App\Models\CreatorProfile;
use App\Rules\SocialProfileUrl;
use App\Rules\StreamingChannelUrl;
use App\Rules\TelegramHandle;
use App\Services\CreatorProofVideo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreatorApplication extends Component
{
    use WithFileUploads;
    #[Locked]
    public int $step = 1;
    #[Locked]
    public bool $acceptLegacyChannel = false;
    #[Locked]
    public bool $profileCommitted = false;
    public string $public_name = '';
    public string $creator_type = 'streamer';
    public string $telegram = '';
    public string $country = '';
    public string $language = 'English';
    public string $platform = 'Twitch';
    public string $channel_url = '';
    public string $twitch_url = '';
    public string $kick_url = '';
    public string $facebook_url = '';
    public string $instagram_url = '';
    public string $youtube_url = '';
    public string $tiktok_url = '';
    public $verification_video;
    #[Locked]
    public string $verification_video_original_name = '';
    public string $followers = '';
    public string $average_viewers = '';
    public array $audience_geos = [];
    public string $vertical = 'Gaming';
    public string $rate = '';
    public string $commercial_model = 'Fixed + CPA';
    public string $availability = 'Open';
    public string $bio = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'creator', 403);
        $this->telegram = Auth::user()->telegram ?? '';
        if ($profile = Auth::user()->creatorProfile) {
            abort_if($profile->trashed(), 403);
            $this->profileCommitted = $profile->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists();
            if ($this->profileCommitted) $this->step = 5;
            $this->acceptLegacyChannel = ! $profile->twitch_url && ! $profile->kick_url;
            foreach (array_keys($this->rules()) as $field) {
                if (! str_contains($field, '*') && !in_array($field, ['telegram', 'verification_video'], true) && $profile->{$field} !== null) {
                    $this->{$field} = is_array($profile->{$field}) ? $profile->{$field} : (string) $profile->{$field};
                }
            }
            $this->verification_video_original_name = $profile->verification_video_original_name ?? '';
            foreach (SocialProfileUrl::FIELDS as $field => $socialPlatform) {
                if (!$this->{$field} && $profile->platform === $socialPlatform) $this->{$field} = SocialProfileUrl::canonicalize($profile->channel_url, $socialPlatform) ?? '';
            }
            if (! $this->twitch_url && $profile->platform === 'Twitch') {
                $this->twitch_url = StreamingChannelUrl::canonicalize($profile->channel_url, 'Twitch') ?? '';
            }
            if (! $this->kick_url && $profile->platform === 'Kick') {
                $this->kick_url = StreamingChannelUrl::canonicalize($profile->channel_url, 'Kick') ?? '';
            }
        } else {
            $this->acceptLegacyChannel = true;
            $this->public_name = Auth::user()->name;
        }
    }

    protected function rules(): array
    {
        return [
            'creator_type' => ['required', 'in:streamer,influencer'],
            'telegram' => ['required', 'string', 'max:100', new TelegramHandle],
            'public_name' => ['required', 'string', 'min:2', 'max:120'],
            'country' => ['required', 'string', 'max:80'],
            'language' => ['required', 'string', 'max:80'],
            'platform' => ['required', $this->creator_type === 'influencer' ? 'in:Facebook,Instagram,YouTube,TikTok' : 'in:Twitch,Kick'],
            'channel_url' => ['required', 'url:https', 'max:255', $this->creator_type === 'influencer' ? new SocialProfileUrl($this->platform) : new StreamingChannelUrl($this->platform)],
            'twitch_url' => $this->creator_type === 'streamer' ? ['nullable', 'required_without:kick_url', 'string', 'max:255', new StreamingChannelUrl('Twitch')] : ['exclude'],
            'kick_url' => $this->creator_type === 'streamer' ? ['nullable', 'required_without:twitch_url', 'string', 'max:255', new StreamingChannelUrl('Kick')] : ['exclude'],
            'facebook_url' => $this->socialRules('facebook_url', 'Facebook'),
            'instagram_url' => $this->socialRules('instagram_url', 'Instagram'),
            'youtube_url' => $this->socialRules('youtube_url', 'YouTube'),
            'tiktok_url' => $this->socialRules('tiktok_url', 'TikTok'),
            'verification_video' => [$this->hasProofVideo() ? 'nullable' : 'required', ...app(CreatorProofVideo::class)->rules()],
            'followers' => ['required', 'integer', 'min:0', 'max:2000000000'],
            'average_viewers' => ['required', 'integer', 'min:0', 'max:100000000'],
            'audience_geos' => ['required', 'array', 'min:1', 'max:30'],
            'audience_geos.*' => ['required', 'string', 'max:80', 'distinct'],
            'vertical' => ['required', 'in:Gaming,Esports,iGaming'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'commercial_model' => ['required', 'in:Fixed,Fixed + CPA,CPA,Revenue share,Open'],
            'availability' => ['required', 'in:Open,Opening soon,Unavailable'],
            'bio' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function next(): void
    {
        $this->syncPrimaryChannel();
        $keys = match ($this->step) {
            1 => ['creator_type', 'telegram', 'public_name', 'country', 'language', 'bio'],
            2 => ['platform', 'channel_url', 'twitch_url', 'kick_url', 'facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'vertical'],
            3 => ['followers', 'average_viewers', 'audience_geos', 'audience_geos.*', 'verification_video'],
            4 => ['rate', 'commercial_model', 'availability'],
            default => [],
        };
        $this->validate(array_intersect_key($this->rules(), array_flip($keys)));
        $this->step = min(5, $this->step + 1);
        $this->resetValidation();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->resetValidation();
    }

    public function submit()
    {
        abort_unless(Auth::user()?->role === 'creator', 403);
        abort_if(Auth::user()->creatorProfile?->trashed(), 403);
        if (Auth::user()->creatorProfile?->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists()) {
            throw ValidationException::withMessages(['profile' => 'Your profile is committed to a contract or live campaign. Ask All In Streams before changing the agreed creator identity or channels.']);
        }
        $this->syncPrimaryChannel();
        $data = $this->validate();
        $telegram = TelegramHandle::canonicalize($data['telegram']);
        unset($data['telegram'], $data['verification_video']);
        $data['twitch_url'] = StreamingChannelUrl::canonicalize($data['twitch_url'] ?? null, 'Twitch');
        $data['kick_url'] = StreamingChannelUrl::canonicalize($data['kick_url'] ?? null, 'Kick');
        foreach (SocialProfileUrl::FIELDS as $field => $socialPlatform) $data[$field] = SocialProfileUrl::canonicalize($data[$field] ?? null, $socialPlatform);
        $video = $this->verification_video ? app(CreatorProofVideo::class)->store($this->verification_video) : [];
        $oldPath = null;
        try {
        DB::transaction(function () use ($data, $telegram, $video, &$oldPath) {
            $profile = CreatorProfile::withTrashed()->where('user_id', Auth::id())->lockForUpdate()->first();
            abort_if($profile?->trashed(), 403);
            if ($profile?->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists()) {
                throw ValidationException::withMessages(['profile' => 'Your profile is committed to a contract or live campaign. Ask All In Streams before changing the agreed creator identity or channels.']);
            }
            if (!$video && (!$profile || $profile->creator_type !== $data['creator_type'] || !$profile->verification_video_path || !Storage::disk('local')->exists($profile->verification_video_path))) {
                throw ValidationException::withMessages(['verification_video' => 'Upload a profile and audience verification video before submitting your profile.']);
            }
            if ($profile) {
                $oldPath = $profile->verification_video_path;
                $profile->update($data + $video + ['status' => 'pending']);
            } else CreatorProfile::create($data + $video + ['user_id' => Auth::id(), 'status' => 'pending']);
            Auth::user()->update(['telegram' => $telegram]);
        });
        } catch (\Throwable $exception) {
            app(CreatorProofVideo::class)->deletePath($video['verification_video_path'] ?? null);
            throw $exception;
        }
        if ($video && $oldPath !== $video['verification_video_path']) app(CreatorProofVideo::class)->deletePath($oldPath);
        session()->flash('status', 'Your creator profile is submitted for review. All In Streams will contact you with suitable opportunities.');
        return $this->redirectRoute('creator.opportunities', navigate: false);
    }

    private function syncPrimaryChannel(): void
    {
        if ($this->creator_type === 'influencer') {
            $valid = [];
            foreach (SocialProfileUrl::FIELDS as $field => $socialPlatform) {
                if ($url = SocialProfileUrl::canonicalize($this->{$field}, $socialPlatform)) $valid[$socialPlatform] = $url;
            }
            if (isset($valid[$this->platform])) $this->channel_url = $valid[$this->platform];
            elseif ($valid) { $this->platform = array_key_first($valid); $this->channel_url = $valid[$this->platform]; }
            else $this->channel_url = '';
            return;
        }
        if (!in_array($this->platform, ['Twitch', 'Kick'], true)) $this->platform = 'Twitch';

        $twitch = StreamingChannelUrl::canonicalize($this->twitch_url, 'Twitch');
        $kick = StreamingChannelUrl::canonicalize($this->kick_url, 'Kick');
        $primary = $this->platform === 'Twitch' ? $twitch : $kick;

        if ($primary || $twitch || $kick) {
            $this->channel_url = $primary ?: ($twitch ?: $kick);
            $this->platform = $this->channel_url === $kick ? 'Kick' : 'Twitch';
        } elseif ($this->acceptLegacyChannel && ! trim($this->twitch_url) && ! trim($this->kick_url)) {
            if ($legacy = StreamingChannelUrl::canonicalize($this->channel_url, $this->platform)) {
                $this->channel_url = $legacy;
                $field = $this->platform === 'Twitch' ? 'twitch_url' : 'kick_url';
                $this->{$field} = $legacy;
            }
        } elseif (! $this->acceptLegacyChannel) {
            $this->channel_url = '';
        }
    }

    private function socialRules(string $field, string $platform): array
    {
        return $this->creator_type === 'influencer'
            ? ['nullable', 'required_without_all:'.implode(',', array_diff(array_keys(SocialProfileUrl::FIELDS), [$field])), 'string', 'max:255', new SocialProfileUrl($platform)]
            : ['exclude'];
    }

    private function hasProofVideo(): bool
    {
        $profile = Auth::user()?->creatorProfile;
        return $profile && $profile->creator_type === $this->creator_type && $profile->verification_video_path && Storage::disk('local')->exists($profile->verification_video_path);
    }

    public function render()
    {
        return view('livewire.creator-application')->layout('layouts.portal', ['title' => 'Creator application']);
    }
}
