<?php

namespace App\Filament\Resources\PaymentMilestoneResource\Pages;

use App\Filament\Resources\PaymentMilestoneResource;
use App\Models\Invitation;
use App\Services\ProviderDelivery;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentMilestoneResource::class;
    protected static ?string $title = 'Provider payments';

    public function getSubheading(): ?string { return 'Manual ledger for selected providers. Add authorized fixed fees or validated CPA amounts, approve them, then record payments made externally.'; }

    protected function getHeaderActions(): array
    {
        return [Action::make('addMilestone')->label('Add payment milestone')->icon('heroicon-o-plus')
            ->schema([
                Select::make('invitation_id')->label('Selected provider')->required()->searchable()->options(fn () => Invitation::query()
                    ->where('selection_status', 'selected')->where('status', 'interested')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live', 'completed']))
                    ->with(['brief', 'creatorProfile', 'agency'])->get()->mapWithKeys(fn (Invitation $invitation) => [$invitation->id => $invitation->brief->code.' · '.$invitation->recipient_name.' · '.$invitation->currency])->all()),
                TextInput::make('label')->required()->maxLength(200)->helperText('Visible to the provider. Use a neutral label, such as 50% completion fee.'),
                Select::make('kind')->options(['fixed_fee' => 'Fixed fee', 'validated_cpa' => 'Validated CPA', 'other' => 'Other'])->required()->default('fixed_fee')->helperText('Use validated CPA only after checking conversion evidence. Self-reported conversions do not create payments.'),
                TextInput::make('amount')->numeric()->minValue(0.01)->maxValue(999999999.99)->required()->helperText('Uses the accepted offer currency. A new milestone starts pending; it does not transfer money.'),
            ])->action(fn (array $data) => app(ProviderDelivery::class)->createMilestone(Invitation::findOrFail($data['invitation_id']), auth()->user(), $data))];
    }
}
