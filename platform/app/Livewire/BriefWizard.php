<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\Brief;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BriefWizard extends Component
{
    #[Locked]
    public int $step = 1;
    public string $title = '';
    public string $brand = '';
    public string $vertical = 'Gaming';
    public array $geos = [];
    public string $language = 'English';
    public string $platform = 'Twitch';
    public string $budget = '';
    public string $currency = 'EUR';
    public string $commercial_model = 'Fixed + CPA';
    public string $deliverables = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $kpis = '';
    public string $notes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === 'business', 403);
        $this->brand = Auth::user()->company ?? '';
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'brand' => ['required', 'string', 'max:180'],
            'vertical' => ['required', 'in:Gaming,Esports,iGaming'],
            'geos' => ['required', 'array', 'min:1', 'max:30'],
            'geos.*' => ['required', 'string', 'max:80', 'distinct'],
            'language' => ['required', 'string', 'max:80'],
            'platform' => ['required', 'in:Twitch,YouTube,Kick,TikTok,Multiple'],
            'budget' => ['required', 'numeric', 'min:100', 'max:999999999'],
            'currency' => ['required', 'in:EUR,USD,GBP'],
            'commercial_model' => ['required', 'in:Fixed,Fixed + CPA,CPA,Revenue share,Open'],
            'deliverables' => ['required', 'string', 'min:5', 'max:5000'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'kpis' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function next(): void
    {
        $keys = match ($this->step) {
            1 => ['title', 'brand', 'vertical'],
            2 => ['geos', 'geos.*', 'language', 'platform'],
            3 => ['budget', 'currency', 'commercial_model', 'deliverables', 'start_date', 'end_date', 'kpis', 'notes'],
            default => [],
        };
        $this->validate(array_intersect_key($this->rules(), array_flip($keys)));
        $this->step = min(4, $this->step + 1);
        $this->resetValidation();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->resetValidation();
    }

    public function submit()
    {
        abort_unless(Auth::user()?->role === 'business', 403);
        $data = $this->validate();
        $brief = DB::transaction(function () use ($data) {
            $brief = Brief::create($data + ['user_id' => Auth::id(), 'status' => 'submitted', 'payment_ready' => false]);
            Activity::create(['brief_id' => $brief->id, 'user_id' => Auth::id(), 'title' => 'We received your brief', 'body' => 'An All In Streams account manager will review your brief and coordinate the next steps. Your campaign is awaiting review.', 'audience' => 'business']);
            return $brief;
        });
        session()->flash('status', 'Your brief has been submitted. Your account manager will review it before creator matching begins.');
        return $this->redirectRoute('campaigns.show', ['brief' => $brief->id], navigate: false);
    }

    public function render()
    {
        return view('livewire.brief-wizard')->layout('layouts.portal', ['title' => 'New campaign brief']);
    }
}
