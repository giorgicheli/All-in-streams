<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorShowcaseResource\Pages\ManageCreatorShowcases;
use App\Models\CreatorShowcase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CreatorShowcaseResource extends ManagedResource
{
    protected static ?string $model = CreatorShowcase::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|UnitEnum|null $navigationGroup = 'Website';
    protected static ?string $navigationLabel = 'Public creator profiles';
    protected static ?string $modelLabel = 'public creator profile';
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $countries = array_values(array_unique(array_merge(...array_values(CreatorShowcase::COUNTRIES_BY_REGION))));
        return $schema->components([
            Section::make('Anonymous public card')->description('Only the selected audience, platforms and public aggregate figures appear on the website. Identity and contact details are protected.')->schema([
                TextInput::make('internal_title')->label('Internal reference')->required()->maxLength(180)->helperText('Visible to administrators only.'),
                Select::make('region')->options(array_combine(array_keys(CreatorShowcase::COUNTRIES_BY_REGION), array_keys(CreatorShowcase::COUNTRIES_BY_REGION)))->required(),
                Select::make('country')->options(array_combine($countries, $countries))->searchable()->required()->helperText('Choose a country within the selected audience region.'),
                Select::make('creator_type')->options(CreatorShowcase::TYPES)->required(),
                Select::make('platforms')->multiple()->options(array_combine(CreatorShowcase::PLATFORMS, CreatorShowcase::PLATFORMS))->required()->minItems(1)->maxItems(6),
                Select::make('language')->options(array_combine(CreatorShowcase::LANGUAGES, CreatorShowcase::LANGUAGES))->required(),
                Select::make('image_theme')->label('Studio image')->options(CreatorShowcase::IMAGES)->required(),
                TextInput::make('sort_order')->label('Display order')->numeric()->integer()->minValue(0)->maxValue(65535)->default(0)->required(),
            ])->columns(2),
            Section::make('Public audience figures')->schema([
                TextInput::make('followers')->numeric()->integer()->minValue(0)->maxValue(2147483647)->required()->default(0),
                TextInput::make('average_views')->label('Average content views')->numeric()->integer()->minValue(0)->maxValue(2147483647)->required()->default(0),
                Toggle::make('is_demo')->label('Demo profile')->helperText('Sample figures carry a demo label on the website.'),
            ])->columns(2),
            Section::make('Staff review')->schema([
                Toggle::make('is_verified')->label('Public card reviewed'),
                Toggle::make('is_published')->label('Publish in creator network')->helperText('Only three published cards per region appear, in display order.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('internal_title')->label('Internal reference')->searchable()->wrap(),
            TextColumn::make('region')->badge(), TextColumn::make('country')->searchable(),
            TextColumn::make('creator_type')->label('Type')->formatStateUsing(fn (string $state) => CreatorShowcase::TYPES[$state]),
            TextColumn::make('followers')->numeric()->sortable(),
            TextColumn::make('is_demo')->label('Dataset')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Demo' : 'Actual'),
            TextColumn::make('is_published')->label('Website')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Published' : 'Draft')->color(fn (bool $state) => $state ? 'success' : 'gray'),
        ])->filters([
            SelectFilter::make('region')->options(array_combine(array_keys(CreatorShowcase::COUNTRIES_BY_REGION), array_keys(CreatorShowcase::COUNTRIES_BY_REGION))),
            SelectFilter::make('creator_type')->options(CreatorShowcase::TYPES),
            SelectFilter::make('is_published')->options(['1' => 'Published', '0' => 'Draft']),
        ])->recordActions([
            EditAction::make(),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-eye-slash')->requiresConfirmation()->visible(fn (CreatorShowcase $record) => $record->is_published)->action(fn (CreatorShowcase $record) => $record->update(['is_published' => false])),
            DeleteAction::make(),
        ])->emptyStateHeading('Publish an anonymous creator card');
    }

    public static function getPages(): array
    {
        return ['index' => ManageCreatorShowcases::route('/')];
    }
}