<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitationResource\Pages\ManageInvitations;
use App\Models\Invitation;
use App\Services\CampaignWorkflow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InvitationResource extends ManagedResource
{
    protected static ?string $model = Invitation::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'Shortlists & invitations';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invitation details')->schema([
                TextEntry::make('brief.title')->label('Campaign'), TextEntry::make('creatorProfile.public_name')->label('Creator'),
                TextEntry::make('status')->badge(),
                TextEntry::make('compensation')->money(fn (Invitation $record) => $record->brief->currency)->label('Private creator compensation'),
                TextEntry::make('deliverables')->columnSpanFull(),
                TextEntry::make('notes')->label('Creator requirements')->columnSpanFull()->placeholder('No additional requirements.'),
                TextEntry::make('response_notes')->columnSpanFull()->placeholder('No response notes.'),
                TextEntry::make('introduced_at')->dateTime()->placeholder('Identity protected; no introduction authorized.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('brief.title')->label('Campaign')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('creatorProfile.public_name')->label('Creator')->searchable()->description(fn (Invitation $record) => $record->creatorProfile->country.' · '.$record->creatorProfile->platform),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())->color(fn (string $state) => match ($state) {'interested' => 'success', 'declined' => 'danger', 'changes_requested' => 'warning', default => 'gray'}),
                TextColumn::make('compensation')->label('Private compensation')->money(fn (Invitation $record) => $record->brief->currency),
                TextColumn::make('introduced_at')->label('Introduction')->dateTime('M j, Y')->placeholder('Identity protected'),
                TextColumn::make('created_at')->since()->label('Invited'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Awaiting response', 'interested' => 'Interested', 'changes_requested' => 'Changes requested', 'declined' => 'Declined']),
                SelectFilter::make('brief')->relationship('brief', 'title')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('View response'),
                Action::make('introduce')->label('Authorize introduction')->icon('heroicon-o-lock-open')->color('primary')
                    ->visible(fn (Invitation $record) => $record->status === 'interested' && !$record->introduced_at)
                    ->requiresConfirmation()->modalDescription('The business will be able to see this creator’s name and channel. Private creator compensation stays hidden.')
                    ->action(function (Invitation $record) {
                        app(CampaignWorkflow::class)->introduce($record, auth()->user());
                        Notification::make()->title('Introduction authorized')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No invitations yet')->emptyStateDescription('Find a creator in Creator matching and invite them to an approved brief.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvitations::route('/')];
    }
}
