<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BriefResource\Pages\ManageBriefs;
use App\Models\Brief;
use App\Services\CampaignWorkflow;
use App\Filament\Support\ProviderOfferActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class BriefResource extends ManagedResource
{
    protected static ?string $model = Brief::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationLabel = 'Brief review queue';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Brief::whereIn('status', ['submitted', 'review'])->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment readiness')->description('Confirm payment arrangements before launch. This does not replace the separate signed agreements required for both parties.')->schema([
                Toggle::make('payment_ready')->label('Payment is ready')->helperText('Only staff can set this after confirming the payment arrangements.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign overview')->schema([
                TextEntry::make('title')->label('Campaign'), TextEntry::make('brand'),
                TextEntry::make('user.email')->label('Business contact'), TextEntry::make('vertical'),
                TextEntry::make('geos')->badge()->label('Audience GEOs'), TextEntry::make('language'),
                TextEntry::make('platform'), TextEntry::make('commercial_model')->label('Commercial model'),
                TextEntry::make('provider_type')->label('Requested provider')->formatStateUsing(fn (string $state) => match ($state) { 'creator' => 'Individual creator', 'agency' => 'Agency', default => 'Creator or agency' }),
                TextEntry::make('budget')->money(fn (Brief $record) => $record->currency),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title()),
            ])->columns(2),
            Section::make('Delivery requirements')->schema([
                TextEntry::make('deliverables'), TextEntry::make('kpis')->label('KPI requirements'),
                TextEntry::make('start_date')->date(), TextEntry::make('end_date')->date(),
                TextEntry::make('notes')->columnSpanFull()->placeholder('No additional notes.'),
                TextEntry::make('payment_ready')->formatStateUsing(fn (bool $state) => $state ? 'Confirmed' : 'Not yet confirmed'),
            ])->columns(2),
        ]);
    }

    public static function statuses(): array
    {
        return ['submitted' => 'Submitted', 'review' => 'Under review', 'matching' => 'Matching', 'proposal' => 'Proposal', 'contracts' => 'Contracts', 'live' => 'Live', 'completed' => 'Completed', 'changes_requested' => 'Changes requested', 'declined' => 'Declined'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('Brief')->formatStateUsing(fn ($state) => 'BRF-'.str_pad($state, 4, '0', STR_PAD_LEFT))->searchable()->sortable(),
                TextColumn::make('title')->searchable()->weight('semibold')->description(fn (Brief $record) => $record->brand)->wrap(),
                TextColumn::make('vertical')->badge()->color('gray'),
                TextColumn::make('budget')->money(fn (Brief $record) => $record->currency)->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => self::statuses()[$state] ?? $state)->color(fn (string $state) => match ($state) {'submitted', 'review' => 'warning', 'live', 'completed' => 'success', 'declined' => 'danger', default => 'primary'}),
                TextColumn::make('created_at')->since()->label('Submitted')->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(self::statuses())])
            ->recordActions([
                ViewAction::make()->label('Review'),
                Action::make('downloadBrief')->label('Private brief PDF')->icon('heroicon-o-arrow-down-tray')->visible(fn (Brief $record) => filled($record->attachment_path))->url(fn (Brief $record) => route('briefs.attachment', $record))->openUrlInNewTab(),
                Action::make('sendOffers')->label('Send provider offers')->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Brief $record) => $record->status === 'matching')
                    ->modalDescription('Send separate private quotes to several independent creators or agencies. Recipients never receive the company name, contact details, total budget or other recipients’ quotes.')
                    ->schema([...ProviderOfferActions::recipientFields(), ...ProviderOfferActions::terms()])
                    ->action(fn (Brief $record, array $data) => ProviderOfferActions::send($record, $data['creator_ids'] ?? [], $data['agency_ids'] ?? [], $data)),
                Action::make('approve')->label('Approve for matching')->icon('heroicon-o-check')->color('primary')
                    ->visible(fn (Brief $record) => in_array($record->status, ['submitted', 'review', 'changes_requested']))
                    ->requiresConfirmation()->modalDescription('This approves the brief for creator matching. It does not activate the campaign.')
                    ->action(fn (Brief $record) => self::transition($record, 'matching')),
                ActionGroup::make([
                    EditAction::make()->label('Payment readiness')->icon('heroicon-o-banknotes'),
                    Action::make('advance')->label(fn (Brief $record) => match ($record->status) {'matching' => 'Prepare client proposal', 'proposal' => 'Move to contracts', 'contracts' => 'Launch campaign', 'live' => 'Complete campaign', default => 'Advance campaign'})
                        ->icon('heroicon-o-arrow-right')->visible(fn (Brief $record) => in_array($record->status, ['matching', 'proposal', 'contracts', 'live']))
                        ->requiresConfirmation()->modalDescription('Contracts require one admin-approved provider. Launch also requires separate signed agreements and payment readiness; completion requires approved delivery evidence.')
                        ->action(fn (Brief $record) => self::transition($record, match ($record->status) {'matching' => 'proposal', 'proposal' => 'contracts', 'contracts' => 'live', 'live' => 'completed'})),
                    Action::make('requestChanges')->label('Request changes')->icon('heroicon-o-pencil-square')->color('warning')
                        ->visible(fn (Brief $record) => in_array($record->status, ['submitted', 'review']))
                        ->schema([Textarea::make('message')->label('What needs to change?')->required()->maxLength(4000)])
                        ->action(function (Brief $record, array $data) {
                            self::transition($record, 'changes_requested');
                            $record->activities()->create(['user_id' => auth()->id(), 'title' => 'Changes requested', 'body' => $data['message'], 'audience' => 'business']);
                        }),
                    Action::make('decline')->label('Decline brief')->color('danger')->icon('heroicon-o-x-mark')->requiresConfirmation()
                        ->visible(fn (Brief $record) => in_array($record->status, ['submitted', 'review', 'changes_requested']))
                        ->action(fn (Brief $record) => self::transition($record, 'declined')),
                    Action::make('update')->label('Post a campaign update')->icon('heroicon-o-chat-bubble-left-right')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('title')->required()->maxLength(150),
                            Textarea::make('body')->required()->maxLength(4000),
                            \Filament\Forms\Components\Select::make('audience')->options(['business' => 'Business', 'creator' => 'Creators', 'admin' => 'Internal staff'])->required()->default('business'),
                        ])
                        ->action(function (Brief $record, array $data) {
                            abort_unless(auth()->user()?->role === 'admin', 403);
                            $record->activities()->create([...$data, 'user_id' => auth()->id()]);
                            Notification::make()->title('Update posted')->success()->send();
                        }),
                ])->label('Manage'),
            ])
            ->emptyStateHeading('No briefs yet')->emptyStateDescription('Business campaign submissions will arrive here for staff review.');
    }

    public static function transition(Brief $brief, string $status): void
    {
        try {
            app(CampaignWorkflow::class)->transition($brief, $status, auth()->user());
        } catch (ValidationException $exception) {
            Notification::make()->title('Campaign cannot advance yet')->body(collect($exception->errors())->flatten()->first())->warning()->persistent()->send();
            throw new Halt;
        }
        Notification::make()->title('Campaign updated')->body(self::statuses()[$status])->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => ManageBriefs::route('/')];
    }
}
