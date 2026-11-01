<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignDocumentResource\Pages\ManageDocuments;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use UnitEnum;

class CampaignDocumentResource extends ManagedResource
{
    protected static ?string $model = CampaignDocument::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationLabel = 'Document centre';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document access')->description('Business, creator and agency provider terms have separate access. Provider files belong only to the single selected, accepted provider. Files are stored privately.')->schema([
                TextInput::make('title')->required()->maxLength(180),
                Select::make('brief_id')->label('Campaign')->options(fn () => Brief::latest()->pluck('title', 'id')->all())->required()->searchable()->live()
                    ->rules(['exists:briefs,id'])->afterStateUpdated(function (Set $set) { $set('creator_profile_id', null); $set('agency_user_id', null); }),
                Select::make('audience')->label('Visible to')->options(['business' => 'Business only', 'creator' => 'Selected creator only', 'agency' => 'Selected agency provider only', 'admin' => 'Internal staff only'])->required()->default('admin')->live()
                    ->rules([Rule::in(['business', 'creator', 'agency', 'admin'])])
                    ->afterStateUpdated(function (Set $set) { $set('creator_profile_id', null); $set('agency_user_id', null); }),
                Select::make('creator_profile_id')->label('Selected creator')->options(fn (Get $get) => CreatorProfile::withTrashed()->whereHas('invitations', fn ($query) => static::selected($query, $get('brief_id')))->pluck('public_name', 'id')->all())
                    ->visible(fn (Get $get) => $get('audience') === 'creator')->required(fn (Get $get) => $get('audience') === 'creator')->searchable()
                    ->rules(fn (Get $get) => [static::recipientRule($get, 'creator_profile_id', 'creator')])
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('audience') === 'creator' ? $state : null)->dehydratedWhenHidden(),
                Select::make('agency_user_id')->label('Selected agency')->options(fn (Get $get) => User::where('role', 'agency')->whereIn('id', static::selected(Invitation::query(), $get('brief_id'))->whereNotNull('agency_user_id')->pluck('agency_user_id'))->get()->mapWithKeys(fn (User $agency) => [$agency->id => $agency->company ?: $agency->name])->all())
                    ->visible(fn (Get $get) => $get('audience') === 'agency')->required(fn (Get $get) => $get('audience') === 'agency')->searchable()
                    ->rules(fn (Get $get) => [static::recipientRule($get, 'agency_user_id', 'agency')])
                    ->dehydrateStateUsing(fn ($state, Get $get) => $get('audience') === 'agency' ? $state : null)->dehydratedWhenHidden(),
            ])->columns(2),
            Section::make('File and signature')->schema([
                FileUpload::make('file_path')->label('Document file')->disk('local')->visibility('private')->directory('campaign-documents')->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg'])->maxSize(10240)->required()->helperText('PDF, PNG or JPEG, up to 10 MB.'),
                Select::make('signature_status')->options(['uploaded' => 'Uploaded — not signed', 'pending' => 'Awaiting signature', 'signed' => 'Signed — verified by staff'])->default('uploaded')->required()
                    ->helperText('Uploading never marks a document signed. Select Signed only after staff verifies the executed agreement.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->weight('semibold')->wrap(),
                TextColumn::make('brief.title')->label('Campaign')->searchable()->wrap(),
                TextColumn::make('audience')->badge()->formatStateUsing(fn (string $state) => match ($state) {'business' => 'Business only', 'creator' => 'Creator only', 'agency' => 'Agency provider only', default => 'Internal staff'})->color('gray'),
                TextColumn::make('creatorProfile.public_name')->label('Creator')->placeholder('—'),
                TextColumn::make('signature_status')->label('Signature')->badge()->formatStateUsing(fn (string $state) => match ($state) {'signed' => 'Signed', 'pending' => 'Awaiting signature', default => 'Uploaded'})->color(fn (string $state) => match ($state) {'signed' => 'success', 'pending' => 'warning', default => 'gray'}),
                TextColumn::make('updated_at')->date('M j, Y')->label('Updated')->sortable(),
                TextColumn::make('agency.company')->label('Agency')->formatStateUsing(fn ($state, CampaignDocument $record) => $state ?: $record->agency?->name)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('audience')->options(['business' => 'Business', 'creator' => 'Creator', 'agency' => 'Agency provider', 'admin' => 'Internal staff']),
                SelectFilter::make('signature_status')->options(['uploaded' => 'Uploaded', 'pending' => 'Awaiting signature', 'signed' => 'Signed']),
            ])
            ->recordActions([
                Action::make('download')->label('Download')->icon('heroicon-o-arrow-down-tray')->url(fn (CampaignDocument $record) => url('/documents/'.$record->id.'/download'))->openUrlInNewTab(),
                EditAction::make()->label('Manage'),
            ])
            ->emptyStateHeading('Your documents, protected')->emptyStateDescription('Upload the campaign agreements and choose which party can access each file.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDocuments::route('/')];
    }

    private static function selected($query, $briefId)
    {
        return $query->where('brief_id', $briefId)->where('selection_status', 'selected')->where('status', 'interested')->whereColumn('accepted_offer_version', 'offer_version');
    }

    private static function recipientRule(Get $get, string $column, string $audience): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $column, $audience): void {
            if ($get('audience') === $audience && ! static::selected(Invitation::query(), $get('brief_id'))->where($column, $value)->exists()) {
                $fail('Choose the selected provider who accepted the current offer for this campaign.');
            }
        };
    }
}
