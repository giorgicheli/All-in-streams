<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PublicCampaignResultResource\Pages\ManagePublicResults;
use App\Models\CreatorProfile;
use App\Models\PublicCampaignResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PublicCampaignResultResource extends ManagedResource
{
    protected static ?string $model = PublicCampaignResult::class;
    protected static string|BackedEnum|null $navigationIcon='heroicon-o-presentation-chart-line';
    protected static string|UnitEnum|null $navigationGroup='Website';
    protected static ?string $navigationLabel='Published results';
    protected static ?string $modelLabel='public campaign result';
    protected static ?int $navigationSort=2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Completed campaign')->description('Only country, platform, delivery counts and aggregate results reach the website. Private company and creator identities are never copied into these cards.')->schema([
                TextInput::make('internal_title')->label('Internal reference')->required()->maxLength(180)->helperText('Visible to administrators only.'),
                Select::make('country')->options(array_combine(CreatorProfile::AUDIENCE_COUNTRIES,CreatorProfile::AUDIENCE_COUNTRIES))->searchable()->required(),
                Select::make('region')->options(array_combine(PublicCampaignResult::REGIONS,PublicCampaignResult::REGIONS))->required(),
                Select::make('platform')->options(array_combine(PublicCampaignResult::PLATFORMS,PublicCampaignResult::PLATFORMS))->required(),
                DatePicker::make('completed_on')->label('Completion date')->required()->maxDate(today())->beforeOrEqual('today'),
                Toggle::make('is_demo')->label('Simulated demo result')->helperText('Demo records display a visible sample-data badge on the website.'),
            ])->columns(2),
            Section::make('Public aggregate figures')->schema([
                self::counter('streams','Completed streams'), self::counter('posts','Completed posts'),
                self::counter('views','Views'), self::counter('clicks','Link clicks')->lte('views'), self::counter('conversions','Conversions')->lte('clicks'),
            ])->columns(3),
            Section::make('Review and publication')->schema([
                Toggle::make('is_verified')->label('Figures reviewed by staff')->helperText('Confirm the figures are approved for public display. Demo figures remain labelled as simulated.'),
                Toggle::make('is_published')->label('Publish on the website')->helperText('Requires a completed date, at least one delivered item and staff review.'),
            ])->columns(2),
        ]);
    }
    private static function counter(string $name,string $label): TextInput { return TextInput::make($name)->label($label)->numeric()->integer()->minValue(0)->maxValue(2147483647)->required()->default(0); }
    public static function table(Table $table): Table
    {
        return $table->defaultSort('completed_on','desc')->columns([
            TextColumn::make('internal_title')->label('Internal reference')->searchable()->wrap(),
            TextColumn::make('country')->searchable(),TextColumn::make('platform')->badge(),
            TextColumn::make('completed_on')->date()->sortable(),
            TextColumn::make('views')->numeric()->sortable(),TextColumn::make('clicks')->numeric()->sortable(),
            TextColumn::make('is_demo')->label('Dataset')->badge()->formatStateUsing(fn(bool $state)=>$state?'Demo':'Actual')->color(fn(bool $state)=>$state?'warning':'success'),
            TextColumn::make('is_published')->label('Website')->badge()->formatStateUsing(fn(bool $state)=>$state?'Published':'Draft')->color(fn(bool $state)=>$state?'success':'gray'),
        ])->filters([
            SelectFilter::make('region')->options(array_combine(PublicCampaignResult::REGIONS,PublicCampaignResult::REGIONS)),
            SelectFilter::make('is_published')->label('Publication')->options(['1'=>'Published','0'=>'Draft']),
        ])->recordActions([
            EditAction::make(),
            Action::make('unpublish')->label('Unpublish')->icon('heroicon-o-eye-slash')->visible(fn(PublicCampaignResult $record)=>$record->is_published)->requiresConfirmation()->action(fn(PublicCampaignResult $record)=>$record->update(['is_published'=>false])),
            DeleteAction::make(),
        ])->emptyStateHeading('Publish a completed campaign result')->emptyStateDescription('Public aggregate cards are separate from private campaign reports. Add and review a result to share it here.');
    }
    public static function getPages(): array { return ['index'=>ManagePublicResults::route('/')]; }
}
