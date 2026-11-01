<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignMetricResource\Pages\ManageMetrics;
use App\Models\Brief;
use App\Models\CampaignMetric;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use UnitEnum;

class CampaignMetricResource extends ManagedResource
{
    protected static ?string $model = CampaignMetric::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Campaign reporting';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 5;

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Daily campaign report')
                ->description('Enter daily results supplied by the campaign team. Demo campaign reports are simulated. These figures are entered manually; no streaming-platform analytics provider is connected.')
                ->schema([
                    Select::make('brief_id')->label('Live or completed campaign')
                        ->options(fn () => Brief::whereIn('status', ['live', 'completed'])->latest()->get()->mapWithKeys(fn (Brief $brief) => [$brief->id => $brief->title.($brief->is_demo ? ' · Demo' : '')])->all())
                        ->required()->searchable()->live()
                        ->rules([Rule::exists('briefs', 'id')->whereIn('status', ['live', 'completed'])]),
                    DatePicker::make('recorded_on')->label('Report date')->required()->default(today())
                        ->maxDate(today())->beforeOrEqual('today')
                        ->rules(fn (Get $get, ?CampaignMetric $record) => [function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $query = CampaignMetric::where('brief_id', $get('brief_id'))->whereDate('recorded_on', $value);
                            if ($record) {
                                $query->whereKeyNot($record->id);
                            }
                            if ($query->exists()) {
                                $fail('A daily report already exists for this campaign and date. Edit that report instead.');
                            }
                        }])
                        ->helperText('One report per campaign per day. Edit the existing report to correct its results.'),
                ])->columns(2),
            Section::make('Reported performance')->schema([
                static::counter('impressions', 'Impressions'),
                static::counter('unique_viewers', 'Unique viewers')->lte('impressions'),
                static::counter('clicks', 'Tracking-link clicks')->lte('impressions'),
                static::counter('conversions', 'Conversions')->lte('clicks'),
                static::counter('streams', 'Completed streams')->maxValue(100000),
                static::counter('watch_minutes', 'Watch minutes'),
            ])->columns(3),
        ]);
    }

    private static function counter(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->integer()->minValue(0)->maxValue(2147483647)->required()->default(0);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('recorded_on', 'desc')
            ->columns([
                TextColumn::make('brief.title')->label('Campaign')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('recorded_on')->label('Report date')->date('M j, Y')->sortable(),
                TextColumn::make('brief.is_demo')->label('Dataset')->badge()->formatStateUsing(fn (bool $state) => $state ? 'Demo' : 'Actual')->color(fn (bool $state) => $state ? 'warning' : 'success'),
                TextColumn::make('impressions')->numeric()->sortable(),
                TextColumn::make('unique_viewers')->label('Unique viewers')->numeric()->sortable(),
                TextColumn::make('clicks')->numeric()->sortable(),
                TextColumn::make('conversions')->numeric()->sortable(),
                TextColumn::make('streams')->numeric()->sortable(),
                TextColumn::make('watch_minutes')->label('Watch minutes')->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brief')->relationship('brief', 'title')->label('Campaign')->searchable()->preload(),
            ])
            ->recordActions([EditAction::make()->label('Edit report')])
            ->emptyStateHeading('Report campaign performance')
            ->emptyStateDescription('Add a daily report for a live or completed campaign. Campaign and dashboard statistics update from these reports.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMetrics::route('/')];
    }
}
