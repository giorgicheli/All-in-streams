<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserFileResource\Pages\ManageUserFiles;
use App\Models\User;
use App\Models\UserFile;
use App\Services\PrivateUserFiles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class UserFileResource extends ManagedResource
{
    protected static ?string $model = UserFile::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Private user files';
    protected static ?string $modelLabel = 'private user file';
    protected static string|UnitEnum|null $navigationGroup = 'Partner network';
    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'uploader'])->select('user_files.*')
            ->selectSub(DB::table('user_files as latest_user_file')->select('latest_user_file.id')
                ->whereColumn('latest_user_file.user_id', 'user_files.user_id')
                ->orderByDesc('latest_user_file.created_at')->orderByDesc('latest_user_file.id')->limit(1), 'latest_attachment_id');
    }

    public static function uploadSchema(?int $userId = null): array
    {
        return [
            Select::make('user_id')->label('User account')->options(fn () => User::query()->orderBy('name')->get()->mapWithKeys(fn (User $user) => [$user->id => $user->name.' — '.$user->email.' ('.$user->account_type_label.')'])->all())
                ->searchable()->required()->default($userId)->disabled($userId !== null)->dehydrated()->rules(['exists:users,id']),
            TextInput::make('title')->label('File label')->maxLength(180)->helperText('Optional. Leave empty to use each file name.'),
            FileUpload::make('files')->label('Private documents')->multiple()->appendFiles()->panelLayout(null)->maxParallelUploads(1)
                ->storeFiles(false)->visibility('private')->acceptedFileTypes(PrivateUserFiles::MIME_TYPES)
                ->maxSize(PrivateUserFiles::MAX_KILOBYTES)->minFiles(1)->maxFiles(10)->required()
                ->helperText('Select several PDF, DOC or DOCX files together, or add more before attaching. Up to 10 files, 10 MB each. Existing documents are retained; only administrators can access them.'),
            Textarea::make('notes')->label('Internal notes')->maxLength(3000)->rows(3),
        ];
    }

    public static function upload(array $data, ?User $account = null): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $records = app(PrivateUserFiles::class)->upload(auth()->user(), $account ?: User::findOrFail($data['user_id']), $data['files'] ?? [], $data['title'] ?? null, $data['notes'] ?? null);
        Notification::make()->title(count($records).' private file(s) attached')->body('These documents are available only to administrators.')->success()->send();
    }

    public static function forUserUrl(User $user): string
    {
        return static::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))->columns([
            TextColumn::make('title')->searchable()->wrap()->weight('semibold')->description(fn (UserFile $record) => $record->original_name),
            TextColumn::make('is_latest_attachment')->label('Latest attachment')->badge(fn (UserFile $record) => $record->is_latest_attachment)
                ->formatStateUsing(fn (bool $state) => $state ? 'ბოლო მიბმული' : '')->color('success'),
            TextColumn::make('user.name')->label('User')->searchable()->description(fn (UserFile $record) => $record->user?->email),
            TextColumn::make('user.account_type_label')->label('Account type')->badge(),
            TextColumn::make('size')->label('Size')->formatStateUsing(fn (int $state) => number_format($state / 1048576, 2).' MB')->sortable(),
            TextColumn::make('notes')->label('Internal notes')->limit(60)->wrap(),
            TextColumn::make('uploader.name')->label('Uploaded by'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('user_id')->label('User account')->relationship('user', 'email')->searchable()->preload(),
        ])->recordActions([
            Action::make('download')->label('Download')->icon('heroicon-o-arrow-down-tray')->url(fn (UserFile $record) => route('admin.user-files.download', $record))->openUrlInNewTab(),
            DeleteAction::make()->modalDescription('Permanently deletes this private file. Other files attached to the account are retained.')
                ->using(function (UserFile $record): bool { app(PrivateUserFiles::class)->delete(auth()->user(), $record); return true; }),
        ])->emptyStateHeading('Private account documents')->emptyStateDescription('Attach agreements or internal documents to any user. These files never appear in a partner portal.');
    }

    public static function getPages(): array { return ['index' => ManageUserFiles::route('/')]; }
}
