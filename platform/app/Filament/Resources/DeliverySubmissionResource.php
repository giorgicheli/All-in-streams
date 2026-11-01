<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliverySubmissionResource\Pages\ManageDeliveries;
use App\Models\DeliverySubmission;
use App\Services\ProviderDelivery;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DeliverySubmissionResource extends ManagedResource
{
    protected static ?string $model = DeliverySubmission::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Delivery review';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Provider evidence')->description('Provider-reported figures require independent validation. Approval does not copy them into campaign reporting or create a payment.')->schema([
                TextEntry::make('invitation.brief.title')->label('Private campaign'),
                TextEntry::make('invitation.recipient_name')->label('Provider'),
                TextEntry::make('title'), TextEntry::make('delivered_on')->date(),
                TextEntry::make('content_url')->label('Content link')->url(fn (DeliverySubmission $record) => $record->content_url)->openUrlInNewTab()->columnSpanFull(),
                TextEntry::make('notes')->columnSpanFull()->placeholder('No provider notes.'),
                TextEntry::make('reported_impressions')->label('Self-reported impressions')->numeric()->placeholder('Not reported'),
                TextEntry::make('reported_clicks')->label('Self-reported clicks')->numeric()->placeholder('Not reported'),
                TextEntry::make('reported_conversions')->label('Self-reported conversions')->numeric()->placeholder('Not reported'),
                TextEntry::make('status')->badge(),
                TextEntry::make('review_notes')->columnSpanFull()->placeholder('No review notes.'),
                TextEntry::make('reviewer.name')->label('Reviewed by')->placeholder('Awaiting review'),
                TextEntry::make('reviewed_at')->dateTime()->placeholder('Awaiting review'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('title')->searchable()->wrap(),
            TextColumn::make('invitation.brief.title')->label('Campaign')->wrap()->searchable(),
            TextColumn::make('invitation.recipient_name')->label('Provider'),
            TextColumn::make('delivered_on')->date()->sortable(),
            TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'approved' => 'success', 'changes_requested' => 'warning', default => 'gray' }),
            TextColumn::make('reviewed_at')->dateTime()->placeholder('Awaiting review'),
        ])->filters([
            SelectFilter::make('status')->options(['submitted' => 'Submitted', 'changes_requested' => 'Changes requested', 'approved' => 'Approved']),
        ])->recordActions([
            ViewAction::make(),
            Action::make('download')->label('Evidence file')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (DeliverySubmission $record) => (bool) $record->file_path)
                ->url(fn (DeliverySubmission $record) => route('provider.deliveries.download', $record))->openUrlInNewTab(),
            Action::make('approve')->label('Approve delivery')->color('success')->icon('heroicon-o-check')
                ->visible(fn (DeliverySubmission $record) => $record->status === 'submitted')->requiresConfirmation()
                ->schema([Textarea::make('review_notes')->label('Note to provider')->maxLength(5000)->helperText('Keep company identity and private business terms out of provider messages.')])
                ->action(fn (DeliverySubmission $record, array $data) => app(ProviderDelivery::class)->review($record, auth()->user(), 'approved', $data['review_notes'] ?? null)),
            Action::make('requestChanges')->label('Request changes / reopen')->color('warning')
                ->visible(fn (DeliverySubmission $record) => $record->status === 'submitted' || ($record->status === 'approved' && $record->invitation->brief->status === 'live'))
                ->schema([Textarea::make('review_notes')->label('Changes needed')->required()->maxLength(5000)->helperText('Visible to this provider. Do not include company details or private commercial terms.')])
                ->action(fn (DeliverySubmission $record, array $data) => app(ProviderDelivery::class)->review($record, auth()->user(), 'changes_requested', $data['review_notes'])),
        ])->emptyStateHeading('No delivery evidence yet')->emptyStateDescription('The selected creator or agency submits evidence after the campaign goes live.');
    }

    public static function getPages(): array { return ['index' => ManageDeliveries::route('/')]; }
}
