<?php

namespace App\Filament\Resources\AgencyResource\RelationManagers;

use App\Models\AgencyTalent;
use App\Rules\StreamingChannelUrl;
use App\Rules\SocialProfileUrl;
use App\Services\AgencyTalentInput;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class TalentsRelationManager extends RelationManager
{
    protected static string $relationship = 'talents';
    protected static ?string $title = 'Agency talent roster';

    public function mount(): void
    {
        $this->authorizeStaff();
        parent::mount();
    }

    public function hydrate(): void { $this->authorizeStaff(); }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->role === 'admin' && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function isReadOnly(): bool { return false; }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('creator_type')->label('Talent type')->options(['streamer'=>'Streamer','influencer'=>'Influencer'])->required()->default('streamer')->live(),
            TextInput::make('public_name')->required()->maxLength(120), TextInput::make('country')->required()->maxLength(80),
            TextInput::make('language')->required()->maxLength(80), Select::make('platform')->options(fn (Get $get) => $get('creator_type') === 'influencer' ? ['Facebook'=>'Facebook','Instagram'=>'Instagram','YouTube'=>'YouTube','TikTok'=>'TikTok'] : ['Twitch'=>'Twitch','Kick'=>'Kick'])->required(),
            TextInput::make('twitch_url')->label('Twitch channel')->requiredWithout('kick_url')->rules([new StreamingChannelUrl('Twitch')])->maxLength(255)->visible(fn (Get $get) => $get('creator_type') !== 'influencer'),
            TextInput::make('kick_url')->label('Kick channel')->requiredWithout('twitch_url')->rules([new StreamingChannelUrl('Kick')])->maxLength(255)->visible(fn (Get $get) => $get('creator_type') !== 'influencer'),
            ...array_map(fn (string $field, string $platform) => TextInput::make($field)->label($platform.' profile')->maxLength(255)->requiredWithoutAll(array_diff(array_keys(SocialProfileUrl::FIELDS), [$field]))->rules([new SocialProfileUrl($platform)])->visible(fn (Get $get) => $get('creator_type') === 'influencer'), array_keys(SocialProfileUrl::FIELDS), array_values(SocialProfileUrl::FIELDS)),
            TextInput::make('followers')->integer()->minValue(0)->maxValue(2000000000)->required(),
            TextInput::make('average_viewers')->integer()->minValue(0)->maxValue(100000000)->required(),
            TagsInput::make('audience_geos')->label('Audience markets')->required(),
            TextInput::make('rate')->numeric()->minValue(0)->maxValue(999999999)->required()->prefix('EUR'),
            Select::make('availability')->options(['Open'=>'Open','Opening soon'=>'Opening soon','Unavailable'=>'Unavailable'])->required()->default('Open'),
            Select::make('status')->options(['pending'=>'In review','approved'=>'Approved','declined'=>'Requires changes'])->required()->default('pending'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))->columns([
            TextColumn::make('public_name')->searchable()->weight('semibold'), TextColumn::make('country'), TextColumn::make('platform')->badge(),
            TextColumn::make('creator_type')->label('Type')->badge(),
            TextColumn::make('followers')->numeric(), TextColumn::make('average_viewers')->numeric(), TextColumn::make('rate')->money('EUR'),
            TextColumn::make('status')->badge(),
        ])->filters([TrashedFilter::make(), SelectFilter::make('creator_type')->label('Talent type')->options(['streamer'=>'Streamers','influencer'=>'Influencers'])])->headerActions([CreateAction::make()->label('Add represented talent')->before(fn () => $this->authorizeStaff())->mutateDataUsing(fn (array $data) => AgencyTalentInput::validate($data))])->recordActions([
            Action::make('approve')->label('Approve talent')->color('success')->requiresConfirmation()
                ->visible(fn (AgencyTalent $record) => ! $record->trashed() && $record->status !== 'approved')
                ->action(function (AgencyTalent $record): void { abort_unless(auth()->user()?->role === 'admin',403); $record->update(['status'=>'approved']); }),
            EditAction::make()->using(fn (AgencyTalent $record, array $data) => $this->mutateTalent($record, fn (AgencyTalent $locked) => $locked->update(AgencyTalentInput::validate($data)))),
            DeleteAction::make()->label('Remove from roster')->using(fn (AgencyTalent $record) => $this->mutateTalent($record, fn (AgencyTalent $locked) => $locked->delete())),
            RestoreAction::make()->using(fn (AgencyTalent $record) => $this->mutateTalent($record, function (AgencyTalent $locked): bool {
                $restored = $locked->restore();
                $locked->update(['status'=>'pending']);
                return $restored;
            })),
        ]);
    }

    private function guardCommitment(AgencyTalent $talent): void
    {
        abort_unless(auth()->user()?->role === 'admin',403);
        if ($talent->isCommitted()) {
            Notification::make()->title('Talent is part of an active campaign')->body('Keep contracted or live talent unchanged until delivery is complete.')->warning()->send();
            throw new Halt;
        }
    }

    private function authorizeStaff(): void { abort_unless(auth()->user()?->role === 'admin', 403); }

    private function mutateTalent(AgencyTalent $talent, Closure $operation): mixed
    {
        $this->authorizeStaff();
        return DB::transaction(function () use ($talent, $operation) {
            $talent->agency()->lockForUpdate()->firstOrFail();
            $locked = AgencyTalent::withTrashed()->whereKey($talent->id)->lockForUpdate()->firstOrFail();
            $this->guardCommitment($locked);
            return $operation($locked);
        });
    }
}
