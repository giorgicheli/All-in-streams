<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserAccountResource\Pages\ListUserAccounts;
use App\Models\User;
use App\Rules\TelegramHandle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserAccountResource extends ManagedResource
{
    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationLabel = 'All user accounts';
    protected static ?string $modelLabel = 'user account';
    protected static string|UnitEnum|null $navigationGroup = 'Partner network';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->with('creatorProfile')->withCount('internalFiles'); }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'), TextEntry::make('email')->copyable(), TextEntry::make('account_type_label')->label('Role')->badge(),
            TextEntry::make('company')->placeholder('—'), TextEntry::make('telegram')->placeholder('Not provided')->copyable(),
            TextEntry::make('internal_files_count')->label('Private files'), TextEntry::make('created_at')->dateTime(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->searchable()->copyable(),
            TextColumn::make('account_type_label')->label('Role')->badge(),
            TextColumn::make('company')->searchable()->placeholder('—'),
            TextColumn::make('telegram')->searchable()->copyable()->placeholder('Not provided'),
            TextColumn::make('internal_files_count')->label('Private files')->sortable(),
            TextColumn::make('created_at')->label('Registered')->date()->sortable(),
        ])->filters([
            SelectFilter::make('role')->options(['admin' => 'Administrator', 'business' => 'Business', 'agency' => 'Agency', 'creator' => 'Creator (all types)']),
            SelectFilter::make('creator_type')->label('Creator type')->options(['streamer' => 'Streamer', 'influencer' => 'Influencer'])
                ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $type) => $query->whereHas('creatorProfile', fn (Builder $profile) => $profile->where('creator_type', $type)))),
            SelectFilter::make('is_demo')->label('Workspace')->options(['1' => 'Demo accounts', '0' => 'Registered accounts']),
        ])->recordActions([
            ViewAction::make(),
            Action::make('telegram')->label('Telegram')->icon('heroicon-o-chat-bubble-left-right')->fillForm(fn (User $record) => ['telegram' => $record->telegram])
                ->schema([TextInput::make('telegram')->label('Telegram username')->required()->maxLength(80)->rules([new TelegramHandle])->helperText('@username or https://t.me/username')])
                ->action(function (User $record, array $data): void {
                    abort_unless(auth()->user()?->role === 'admin', 403);
                    $record->update(['telegram' => TelegramHandle::canonicalize($data['telegram'])]);
                    Notification::make()->title('Telegram updated')->success()->send();
                }),
            Action::make('attachFiles')->label('Attach files')->icon('heroicon-o-paper-clip')->schema(fn (User $record) => UserFileResource::uploadSchema($record->id))
                ->action(fn (User $record, array $data) => UserFileResource::upload($data, $record)),
            Action::make('files')->label('Private files')->icon('heroicon-o-folder')->url(fn (User $record) => UserFileResource::forUserUrl($record)),
        ]);
    }

    public static function getPages(): array { return ['index' => ListUserAccounts::route('/')]; }
}
