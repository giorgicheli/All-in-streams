<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvitationResource\Pages\ManageInvitations;
use App\Filament\Support\ProviderOfferActions;
use App\Models\Invitation;
use App\Models\AgencyTalent;
use App\Services\CampaignWorkflow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InvitationResource extends ManagedResource
{
    protected static ?string $model = Invitation::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'Offers & final selection';
    protected static string|UnitEnum|null $navigationGroup = 'Campaign operations';
    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Staff offer review')->description('Business details are internal. Recipients see only their own neutral opportunity and commercial terms.')->schema([
                TextEntry::make('brief.title')->label('Private business campaign'),
                TextEntry::make('recipient_name')->label('Creator / agency'),
                TextEntry::make('opportunity_title')->label('Recipient-facing title'),
                TextEntry::make('status')->badge(), TextEntry::make('selection_status')->badge(),
                TextEntry::make('compensation')->money(fn (Invitation $record) => $record->currency ?: $record->brief->currency)->label('Private fixed fee'),
                TextEntry::make('cpa_rate')->label('Private CPA unit rate')->placeholder('Not offered'),
                TextEntry::make('counter_amount')->label('Requested fixed fee')->placeholder('No price change requested'),
                TextEntry::make('counter_cpa_rate')->label('Requested CPA rate')->placeholder('No CPA change requested'),
                TextEntry::make('offer_version')->label('Current offer version'),
                TextEntry::make('deliverables')->columnSpanFull(),
                TextEntry::make('offer_details.start_date')->label('Provider schedule starts'),
                TextEntry::make('offer_details.end_date')->label('Provider schedule ends'),
                TextEntry::make('agency_nominees')->label('Agency-proposed roster talent')->visible(fn (Invitation $record) => (bool)$record->agency_user_id)
                    ->state(fn (Invitation $record) => AgencyTalent::withTrashed()->where('agency_user_id', $record->agency_user_id)->whereIn('id', $record->proposed_talent_ids ?? [])->get()->map(fn (AgencyTalent $talent) => $talent->public_name.' · '.$talent->country.' · '.$talent->platform.' · '.$talent->status.($talent->trashed() ? ' (archived)' : '').' · '.($talent->twitch_url ?: $talent->kick_url))->implode("\n"))->placeholder('No roster talent proposed yet')->columnSpanFull(),
                TextEntry::make('requirements')->columnSpanFull()->placeholder('No additional requirements'),
                TextEntry::make('notes')->label('Shared offer message')->columnSpanFull()->placeholder('No message'),
                TextEntry::make('response_notes')->label('Recipient response')->columnSpanFull()->placeholder('No response notes'),
                TextEntry::make('selected_at')->dateTime()->placeholder('No final selection'),
                TextEntry::make('negotiation')->label('Negotiation history')->state(fn (Invitation $record) => $record->events()->oldest('id')->get()->map(fn ($event) => $event->created_at->format('d M H:i').' · '.str_replace('_',' ',$event->kind).' · '.($event->amount!==null?$record->currency.' '.$event->amount.' · ':'').($event->notes??''))->implode("\n"))->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at','desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['brief','creatorProfile','agency']))
            ->columns([
                TextColumn::make('brief.title')->label('Business campaign')->searchable()->wrap()->weight('semibold'),
                TextColumn::make('recipient_name')->label('Recipient')->description(fn (Invitation $record) => ucfirst($record->recipient_type).' · '.$record->recipient_code),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => $state==='interested'?'Accepted terms':str($state)->replace('_',' ')->title())->color(fn (string $state) => match($state){'interested'=>'success','changes_requested'=>'warning','declined'=>'danger',default=>'gray'}),
                TextColumn::make('selection_status')->label('Final selection')->badge()->formatStateUsing(fn (string $state) => str($state)->replace('_',' ')->title())->color(fn (string $state) => $state==='selected'?'success':'gray'),
                TextColumn::make('compensation')->label('Private fixed fee')->money(fn (Invitation $record) => $record->currency ?: $record->brief->currency),
                TextColumn::make('counter_amount')->label('Requested fee')->placeholder('—'),
                TextColumn::make('offer_version')->label('Version'),
                TextColumn::make('created_at')->since()->label('Sent'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending'=>'Awaiting response','interested'=>'Accepted terms','changes_requested'=>'Counter / changes requested','declined'=>'Declined']),
                SelectFilter::make('selection_status')->options(['unselected'=>'Not decided','selected'=>'Selected provider','not_selected'=>'Not selected']),
                SelectFilter::make('brief')->relationship('brief','title')->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make()->label('Review response'),
                Action::make('reviseOffer')->label('Revise & resend')->icon('heroicon-o-pencil-square')
                    ->visible(fn (Invitation $record) => self::canNegotiate($record))
                    ->schema(ProviderOfferActions::terms())->fillForm(fn (Invitation $record) => $record->only(['opportunity_title','compensation','cpa_rate','deliverables','requirements','notes'])+['start_date'=>$record->offer_details['start_date']??null,'end_date'=>$record->offer_details['end_date']??null])
                    ->action(fn (Invitation $record,array $data) => ProviderOfferActions::run(fn () => app(CampaignWorkflow::class)->reviseOffer($record,auth()->user(),$data),'Revised offer sent for confirmation')),
                Action::make('acceptCounter')->label('Accept requested price')->icon('heroicon-o-currency-euro')->color('success')
                    ->visible(fn (Invitation $record) => $record->status==='changes_requested' && ($record->counter_amount!==null || $record->counter_cpa_rate!==null) && self::canNegotiate($record))
                    ->requiresConfirmation()->modalDescription('Accept this recipient’s requested commercial terms. This does not select or launch a provider.')
                    ->action(fn (Invitation $record) => ProviderOfferActions::run(fn () => app(CampaignWorkflow::class)->acceptCounter($record,auth()->user()),'Requested price accepted')),
                Action::make('selectProvider')->label('Approve this provider')->icon('heroicon-o-check-badge')->color('primary')
                    ->visible(fn (Invitation $record) => $record->status==='interested' && $record->selection_status!=='selected' && in_array($record->brief->status,['matching','proposal']))
                    ->requiresConfirmation()->modalDescription('Choose exactly one accepted creator or agency for this campaign. Other offers will be marked not selected. Agreements and payment readiness are still required before launch.')
                    ->action(fn (Invitation $record) => ProviderOfferActions::run(fn () => app(CampaignWorkflow::class)->selectProvider($record,auth()->user()),'Final provider approved')),
            ])->emptyStateHeading('Send a private provider offer')->emptyStateDescription('Approve a business brief for matching, then send it to several independent creators or talent agencies.');
    }

    public static function getPages(): array { return ['index'=>ManageInvitations::route('/')]; }

    private static function canNegotiate(Invitation $record): bool
    {
        return in_array($record->brief->status, ['matching', 'proposal'], true)
            && $record->selection_status !== 'selected'
            && !$record->brief->selectedInvitations()->exists();
    }
}
