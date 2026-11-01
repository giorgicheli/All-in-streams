<?php

namespace App\Livewire;

use App\Models\Activity;
use App\Models\Brief;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class BriefWizard extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $step = 1;
    #[Locked]
    public ?int $briefId = null;
    public string $title = '';
    public string $brand = '';
    public string $vertical = 'Gaming';
    public array $geos = [];
    public string $language = 'English';
    public string $platform = 'Twitch';
    public string $provider_type = 'either';
    public $attachment = null;
    public string $budget = '';
    public string $currency = 'EUR';
    public string $commercial_model = 'Fixed + CPA';
    public string $deliverables = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $kpis = '';
    public string $notes = '';

    public function mount(?Brief $brief = null): void
    {
        $this->authorizeClient();
        $this->brand = Auth::user()->company ?? '';
        if ($brief?->exists) {
            abort_unless($brief->user_id === Auth::id() && $brief->status === 'changes_requested', 403);
            $this->briefId = $brief->id;
            foreach (array_keys($this->rules()) as $field) {
                if (str_contains($field, '*') || $field === 'attachment') continue;
                $value = $brief->{$field};
                $this->{$field} = in_array($field, ['start_date', 'end_date'], true) ? ($value?->toDateString() ?? '') : (is_array($value) ? $value : (string)$value);
            }
        }
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
            'provider_type' => ['required', 'in:creator,agency,either'],
            'attachment' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
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
            2 => ['geos', 'geos.*', 'language', 'platform', 'provider_type'],
            3 => ['budget', 'currency', 'commercial_model', 'deliverables', 'start_date', 'end_date', 'kpis', 'notes', 'attachment'],
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
        $this->authorizeClient();
        $data = $this->validate();
        unset($data['attachment']);
        $path = $this->attachment?->store('brief-attachments', 'local');
        try {
        $brief = DB::transaction(function () use ($data, $path) {
            if ($this->briefId) {
                $brief = Brief::lockForUpdate()->findOrFail($this->briefId);
                abort_unless($brief->user_id === Auth::id() && $brief->status === 'changes_requested', 403);
                $brief->update($data + ['status' => 'submitted', 'payment_ready' => false] + ($path ? ['attachment_path' => $path] : []));
            } else {
                $brief = Brief::create($data + ['user_id' => Auth::id(), 'status' => 'submitted', 'payment_ready' => false, 'attachment_path' => $path]);
            }
            Activity::create(['brief_id' => $brief->id, 'user_id' => Auth::id(), 'title' => 'We received your brief', 'body' => 'An All In Streams account manager will review your brief and coordinate the next steps. Your campaign is awaiting review.', 'audience' => 'business']);
            return $brief;
        });
        } catch (\Throwable $exception) {
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        session()->flash('status', 'Your brief has been submitted. Your account manager will review it before creator matching begins.');
        return $this->redirectRoute(Auth::user()->role === 'agency' ? 'agency.campaigns.show' : 'campaigns.show', ['brief' => $brief->id], navigate: false);
    }

    private function authorizeClient(): void
    {
        $actor = Auth::user();
        abort_unless($actor && in_array($actor->role, ['business', 'agency'], true), 403);
        abort_if($actor->role === 'agency' && !in_array('request', $actor->agencyProfile?->services ?? [], true), 403);
    }

    public function render()
    {
        return view('livewire.brief-wizard')->layout('layouts.portal', ['title' => 'New campaign brief']);
    }
}
