<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgencyResource\Pages\ListAgencies;
use App\Filament\Resources\AgencyResource\Pages\ViewAgency;
use App\Filament\Resources\AgencyResource\RelationManagers\TalentsRelationManager;
use App\Models\AgencyProfile;
use App\Models\Invitation;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AgencyResource extends ManagedResource
{
    protected static ?string $model = AgencyProfile::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationLabel = 'Agencies';
    protected static ?string $modelLabel = 'agency';
    protected static ?string $pluralModelLabel = 'agencies';
    protected static string|UnitEnum|null $navigationGroup = 'Partner network';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('user', fn (Builder $query) => $query->where('role', 'agency'))->with('user')->withCount('talents');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Agency account')->schema([
                TextEntry::make('user.company')->label('Agency'), TextEntry::make('code')->label('Agency code'),
                TextEntry::make('user.name')->label('Contact name'), TextEntry::make('user.email')->label('Private contact email')->copyable(),
                TextEntry::make('user.telegram')->label('Private Telegram')->placeholder('Not supplied')->copyable(),
                TextEntry::make('status')->badge(), TextEntry::make('country')->placeholder('Not supplied'),
                TextEntry::make('website')->placeholder('Not supplied'), TextEntry::make('services')->badge(),
                TextEntry::make('languages')->badge(), TextEntry::make('audience_geos')->label('Audience markets')->badge(),
                TextEntry::make('platforms')->badge(), TextEntry::make('bio')->columnSpanFull()->placeholder('No agency overview supplied.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.company')->label('Agency')->searchable()->weight('semibold'),
            TextColumn::make('user.name')->label('Contact')->searchable(),
            TextColumn::make('country')->searchable()->placeholder('Profile incomplete'),
            TextColumn::make('services')->badge(), TextColumn::make('talents_count')->label('Roster talent'),
            TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) { 'approved' => 'success', 'pending' => 'warning', 'declined' => 'danger', default => 'gray' }),
            TextColumn::make('created_at')->date()->label('Registered')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['draft'=>'Draft','pending'=>'In review','approved'=>'Approved','declined'=>'Declined']),
        ])->recordActions([
            ViewAction::make()->url(fn (AgencyProfile $record) => static::getUrl('view', ['record'=>$record])),
            Action::make('approve')->label('Approve agency')->color('success')->icon('heroicon-o-check')->requiresConfirmation()
                ->modalDescription('Approve the agency profile. Roster talent is reviewed separately before nomination.')
                ->visible(fn (AgencyProfile $record) => in_array($record->status, ['pending', 'declined'], true))
                ->action(fn (AgencyProfile $record) => static::review($record, 'approved')),
            Action::make('decline')->label('Request profile changes')->color('warning')->requiresConfirmation()
                ->visible(fn (AgencyProfile $record) => $record->status !== 'declined')
                ->action(fn (AgencyProfile $record) => static::review($record, 'declined')),
        ])->defaultSort('created_at','desc');
    }

    private static function review(AgencyProfile $profile, string $status): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        DB::transaction(function () use ($profile, $status) {
            $profile->user()->lockForUpdate()->firstOrFail();
            $profile = AgencyProfile::whereKey($profile->id)->lockForUpdate()->firstOrFail();
            if ($status !== 'approved' && Invitation::where('agency_user_id', $profile->user_id)->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts','live']))->exists()) {
                Notification::make()->title('Agency has an active campaign')->body('Keep the selected agency approved until contracts or live delivery are resolved.')->warning()->send();
                return;
            }
            if ($status === 'approved' && (! in_array($profile->status, ['pending', 'declined'], true)
                || blank($profile->user?->company) || blank($profile->country) || blank($profile->services)
                || blank($profile->languages) || blank($profile->audience_geos) || blank($profile->platforms))) {
                Notification::make()->title('Agency profile is incomplete')->body('The agency must submit its company, markets, languages and platforms before approval.')->warning()->send();
                return;
            }
            $profile->update(['status'=>$status]);
            Notification::make()->title($status === 'approved' ? 'Agency profile approved' : 'Agency profile requires changes')->success()->send();
        });
    }

    public static function getRelations(): array { return [TalentsRelationManager::class]; }
    public static function getPages(): array { return ['index'=>ListAgencies::route('/'),'view'=>ViewAgency::route('/{record}')]; }
}
