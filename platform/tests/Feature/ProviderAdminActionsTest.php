<?php

namespace Tests\Feature;

use App\Filament\Resources\CreatorProfileResource\Pages\ManageCreators;
use App\Filament\Resources\InvitationResource\Pages\ManageInvitations;
use App\Livewire\CreatorApplication;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    private function brief(string $status = 'matching'): Brief
    {
        return Brief::create([
            'user_id' => User::factory()->create(['role' => 'business'])->id, 'title' => 'Private campaign', 'brand' => 'Private Brand',
            'vertical' => 'Gaming', 'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch',
            'budget' => 18000, 'currency' => 'EUR', 'commercial_model' => 'Fixed', 'deliverables' => 'Private business requirements',
            'start_date' => today()->addDays(3), 'end_date' => today()->addDays(12), 'status' => $status,
        ]);
    }

    private function creator(): CreatorProfile
    {
        $user = User::factory()->create(['role' => 'creator']);

        return CreatorProfile::create([
            'user_id' => $user->id, 'public_name' => 'Streamer '.$user->id, 'country' => 'Germany', 'language' => 'German',
            'platform' => 'Twitch', 'channel_url' => 'https://twitch.tv/streamer'.$user->id, 'audience_geos' => ['Germany'],
            'vertical' => 'Gaming', 'rate' => 750, 'commercial_model' => 'Fixed', 'status' => 'approved',
        ]);
    }

    private function staff(): User
    {
        $staff = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $staff;
    }

    private function terms(array $override = []): array
    {
        return array_replace(['opportunity_title' => 'Gaming community opportunity', 'compensation' => 2400, 'deliverables' => '4 live streams, 90 minutes each', 'requirements' => 'Use the assigned tracking link.', 'notes' => 'Please confirm availability.'], $override);
    }

    public function test_bulk_invitation_action_sends_independent_offers_and_defaults_to_campaign_schedule(): void
    {
        $brief = $this->brief();
        $creators = [$this->creator(), $this->creator()];
        $this->staff();
        Livewire::test(ManageCreators::class)->callTableBulkAction('inviteMany', $creators, data: $this->terms(['brief_id' => $brief->id]))->assertHasNoActionErrors();

        $this->assertSame(2, $brief->invitations()->count());
        foreach ($brief->invitations as $invitation) {
            $this->assertSame('pending', $invitation->status);
            $this->assertSame('2400.00', $invitation->compensation);
            $this->assertSame($brief->start_date->toDateString(), $invitation->offer_details['start_date']);
            $this->assertSame($brief->end_date->toDateString(), $invitation->offer_details['end_date']);
        }
        $this->assertSame('matching', $brief->fresh()->status);
    }

    public function test_staff_revises_an_offer_gets_new_acceptance_and_selects_exactly_one_provider(): void
    {
        $staff = $this->staff();
        $brief = $this->brief();
        $first = $this->creator();
        $second = $this->creator();
        $workflow = app(CampaignWorkflow::class);
        $firstOffer = $workflow->invite($brief, $first, $staff, $this->terms());
        $secondOffer = $workflow->invite($brief, $second, $staff, $this->terms());
        $workflow->respond($firstOffer, $first->user, 'interested');
        $workflow->respond($secondOffer, $second->user, 'interested');
        Livewire::test(ManageInvitations::class)->callTableAction('reviseOffer', $firstOffer->fresh(), data: $this->terms(['compensation' => 2600]))->assertHasNoActionErrors();
        $this->assertSame('pending', $firstOffer->fresh()->status);
        $this->assertSame(2, $firstOffer->fresh()->offer_version);
        $this->assertNull($firstOffer->fresh()->accepted_offer_version);
        $workflow->respond($firstOffer->fresh(), $first->user, 'interested');
        Livewire::test(ManageInvitations::class)->callTableAction('selectProvider', $firstOffer->fresh())->assertHasNoActionErrors();

        $this->assertSame('selected', $firstOffer->fresh()->selection_status);
        $this->assertSame('not_selected', $secondOffer->fresh()->selection_status);
        $this->assertSame(1, $brief->invitations()->where('selection_status', 'selected')->count());
        $this->assertSame('matching', $brief->fresh()->status);
        $this->assertNotNull($firstOffer->fresh()->selected_at);
    }

    public function test_creator_delete_action_preserves_history_and_refuses_active_selected_work(): void
    {
        $staff = $this->staff();
        $historical = $this->creator();
        $active = $this->creator();
        $completed = $this->brief('completed');
        $contract = $this->brief('contracts');
        foreach ([[$completed, $historical], [$contract, $active]] as [$brief, $creator]) {
            Invitation::create(['brief_id' => $brief->id, 'creator_profile_id' => $creator->id, 'status' => 'interested', 'selection_status' => 'selected', 'offer_version' => 1, 'accepted_offer_version' => 1, 'compensation' => 2400, 'currency' => 'EUR', 'deliverables' => '4 streams']);
        }
        Livewire::test(ManageCreators::class)->callTableAction('delete', $historical)->assertHasNoActionErrors();
        $this->assertSoftDeleted('creator_profiles', ['id' => $historical->id]);
        $this->assertSame(1, $completed->invitations()->count());
        $this->assertSame($historical->id, $completed->invitations()->sole()->creatorProfile->id);
        Livewire::test(ManageCreators::class)->callTableAction('delete', $active)->assertNotified('Action could not be completed');
        $this->assertFalse($active->fresh()->trashed());
        $this->assertSame('selected', $contract->invitations()->sole()->selection_status);
    }

    public function test_selected_creator_can_view_profile_but_cannot_change_it_even_from_an_existing_component(): void
    {
        $creator = $this->creator();
        $component = Livewire::actingAs($creator->user)->test(CreatorApplication::class);
        $brief = $this->brief('live');
        Invitation::create(['brief_id' => $brief->id, 'creator_profile_id' => $creator->id, 'status' => 'interested', 'selection_status' => 'selected', 'offer_version' => 1, 'accepted_offer_version' => 1, 'compensation' => 2400, 'currency' => 'EUR', 'deliverables' => '4 streams']);
        $component->set('public_name', 'Forged replacement creator')->call('submit')->assertHasErrors('profile');
        $this->assertSame('approved', $creator->fresh()->status);
        $this->assertSame($creator->public_name, $creator->fresh()->public_name);

        Livewire::actingAs($creator->user)->test(CreatorApplication::class)
            ->assertSet('step', 5)->assertSet('profileCommitted', true)
            ->assertSee($creator->public_name)->assertSee('committed to a contract or live campaign')
            ->assertDontSee('Submit profile for review');
        $this->actingAs($creator->user)->get(route('creator.apply'))->assertOk()->assertSee($creator->public_name);
    }

    public function test_staff_active_profile_is_view_only_and_a_forged_edit_action_cannot_mount(): void
    {
        $creator = $this->creator();
        $brief = $this->brief('contracts');
        Invitation::create(['brief_id' => $brief->id, 'creator_profile_id' => $creator->id, 'status' => 'interested', 'selection_status' => 'selected', 'offer_version' => 1, 'accepted_offer_version' => 1, 'compensation' => 2400, 'currency' => 'EUR', 'deliverables' => '4 streams']);
        $this->staff();
        Livewire::test(ManageCreators::class)
            ->assertTableActionHidden('edit', $creator)->assertTableActionVisible('view', $creator)
            ->call('mountAction', 'edit', [], ['table' => true, 'recordKey' => (string) $creator->id])
            ->assertSet('mountedActions', []);
        $this->assertSame('approved', $creator->fresh()->status);
    }
}
