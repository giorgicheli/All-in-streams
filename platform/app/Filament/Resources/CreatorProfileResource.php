<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorProfileResource\Pages\ManageCreators;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Rules\StreamingChannelUrl;
use App\Rules\SocialProfileUrl;
use App\Services\CampaignWorkflow;
use App\Services\CreatorProofVideo;
use App\Filament\Support\ProviderOfferActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CreatorProfileResource extends ManagedResource
{
    protected static ?string $model = CreatorProfile::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Streamers';
    protected static ?string $modelLabel = 'streamer';
    protected static ?string $pluralModelLabel = 'streamers';
    protected static string|UnitEnum|null $navigationGroup = 'Network';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'public_name';

    public static function creatorType(): string { return 'streamer'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->where('creator_type', static::creatorType());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('creator_type')->default(static::creatorType())->dehydrateStateUsing(fn () => static::creatorType()),
            Section::make('Creator identity')->description('Internal staff view. Account identities, channel links and contact details are private to administrators.')->schema([
                Select::make('user_id')->relationship('user', 'email', modifyQueryUsing: fn (Builder $query) => $query->where('role', 'creator'))->label('Creator account')->required()->searchable()->preload()->unique(ignoreRecord: true),
                TextInput::make('public_name')->required()->maxLength(120),
                TextInput::make('country')->required()->maxLength(80),
                TextInput::make('language')->required()->maxLength(80),
                Select::make('platform')->options(static::platformOptions())->required(),
                TextInput::make('channel_url')->url()->required()->maxLength(255),
                TextInput::make('twitch_url')->label('Twitch channel')->url()->maxLength(255)->rules([new StreamingChannelUrl('Twitch')])->helperText('Private channel link; visible to administrators only.')->visible(static::creatorType() === 'streamer'),
                TextInput::make('kick_url')->label('Kick channel')->url()->maxLength(255)->rules([new StreamingChannelUrl('Kick')])->visible(static::creatorType() === 'streamer'),
                ...array_map(fn (string $field, string $platform) => TextInput::make($field)->label($platform.' profile')->url()->maxLength(255)->rules([new SocialProfileUrl($platform)])->visible(static::creatorType() === 'influencer'), array_keys(SocialProfileUrl::FIELDS), array_values(SocialProfileUrl::FIELDS)),
            ])->columns(2),
            Section::make('Audience and partnerships')->schema([
                TextInput::make('followers')->numeric()->minValue(0)->required(),
                TextInput::make('average_viewers')->numeric()->minValue(0)->required(),
                TagsInput::make('audience_geos')->label('Audience GEOs')->placeholder('Add a country')->required(),
                Select::make('vertical')->options(['Gaming' => 'Gaming', 'Esports' => 'Esports', 'iGaming' => 'iGaming'])->required(),
                TextInput::make('rate')->numeric()->minValue(0)->prefix('€')->required()->helperText('Internal creator rate. Never included in a business proposal.'),
                Select::make('commercial_model')->options(['Fixed' => 'Fixed', 'Fixed + CPA' => 'Fixed + CPA', 'CPA' => 'CPA', 'Revenue share' => 'Revenue share', 'Open' => 'Open to discussion'])->required(),
                Select::make('availability')->options(['Open' => 'Open', 'Opening soon' => 'Opening soon', 'Unavailable' => 'Unavailable'])->required(),
                Select::make('status')->options(['draft' => 'Application draft', 'pending' => 'Pending review', 'approved' => 'Approved', 'declined' => 'Declined'])->required(),
                Textarea::make('bio')->maxLength(3000)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_name')->label('Creator')->searchable()->weight('semibold')->description(fn (CreatorProfile $record) => '#'.str_pad($record->id, 3, '0', STR_PAD_LEFT)),
                TextColumn::make('country')->searchable(), TextColumn::make('language')->searchable(),
                TextColumn::make('platform')->badge()->color('info'),
                TextColumn::make('audience_geos')->label('Audience countries')->badge()->separator(','),
                TextColumn::make('user.telegram')->label('Telegram')->copyable()->toggleable(),
                TextColumn::make('followers')->numeric()->sortable(),
                TextColumn::make('average_viewers')->label('Avg. viewers')->numeric()->sortable(),
                TextColumn::make('rate')->money('EUR')->sortable()->label('Internal rate'),
                TextColumn::make('availability')->badge()->color(fn (string $state) => $state === 'Open' ? 'success' : 'gray'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'approved' ? 'success' : 'warning'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('country')->options(fn () => CreatorProfile::query()->distinct()->orderBy('country')->pluck('country', 'country')->all())->searchable(),
                SelectFilter::make('language')->options(fn () => CreatorProfile::query()->distinct()->orderBy('language')->pluck('language', 'language')->all())->searchable(),
                SelectFilter::make('audience_country')->label('Audience country')->options(fn () => CreatorProfile::query()->where('creator_type', static::creatorType())->pluck('audience_geos')->flatten()->filter()->unique()->sort()->mapWithKeys(fn ($country) => [$country => $country])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, string $country) => $q->whereJsonContains('audience_geos', $country)))->searchable(),
                Filter::make('budget')->schema([TextInput::make('maximum_rate')->label('Maximum creator rate (EUR)')->numeric()->minValue(0)->maxValue(999999999.99)])
                    ->query(fn (Builder $query, array $data) => $query->when(isset($data['maximum_rate']) && $data['maximum_rate'] !== '' && is_numeric($data['maximum_rate']) && (float) $data['maximum_rate'] >= 0, fn (Builder $q) => $q->where('rate', '<=', (float) $data['maximum_rate'])))
                    ->indicateUsing(fn (array $data) => isset($data['maximum_rate']) && $data['maximum_rate'] !== '' ? 'Maximum rate: EUR '.number_format((float) $data['maximum_rate'], 2) : null),
                SelectFilter::make('platform')->options(static::platformOptions()),
                SelectFilter::make('status')->options(['draft' => 'Application draft', 'pending' => 'Pending review', 'approved' => 'Approved', 'declined' => 'Declined']),
                SelectFilter::make('availability')->options(['Open' => 'Open', 'Opening soon' => 'Opening soon', 'Unavailable' => 'Unavailable']),
            ])
            ->recordActions([
                Action::make('proofVideo')->label('Verification video')->icon('heroicon-o-play-circle')->color('gray')
                    ->visible(fn (CreatorProfile $record) => filled($record->verification_video_path))
                    ->url(fn (CreatorProfile $record) => route('admin.creator-video', $record))->openUrlInNewTab(),
                Action::make('deleteProofVideo')->label('Delete verification video')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->visible(fn (CreatorProfile $record) => filled($record->verification_video_path))
                    ->modalDescription('Permanently deletes this private verification video. The creator will need to upload a replacement before submitting their profile again.')
                    ->action(function (CreatorProfile $record) { app(CreatorProofVideo::class)->delete($record); Notification::make()->title('Verification video deleted')->success()->send(); }),
                EditAction::make()->label('Profile')->visible(fn (?CreatorProfile $record) => $record && !$record->trashed() && !self::committed($record))
                    ->using(function (CreatorProfile $record, array $data): CreatorProfile {
                        return DB::transaction(function () use ($record, $data) {
                            $record = CreatorProfile::withTrashed()->lockForUpdate()->findOrFail($record->id);
                            if ($record->trashed() || self::committed($record)) throw ValidationException::withMessages(['status' => 'Resolve this creator\'s contract or live campaign before changing the profile.']);
                            $record->update($data);
                            return $record;
                        });
                    }),
                ViewAction::make()->label('Profile')->visible(fn (?CreatorProfile $record) => $record && self::committed($record)),
                Action::make('invite')->label('Send invitation')->icon('heroicon-o-paper-airplane')->color('primary')
                    ->visible(fn (CreatorProfile $record) => $record->status === 'approved' && !$record->trashed())
                    ->modalDescription('The creator receives your invitation and compensation terms. Their identity and contacts remain private to administrators.')
                    ->schema([ProviderOfferActions::campaignField(), ...ProviderOfferActions::terms()])
                    ->action(function (CreatorProfile $record, array $data) {
                        try {
                            app(CampaignWorkflow::class)->invite(Brief::findOrFail($data['brief_id']), $record, auth()->user(), $data);
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Invitation could not be sent')->body(collect($exception->errors())->flatten()->first())->warning()->send();
                            throw new Halt;
                        }
                        Notification::make()->title('Invitation sent')->body('The opportunity is now available in the creator portal.')->success()->send();
                    }),
                DeleteAction::make()->label('Delete creator')->requiresConfirmation()
                    ->modalDescription('Removes the creator from matching and retains past campaign history. A provider selected for contracts or a live campaign cannot be deleted until that work is resolved.')
                    ->using(function (CreatorProfile $record): bool {
                        ProviderOfferActions::run(fn () => app(CampaignWorkflow::class)->deleteCreator($record, auth()->user()), 'Creator deleted');
                        return true;
                    }),
                RestoreAction::make()->label('Restore creator')->using(fn (CreatorProfile $record): bool => DB::transaction(function () use ($record) {
                    $record = CreatorProfile::withTrashed()->lockForUpdate()->findOrFail($record->id);
                    $record->status = 'pending';
                    return (bool)$record->restore();
                })),
            ])
            ->toolbarActions([
                BulkAction::make('inviteMany')->label('Send invitations to selected')->icon('heroicon-o-paper-airplane')
                    ->schema([ProviderOfferActions::campaignField(), ...ProviderOfferActions::terms()])
                    ->action(fn (Collection $records, array $data) => ProviderOfferActions::send(Brief::findOrFail($data['brief_id']), $records->modelKeys(), [], $data))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Build your creator network')->emptyStateDescription('Creator applications appear here for review and campaign matching.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCreators::route('/')];
    }

    protected static function platformOptions(): array
    {
        return static::creatorType() === 'influencer'
            ? ['Instagram' => 'Instagram', 'Facebook' => 'Facebook', 'TikTok' => 'TikTok', 'YouTube' => 'YouTube']
            : ['Twitch' => 'Twitch', 'Kick' => 'Kick'];
    }

    private static function committed(CreatorProfile $record): bool
    {
        return $record->invitations()->where('selection_status', 'selected')->whereHas('brief', fn ($query) => $query->whereIn('status', ['contracts', 'live']))->exists();
    }
}
