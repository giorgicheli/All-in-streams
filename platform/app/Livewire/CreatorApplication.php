<?php

namespace App\Livewire;

use App\Models\CreatorProfile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CreatorApplication extends Component
{
    #[Locked]
    public int $step = 1;
    public string $public_name = '';
    public string $country = '';
    public string $language = 'English';
    public string $platform = 'Twitch';
    public string $channel_url = '';
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
            foreach (array_keys($this->rules()) as $field) {
                if (! str_contains($field, '*') && $profile->{$field} !== null) {
                    $this->{$field} = is_array($profile->{$field}) ? $profile->{$field} : (string) $profile->{$field};
                }
            }
        } else {
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
            'channel_url' => ['required', 'url:http,https', 'max:255'],
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
        $keys = match ($this->step) {
            1 => ['public_name', 'country', 'language', 'bio'],
            2 => ['platform', 'channel_url', 'vertical'],
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
        $data = $this->validate();
        CreatorProfile::updateOrCreate(['user_id' => Auth::id()], $data + ['status' => 'pending']);
        session()->flash('status', 'Your creator profile is submitted for review. All In Streams will contact you with suitable opportunities.');
        return $this->redirectRoute('creator.opportunities', navigate: false);
    }

    public function render()
    {
        return view('livewire.creator-application')->layout('layouts.portal', ['title' => 'Creator application']);
    }
}
