<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerEnquiryResource\Pages\ManagePartnerEnquiries;
use App\Models\PartnerEnquiry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use UnitEnum;

class PartnerEnquiryResource extends ManagedResource
{
    protected static ?string $model = PartnerEnquiry::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static string|UnitEnum|null $navigationGroup = 'Partner network';
    protected static ?string $navigationLabel = 'Partner enquiries';
    protected static ?string $modelLabel = 'partner enquiry';
    protected static ?int $navigationSort = 0;

    public static function canCreate(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        $readonly = fn (string $field, string $label) => TextInput::make($field)->label($label)->disabled()->dehydrated(false);
        return $schema->components([
            Section::make('Submitted contact details')->description('Private initial enquiries for staff review. Submission does not create an account, approve a profile or launch a campaign.')->schema([
                Select::make('kind')->label('Category')->options(PartnerEnquiry::KINDS)->disabled()->dehydrated(false),
                Select::make('creator_type')->options(PartnerEnquiry::CREATOR_TYPES)->disabled()->dehydrated(false),
                $readonly('name', 'Name'), $readonly('email', 'Business email'),
                $readonly('telegram', 'Telegram'), $readonly('website', 'Company / agency website'),
                DateTimePicker::make('age_confirmed_at')->label('18+ confirmation received')->disabled()->dehydrated(false),
            ])->columns(2),
            Section::make('Submitted requirements')->schema([
                Select::make('audience_geos')->label('Audience countries')->multiple()->options(array_combine(PartnerEnquiry::countries(), PartnerEnquiry::countries()))->disabled()->dehydrated(false)->columnSpanFull(),
                $readonly('streams', 'Number of streams'), $readonly('budget', 'Campaign budget'),
                $readonly('currency', 'Currency'),
                $readonly('requested_region', 'Requested audience region'),
                TagsInput::make('shortlisted_profiles')->label('Shortlisted public profile references')->disabled()->dehydrated(false)->columnSpanFull(),
                Select::make('budget_scope')->label('Budget applies to')->options(['total' => 'Total campaign', 'per_stream' => 'Per stream'])->disabled()->dehydrated(false),
                Textarea::make('requirements')->label('Additional requirements / goals')->rows(4)->disabled()->dehydrated(false)->columnSpanFull(),
                $readonly('twitch_url', 'Twitch profile'), $readonly('kick_url', 'Kick profile'),
                $readonly('instagram_url', 'Instagram profile'), $readonly('stream_rate', 'Price per stream'),
                $readonly('story_rate', 'Price per Instagram story'), $readonly('reel_rate', 'Price per Instagram Reel'),
            ])->columns(2),
            Section::make('Staff review')->schema([
                Select::make('status')->options(PartnerEnquiry::STATUSES)->required()->in(array_keys(PartnerEnquiry::STATUSES)),
                Textarea::make('staff_notes')->label('Private staff notes')->rows(4)->maxLength(5000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->searchable()->description(fn (PartnerEnquiry $record) => $record->email),
            TextColumn::make('kind')->label('Category')->badge()->formatStateUsing(fn (string $state) => PartnerEnquiry::KINDS[$state] ?? $state),
            TextColumn::make('creator_type')->label('Creator type')->formatStateUsing(fn (?string $state) => PartnerEnquiry::CREATOR_TYPES[$state] ?? '—')->toggleable(),
            TextColumn::make('telegram')->searchable(),
            TextColumn::make('audience_geos')->label('Audience')->listWithLineBreaks()->limitList(3),
            TextColumn::make('budget')->numeric(decimalPlaces: 2)->placeholder('—')->description(fn (PartnerEnquiry $record) => $record->currency),
            TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => PartnerEnquiry::STATUSES[$state] ?? $state)
                ->color(fn (string $state) => match ($state) { 'new' => 'warning', 'in_review' => 'info', 'contacted' => 'success', default => 'gray' }),
            TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('kind')->label('Category')->options(PartnerEnquiry::KINDS),
            SelectFilter::make('creator_type')->options(PartnerEnquiry::CREATOR_TYPES),
            SelectFilter::make('status')->options(PartnerEnquiry::STATUSES),
        ])->recordActions([
            Action::make('account')->label('Account review')->icon('heroicon-o-user-circle')
                ->visible(fn (PartnerEnquiry $record) => $record->user_id !== null)
                ->url(fn (PartnerEnquiry $record) => UserAccountResource::getUrl('index', ['tableSearch' => $record->email])),
            EditAction::make()->label('Review')->mutateDataUsing(fn (array $data) => Arr::only($data, ['status', 'staff_notes'])),
            DeleteAction::make()->modalDescription('Permanently delete this initial enquiry. Existing user accounts and campaigns are retained.'),
        ])->emptyStateHeading('New partner enquiries arrive here')->emptyStateDescription('Brand, agency and creator contact forms are private to administrators.');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePartnerEnquiries::route('/')];
    }
}
