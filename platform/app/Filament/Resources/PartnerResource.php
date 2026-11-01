<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages\ListPartners;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PartnerResource extends ManagedResource
{
    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Businesses';
    protected static ?string $modelLabel = 'business';
    protected static ?string $pluralModelLabel = 'businesses';
    protected static string|UnitEnum|null $navigationGroup = 'Partner network';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'company';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'business')->withCount('briefs');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Partner account')->schema([
                TextEntry::make('company')->placeholder('Independent partner'),
                TextEntry::make('account_type_label')->label('Account type')->badge(),
                TextEntry::make('name')->label('Contact name'),
                TextEntry::make('email')->label('Contact email')->copyable(),
                TextEntry::make('briefs_count')->label('Campaign briefs'),
                TextEntry::make('created_at')->label('Registered')->dateTime(),
                TextEntry::make('is_demo')->label('Workspace')->formatStateUsing(fn (bool $state) => $state ? 'Demo — fictional sample account' : 'Registered partner'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company')->label('Business')->searchable()->sortable()->placeholder('Independent business'),
            TextColumn::make('name')->label('Contact')->searchable(),
            TextColumn::make('account_type')->label('Type')->formatStateUsing(fn (?string $state) => $state === 'agency' ? 'Agency' : 'Business')->badge(),
            TextColumn::make('email')->searchable()->copyable(),
            TextColumn::make('briefs_count')->label('Briefs')->sortable(),
            IconColumn::make('is_demo')->label('Demo')->boolean(),
            TextColumn::make('created_at')->label('Registered')->date()->sortable(),
        ])->filters([
            SelectFilter::make('is_demo')->label('Workspace')->options(['1' => 'Demo accounts', '0' => 'Registered partners']),
        ])->recordActions([ViewAction::make()])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListPartners::route('/')];
    }
}
