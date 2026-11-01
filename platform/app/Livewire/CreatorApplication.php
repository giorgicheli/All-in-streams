<?php

namespace App\Livewire;

use App\Models\CreatorProfile;
use App\Rules\StreamingChannelUrl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CreatorApplication extends Component
{
    #[Locked]
    public int $step = 1;
    #[Locked]
    public bool $acceptLegacyChannel = false;
    #[Locked]
    public bool $profileCommitted = false;
    public string $public_name = '';
    public string $country = '';
    public string $language = 'English';
    public string $platform = 'Twitch';
    public string $channel_url = '';
    public string $twitch_url = '';
    public string $kick_url = '';
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
        if ($profile = Auth::user()->creatorProfile) {
            abort_if($profile->trashed(), 403);
            $this->profileCommitted = $profile->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists();
            if ($this->profileCommitted) $this->step = 5;
            $this->acceptLegacyChannel = ! $profile->twitch_url && ! $profile->kick_url;
            foreach (array_keys($this->rules()) as $field) {
                if (! str_contains($field, '*') && $profile->{$field} !== null) {
                    $this->{$field} = is_array($profile->{$field}) ? $profile->{$field} : (string) $profile->{$field};
                }
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
            'public_name' => ['required', 'string', 'min:2', 'max:120'],
            'country' => ['required', 'string', 'max:80'],
            'language' => ['required', 'string', 'max:80'],
            'platform' => ['required', 'in:Twitch,YouTube,Kick,TikTok'],
            'channel_url' => array_merge(['required', 'url:http,https', 'max:255'], in_array($this->platform, ['Twitch', 'Kick'], true) ? [new StreamingChannelUrl($this->platform)] : []),
            'twitch_url' => ['nullable', 'string', 'max:255', new StreamingChannelUrl('Twitch')],
            'kick_url' => ['nullable', 'string', 'max:255', new StreamingChannelUrl('Kick')],
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
            1 => ['public_name', 'country', 'language', 'bio'],
            2 => ['platform', 'channel_url', 'twitch_url', 'kick_url', 'vertical'],
            3 => ['followers', 'average_viewers', 'audience_geos', 'audience_geos.*'],
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
        $this->syncPrimaryChannel();
        $data = $this->validate();
        $data['twitch_url'] = StreamingChannelUrl::canonicalize($data['twitch_url'], 'Twitch');
        $data['kick_url'] = StreamingChannelUrl::canonicalize($data['kick_url'], 'Kick');
        DB::transaction(function () use ($data) {
            $profile = CreatorProfile::withTrashed()->where('user_id', Auth::id())->lockForUpdate()->first();
            abort_if($profile?->trashed(), 403);
            if ($profile?->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists()) {
                throw ValidationException::withMessages(['profile' => 'Your profile is committed to a contract or live campaign. Ask All In Streams before changing the agreed creator identity or channels.']);
            }
            if ($profile) $profile->update($data + ['status' => 'pending']);
            else CreatorProfile::create($data + ['user_id' => Auth::id(), 'status' => 'pending']);
        });
        session()->flash('status', 'Your creator profile is submitted for review. All In Streams will contact you with suitable opportunities.');
        return $this->redirectRoute('creator.opportunities', navigate: false);
    }

    private function syncPrimaryChannel(): void
    {
        if (! in_array($this->platform, ['Twitch', 'Kick'], true)) {
            return;
        }

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

    public function render()
    {
        return view('livewire.creator-application')->layout('layouts.portal', ['title' => 'Creator application']);
    }
}
