<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BriefResource;
use App\Models\Brief;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ReviewQueue extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('The review queue')
            ->description('Every campaign begins with a clear, considered brief.')
            ->query(Brief::query()->whereIn('status', ['submitted', 'review', 'changes_requested'])->latest())
            ->columns([
                TextColumn::make('title')->label('Campaign')->searchable()->weight('semibold')->description(fn (Brief $record) => $record->brand),
                TextColumn::make('budget')->money(fn (Brief $record) => $record->currency)->label('Budget'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title())->color('primary'),
                TextColumn::make('created_at')->since()->label('Submitted'),
            ])
            ->recordActions([
                Action::make('review')->label('Open queue')->url(fn () => BriefResource::getUrl())->icon('heroicon-o-arrow-up-right'),
            ])
            ->emptyStateHeading('All caught up')
            ->emptyStateDescription('New business briefs will appear here for review.')
            ->paginated([5]);
    }
}
