<?php

namespace App\Filament\Support;

use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderOfferActions
{
    public static function terms(): array
    {
        return [
            TextInput::make('opportunity_title')->label('Recipient-facing opportunity title')->default('Creator partnership opportunity')->required()->maxLength(180)
                ->helperText('Keep the business name, contacts and total budget private.'),
            TextInput::make('compensation')->label('Total fixed fee offered to this recipient')->numeric()->minValue(0)->maxValue(999999999)->required()
                ->helperText('Campaign currency. This is a private provider quote, not the company’s total budget.'),
            TextInput::make('cpa_rate')->label('Optional CPA unit rate')->numeric()->minValue(0)->maxValue(999999999),
            DatePicker::make('start_date')->label('Recipient schedule starts')->helperText('Optional; otherwise use the approved campaign schedule.'),
            DatePicker::make('end_date')->label('Recipient schedule ends')->afterOrEqual('start_date'),
            Textarea::make('deliverables')->label('Recipient delivery scope')->required()->maxLength(5000)->default('4 live streams, 90 minutes each')
                ->helperText('Use a neutral description. Do not copy private company instructions or brand information.'),
            Textarea::make('requirements')->label('Public requirements for this recipient')->maxLength(3000),
            Textarea::make('notes')->label('Message to the recipient')->maxLength(3000),
        ];
    }

    public static function campaignField(): Select
    {
        return Select::make('brief_id')->label('Approved campaign in matching')
            ->options(fn () => Brief::where('status', 'matching')->latest()->pluck('title', 'id')->all())->required()->searchable();
    }

    public static function recipientFields(): array
    {
        return [
            Select::make('creator_ids')->label('Independent creators')->multiple()->searchable()
                ->options(fn () => CreatorProfile::where('status', 'approved')->pluck('public_name', 'id')->all()),
            Select::make('agency_ids')->label('Talent agencies')->multiple()->searchable()
                ->options(fn () => User::where('role', 'agency')->whereHas('agencyProfile', fn ($q) => $q->where('status', 'approved')->whereJsonContains('services', 'supply'))
                    ->get()->mapWithKeys(fn (User $user) => [$user->id => $user->company ?: $user->name])->all()),
        ];
    }

    public static function send(Brief $brief, array $creatorIds, array $agencyIds, array $terms): void
    {
        self::run(function () use ($brief, $creatorIds, $agencyIds, $terms): void {
            if (!$creatorIds && !$agencyIds) throw ValidationException::withMessages(['creator_ids' => 'Select at least one creator or agency.']);
            DB::transaction(function () use ($brief, $creatorIds, $agencyIds, $terms): void {
                $workflow = app(CampaignWorkflow::class);
                foreach (array_unique($creatorIds) as $id) $workflow->invite($brief, CreatorProfile::findOrFail($id), auth()->user(), $terms);
                foreach (array_unique($agencyIds) as $id) $workflow->inviteAgency($brief, User::findOrFail($id), auth()->user(), $terms);
            });
        }, 'Private offers sent');
    }

    public static function run(\Closure $callback, string $success): void
    {
        try {
            $callback();
        } catch (ValidationException $error) {
            Notification::make()->title('Action could not be completed')->body(collect($error->errors())->flatten()->first())->warning()->persistent()->send();
            throw new Halt;
        }
        Notification::make()->title($success)->success()->send();
    }
}
