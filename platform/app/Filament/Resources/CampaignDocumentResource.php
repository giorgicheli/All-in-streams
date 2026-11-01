<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignDocumentResource\Pages\ManageDocuments;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use BackedEnum;
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
            Section::make('Document access')->description('Business and creator commercial terms have separate access. Files are stored privately and downloaded through an authorized route.')->schema([
                TextInput::make('title')->required()->maxLength(180),
                Select::make('brief_id')->label('Campaign')->options(fn () => Brief::latest()->pluck('title', 'id')->all())->required()->searchable()->live()
                    ->afterStateUpdated(fn (Set $set) => $set('creator_profile_id', null)),
                Select::make('audience')->label('Visible to')->options(['business' => 'Business only', 'creator' => 'Selected creator only', 'admin' => 'Internal staff only'])->required()->default('admin')->live()
                    ->afterStateUpdated(fn (Set $set) => $set('creator_profile_id', null)),
                Select::make('creator_profile_id')->label('Creator')->options(fn (Get $get) => CreatorProfile::whereHas('invitations', fn ($query) => $query->where('brief_id', $get('brief_id')))->pluck('public_name', 'id')->all())
                    ->visible(fn (Get $get) => $get('audience') === 'creator')->required(fn (Get $get) => $get('audience') === 'creator')->searchable()->dehydratedWhenHidden(),
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
                TextColumn::make('audience')->badge()->formatStateUsing(fn (string $state) => match ($state) {'business' => 'Business only', 'creator' => 'Creator only', default => 'Internal staff'})->color('gray'),
                TextColumn::make('creatorProfile.public_name')->label('Creator')->placeholder('—'),
                TextColumn::make('signature_status')->label('Signature')->badge()->formatStateUsing(fn (string $state) => match ($state) {'signed' => 'Signed', 'pending' => 'Awaiting signature', default => 'Uploaded'})->color(fn (string $state) => match ($state) {'signed' => 'success', 'pending' => 'warning', default => 'gray'}),
                TextColumn::make('updated_at')->date('M j, Y')->label('Updated')->sortable(),
            ])
            ->filters([
                SelectFilter::make('audience')->options(['business' => 'Business', 'creator' => 'Creator', 'admin' => 'Internal staff']),
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
}
