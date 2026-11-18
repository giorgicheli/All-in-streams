<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use UnitEnum;

class ContactMessageResource extends ManagedResource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Contact messages';

    protected static ?string $modelLabel = 'contact message';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static string|Htmlable|null $navigationBadgeTooltip = 'New contact messages';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (auth()->user()?->role !== 'admin' || ! DatabaseSchema::hasTable('contact_messages')) {
            return null;
        }

        $count = ContactMessage::query()->where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submitted message')->schema([
                TextInput::make('name')->disabled()->dehydrated(false),
                TextInput::make('email')->disabled()->dehydrated(false),
                TextInput::make('subject')->disabled()->dehydrated(false)->columnSpanFull(),
                Textarea::make('message')->rows(7)->disabled()->dehydrated(false)->columnSpanFull(),
            ])->columns(2),
            Section::make('Staff review')->description('Status and notes are private to administrators. Saving a review does not send an email.')->schema([
                Select::make('status')->options(ContactMessage::STATUSES)
                    ->required()->in(array_keys(ContactMessage::STATUSES)),
                Textarea::make('staff_notes')->label('Private staff notes')->rows(4)->maxLength(5000),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submitted message')->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('message')->columnSpanFull()
                    ->extraAttributes(['style' => 'white-space: pre-wrap; overflow-wrap: anywhere;']),
                TextEntry::make('created_at')->label('Received')->dateTime(),
            ])->columns(2),
            Section::make('Staff review')->schema([
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => self::statusColor($state)),
                TextEntry::make('staff_notes')->label('Private staff notes')->placeholder('No staff notes.')
                    ->extraAttributes(['style' => 'white-space: pre-wrap; overflow-wrap: anywhere;']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->wrap(),
                TextColumn::make('email')->searchable()->copyable()->wrap(),
                TextColumn::make('subject')->searchable()->limit(80)->wrap(),
                TextColumn::make('status')->badge()->sortable()
                    ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => self::statusColor($state)),
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContactMessage::STATUSES),
                Filter::make('received')->label('Received date')->schema([
                    DatePicker::make('from')->label('Received from'),
                    DatePicker::make('until')->label('Received through'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        'from' => filled($data['from'] ?? null) ? 'Received from '.$data['from'] : null,
                        'until' => filled($data['until'] ?? null) ? 'Received through '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([
                ViewAction::make()->label('Read message'),
                EditAction::make()->label('Review')
                    ->mutateRecordDataUsing(fn (array $data, ContactMessage $record): array => [
                        ...$data,
                        'staff_notes' => $record->staff_notes,
                    ])
                    ->mutateDataUsing(fn (array $data): array => Arr::only($data, ['status', 'staff_notes'])),
                DeleteAction::make()->requiresConfirmation()
                    ->modalDescription('Permanently delete this contact message and its private staff notes.'),
            ])
            ->emptyStateHeading('No contact messages yet')
            ->emptyStateDescription('Messages submitted through the contact form appear here and are visible only to administrators.');
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'new' => 'warning',
            'in_review' => 'info',
            'replied' => 'success',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return ['index' => ManageContactMessages::route('/')];
    }
}
