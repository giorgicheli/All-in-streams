<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserAccountResource\Pages\ListUserAccounts;
use App\Models\User;
use App\Rules\TelegramHandle;
use App\Services\PartnerVerification;
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
            TextEntry::make('partner_verification_status')->label('Account verification')->badge()
                ->formatStateUsing(fn (string $state, User $record) => $record->role === 'admin' ? 'Verified' : ucfirst($state)),
            TextEntry::make('partner_verified_at')->label('Verified')->dateTime()->placeholder('Awaiting approval'),
            TextEntry::make('partnerVerifier.name')->label('Approved by')->placeholder('—'),
            TextEntry::make('internal_files_count')->label('Private files'), TextEntry::make('created_at')->dateTime(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
            TextColumn::make('email')->searchable()->copyable(),
            TextColumn::make('account_type_label')->label('Role')->badge(),
            TextColumn::make('partner_verification_status')->label('Account verification')->badge()
                ->formatStateUsing(fn (string $state, User $record) => $record->role === 'admin' ? 'Verified' : ucfirst($state))
                ->color(fn (string $state, User $record) => $record->hasVerifiedPartnerAccount() ? 'success' : ($state === 'rejected' ? 'danger' : 'warning')),
            TextColumn::make('company')->searchable()->placeholder('—'),
            TextColumn::make('telegram')->searchable()->copyable()->placeholder('Not provided'),
            TextColumn::make('internal_files_count')->label('Private files')->sortable(),
            TextColumn::make('created_at')->label('Registered')->date()->sortable(),
        ])->filters([
            SelectFilter::make('role')->options(['admin' => 'Administrator', 'business' => 'Business', 'agency' => 'Agency', 'creator' => 'Creator (all types)']),
            SelectFilter::make('creator_type')->label('Creator type')->options(['streamer' => 'Streamer', 'influencer' => 'Influencer', 'both' => 'Streamer & influencer'])
                ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $type) => $query->whereHas('creatorProfile', fn (Builder $profile) => $profile->whereIn('creator_type', $type === 'both' ? ['both'] : [$type, 'both'])))),
            SelectFilter::make('partner_verification_status')->label('Account verification')
                ->options(['pending' => 'Pending verification', 'verified' => 'Verified', 'rejected' => 'Rejected'])
                ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, function (Builder $query, string $status) {
                    return $query->where(function (Builder $accounts) use ($status) {
                        $accounts->where('partner_verification_status', $status)->where('role', '!=', 'admin');
                        if ($status === 'verified') $accounts->orWhere('role', 'admin');
                    });
                })),
            SelectFilter::make('is_demo')->label('Workspace')->options(['1' => 'Demo accounts', '0' => 'Registered accounts']),
        ])->recordActions([
            ViewAction::make(),
            Action::make('verifyPartner')->label('Approve account')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (User $record) => $record->role !== 'admin' && ! $record->hasVerifiedPartnerAccount())
                ->requiresConfirmation()->modalDescription('Approve this partner account and activate its workspace.')
                ->action(function (User $record): void {
                    app(PartnerVerification::class)->verify(auth()->user(), $record);
                    Notification::make()->title('Partner account approved')->success()->send();
                }),
            Action::make('rejectPartner')->label('Reject account')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (User $record) => $record->role !== 'admin' && $record->partner_verification_status !== 'rejected')
                ->requiresConfirmation()->modalDescription('The partner will see the account review decision when signed in.')
                ->action(function (User $record): void {
                    app(PartnerVerification::class)->reject(auth()->user(), $record);
                    Notification::make()->title('Partner account rejected')->success()->send();
                }),
            Action::make('revokePartner')->label('Return to review')->icon('heroicon-o-arrow-path')
                ->visible(fn (User $record) => $record->role !== 'admin' && $record->hasVerifiedPartnerAccount())
                ->requiresConfirmation()->modalDescription('Return the account to pending verification and pause workspace access.')
                ->action(function (User $record): void {
                    app(PartnerVerification::class)->revoke(auth()->user(), $record);
                    Notification::make()->title('Partner account returned to review')->success()->send();
                }),
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
