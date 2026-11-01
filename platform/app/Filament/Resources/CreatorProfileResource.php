<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorProfileResource\Pages\ManageCreators;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Services\CampaignWorkflow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CreatorProfileResource extends ManagedResource
{
    protected static ?string $model = CreatorProfile::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Creator matching';
    protected static string|UnitEnum|null $navigationGroup = 'Network';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'public_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Creator identity')->description('Internal staff view. Public names and contact details are released to a business only after an authorized introduction.')->schema([
                Select::make('user_id')->relationship('user', 'email', modifyQueryUsing: fn (Builder $query) => $query->where('role', 'creator'))->label('Creator account')->required()->searchable()->preload()->unique(ignoreRecord: true),
                TextInput::make('public_name')->required()->maxLength(120),
                TextInput::make('country')->required()->maxLength(80),
                TextInput::make('language')->required()->maxLength(80),
                Select::make('platform')->options(['Twitch' => 'Twitch', 'Kick' => 'Kick', 'YouTube' => 'YouTube', 'TikTok' => 'TikTok'])->required(),
                TextInput::make('channel_url')->url()->required()->maxLength(500),
            ])->columns(2),
            Section::make('Audience and partnerships')->schema([
                TextInput::make('followers')->numeric()->minValue(0)->required(),
                TextInput::make('average_viewers')->numeric()->minValue(0)->required(),
                TagsInput::make('audience_geos')->label('Audience GEOs')->placeholder('Add a country')->required(),
                Select::make('vertical')->options(['Gaming' => 'Gaming', 'Esports' => 'Esports', 'iGaming' => 'iGaming'])->required(),
                TextInput::make('rate')->numeric()->minValue(0)->prefix('€')->required()->helperText('Internal creator rate. Never included in a business proposal.'),
                Select::make('commercial_model')->options(['Fixed' => 'Fixed', 'Fixed + CPA' => 'Fixed + CPA', 'CPA' => 'CPA', 'Revenue share' => 'Revenue share', 'Open' => 'Open to discussion'])->required(),
                Select::make('availability')->options(['Open' => 'Open', 'Opening soon' => 'Opening soon', 'Unavailable' => 'Unavailable'])->required(),
                Select::make('status')->options(['pending' => 'Pending review', 'approved' => 'Approved', 'declined' => 'Declined'])->required(),
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
                TextColumn::make('followers')->numeric()->sortable(),
                TextColumn::make('average_viewers')->label('Avg. viewers')->numeric()->sortable(),
                TextColumn::make('rate')->money('EUR')->sortable()->label('Internal rate'),
                TextColumn::make('availability')->badge()->color(fn (string $state) => $state === 'Open' ? 'success' : 'gray'),
                TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'approved' ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('country')->options(fn () => CreatorProfile::query()->distinct()->orderBy('country')->pluck('country', 'country')->all())->searchable(),
                SelectFilter::make('language')->options(fn () => CreatorProfile::query()->distinct()->orderBy('language')->pluck('language', 'language')->all())->searchable(),
                SelectFilter::make('platform')->options(['Twitch' => 'Twitch', 'Kick' => 'Kick', 'YouTube' => 'YouTube', 'TikTok' => 'TikTok']),
                SelectFilter::make('status')->options(['pending' => 'Pending review', 'approved' => 'Approved', 'declined' => 'Declined']),
                SelectFilter::make('availability')->options(['Open' => 'Open', 'Opening soon' => 'Opening soon', 'Unavailable' => 'Unavailable']),
            ])
            ->recordActions([
                EditAction::make()->label('Profile'),
                Action::make('invite')->label('Send invitation')->icon('heroicon-o-paper-airplane')->color('primary')
                    ->visible(fn (CreatorProfile $record) => $record->status === 'approved')
                    ->modalDescription('The creator receives your invitation and compensation terms. Their identity stays private until you authorize an introduction.')
                    ->schema([
                        Select::make('brief_id')->label('Campaign in matching')->options(fn () => Brief::where('status', 'matching')->pluck('title', 'id')->all())->searchable()->required(),
                        TextInput::make('compensation')->label('Creator compensation')->numeric()->minValue(0)->required()->default(fn (CreatorProfile $record) => $record->rate),
                        Textarea::make('deliverables')->required()->maxLength(1000)->default('4 live streams'),
                        Textarea::make('notes')->label('Requirements for the creator')->maxLength(3000),
                    ])
                    ->action(function (CreatorProfile $record, array $data) {
                        try {
                            app(CampaignWorkflow::class)->invite(Brief::findOrFail($data['brief_id']), $record, auth()->user(), $data);
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Invitation could not be sent')->body(collect($exception->errors())->flatten()->first())->warning()->send();
                            throw new Halt;
                        }
                        Notification::make()->title('Invitation sent')->body('The opportunity is now available in the creator portal.')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Build your creator network')->emptyStateDescription('Creator applications appear here for review and campaign matching.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCreators::route('/')];
    }
}
