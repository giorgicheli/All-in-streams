<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentMilestoneResource\Pages\ManagePayments;
use App\Models\PaymentMilestone;
use App\Services\ProviderDelivery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentMilestoneResource extends ManagedResource
{
    protected static ?string $model = PaymentMilestone::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Provider payments';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('label')->searchable()->wrap(),
            TextColumn::make('invitation.brief.title')->label('Campaign')->wrap()->searchable(),
            TextColumn::make('invitation.recipient_name')->label('Provider'),
            TextColumn::make('kind')->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title()),
            TextColumn::make('amount')->money(fn (PaymentMilestone $record) => $record->currency),
            TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'paid' => 'success', 'approved' => 'primary', default => 'warning' }),
            TextColumn::make('approver.name')->label('Approved by')->placeholder('Not approved'),
            TextColumn::make('paid_at')->dateTime()->placeholder('No payment recorded'),
            TextColumn::make('reference')->placeholder('—')->toggleable(),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid']),
            SelectFilter::make('kind')->options(['fixed_fee' => 'Fixed fee', 'validated_cpa' => 'Validated CPA', 'other' => 'Other']),
        ])->recordActions([
            Action::make('approve')->label('Approve milestone')->color('success')->requiresConfirmation()
                ->visible(fn (PaymentMilestone $record) => $record->status === 'pending')
                ->modalDescription('Confirm this provider amount is authorized. CPA milestones must already have been validated by your team. This does not transfer money.')
                ->action(fn (PaymentMilestone $record) => app(ProviderDelivery::class)->approveMilestone($record, auth()->user())),
            Action::make('markPaid')->label('Record external payment')->icon('heroicon-o-check')->color('success')
                ->visible(fn (PaymentMilestone $record) => $record->status === 'approved')
                ->schema([
                    DateTimePicker::make('paid_at')->label('Actual payment date')->required()->maxDate(now())->default(now()),
                    TextInput::make('reference')->label('Provider payment reference')->maxLength(255)->helperText('Visible to the provider. Use a transfer reference without company or private business details.'),
                ])->modalDescription('Record a payment already made outside this portal. This action does not transfer money.')
                ->action(fn (PaymentMilestone $record, array $data) => app(ProviderDelivery::class)->markPaid($record, auth()->user(), $data)),
        ])->emptyStateHeading('No provider payment milestones')->emptyStateDescription('Add manual milestones for the selected provider after contracts begin. The portal does not process payments.');
    }

    public static function getPages(): array { return ['index' => ManagePayments::route('/')]; }
}
