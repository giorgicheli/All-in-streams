<?php

namespace Tests\Feature;

use App\Livewire\BriefWizard;
use App\Livewire\CreatorApplication;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        $this->seed();
    }

    private function user(string $role): User
    {
        return User::where('email', match ($role) {
            'admin' => 'admin@allinstreams.test', 'business' => 'brand@allinstreams.test', default => 'creator@allinstreams.test',
        })->firstOrFail();
    }

    public function test_approval_starts_matching_without_launching_a_campaign(): void
    {
        $brief = app(CampaignWorkflow::class)->transition(Brief::findOrFail(842), 'matching', $this->user('admin'));
        $this->assertSame('matching', $brief->status);
        $this->assertFalse($brief->payment_ready);
        $this->assertDatabaseHas('activities', ['brief_id' => 842, 'audience' => 'business', 'title' => 'Creator matching has started']);
    }

    public function test_business_cannot_approve_its_own_brief(): void
    {
        $this->expectException(HttpException::class);
        app(CampaignWorkflow::class)->transition(Brief::findOrFail(842), 'matching', $this->user('business'));
    }

    public function test_proposal_requires_a_creator_who_expressed_interest(): void
    {
        $this->expectException(ValidationException::class);
        app(CampaignWorkflow::class)->transition(Brief::findOrFail(831), 'proposal', $this->user('admin'));
    }

    public function test_uploaded_documents_and_payment_flag_do_not_satisfy_launch_requirements(): void
    {
        $brief = Brief::findOrFail(839);
        $workflow = app(CampaignWorkflow::class);
        $workflow->transition($brief, 'contracts', $this->user('admin'));
        $brief->refresh()->update(['payment_ready' => true]);
        CampaignDocument::create(['brief_id' => $brief->id, 'title' => 'Uploaded agreement', 'audience' => 'business', 'signature_status' => 'uploaded']);
        try {
            $workflow->transition($brief, 'live', $this->user('admin'));
            $this->fail('An uploaded document must not be treated as signed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('contracts', $brief->fresh()->status);
    }

    public function test_launch_requires_and_accepts_separate_signed_documents_for_all_parties(): void
    {
        $brief = Brief::findOrFail(839);
        $workflow = app(CampaignWorkflow::class);
        $workflow->transition($brief, 'contracts', $this->user('admin'));
        $brief->refresh()->update(['payment_ready' => true]);
        CampaignDocument::create(['brief_id' => $brief->id, 'title' => 'Business agreement', 'audience' => 'business', 'signature_status' => 'signed', 'file_path' => 'campaign-documents/demo-business-agreement.txt']);
        foreach ($brief->invitations as $invitation) {
            CampaignDocument::create(['brief_id' => $brief->id, 'creator_profile_id' => $invitation->creator_profile_id, 'title' => 'Creator agreement', 'audience' => 'creator', 'signature_status' => 'signed', 'file_path' => 'campaign-documents/demo-creator-agreement.txt']);
        }
        $this->assertSame('live', $workflow->transition($brief, 'live', $this->user('admin'))->status);
    }

    public function test_creator_response_cannot_launch_or_modify_business_terms(): void
    {
        $invitation = Invitation::where('brief_id', 831)->firstOrFail();
        $this->actingAs($this->user('creator'))->post(route('creator.respond', $invitation), ['status' => 'interested', 'compensation' => 1, 'brief_id' => 842])->assertRedirect();
        $this->assertSame('interested', $invitation->fresh()->status);
        $this->assertSame('2400.00', $invitation->fresh()->compensation);
        $this->assertSame('matching', $invitation->brief->fresh()->status);
    }

    public function test_creator_cannot_respond_to_another_creators_invitation(): void
    {
        $invitation = Invitation::where('brief_id', 839)->firstOrFail();
        $this->actingAs($this->user('creator'))->post(route('creator.respond', $invitation), ['status' => 'declined'])->assertForbidden();
        $this->assertSame('interested', $invitation->fresh()->status);
    }

    public function test_business_shortlist_hides_identity_and_private_terms_until_authorized(): void
    {
        $invitation = Invitation::with(['brief', 'creatorProfile'])->where('brief_id', 839)->firstOrFail();
        $safe = $invitation->forBusiness();
        $this->assertNull($safe['public_name']);
        $this->assertNull($safe['channel_url']);
        $this->assertArrayNotHasKey('rate', $safe);
        $this->assertArrayNotHasKey('compensation', $safe);
        $this->assertArrayNotHasKey('user', $safe);
        app(CampaignWorkflow::class)->introduce($invitation, $this->user('admin'));
        $released = $invitation->fresh()->forBusiness();
        $this->assertSame($invitation->creatorProfile->public_name, $released['public_name']);
        $this->assertArrayNotHasKey('compensation', $released);
    }

    public function test_documents_enforce_party_visibility_and_campaign_participation(): void
    {
        $businessDoc = CampaignDocument::where('audience', 'business')->firstOrFail();
        $creatorDoc = CampaignDocument::where('audience', 'creator')->firstOrFail();
        $this->actingAs($this->user('business'))->get(route('documents.download', $creatorDoc))->assertForbidden();
        $this->get(route('documents.download', $businessDoc))->assertOk();
        $this->actingAs($this->user('creator'))->get(route('documents.download', $businessDoc))->assertForbidden();
        $this->get(route('documents.download', $creatorDoc))->assertOk();
        $mismatched = CampaignDocument::create(['brief_id' => 842, 'creator_profile_id' => 24, 'title' => 'Incorrectly linked', 'audience' => 'creator', 'signature_status' => 'uploaded', 'file_path' => $creatorDoc->file_path]);
        $this->get(route('documents.download', $mismatched))->assertForbidden();
    }

    public function test_business_cannot_view_another_business_campaign(): void
    {
        $other = User::factory()->create(['role' => 'business']);
        $this->actingAs($other)->get('/campaigns/842')->assertForbidden();
        $this->actingAs($this->user('creator'))->get('/dashboard')->assertForbidden();
    }

    public function test_registration_cannot_create_an_admin(): void
    {
        $this->post('/register', ['name' => 'Imposter', 'email' => 'imposter@example.com', 'role' => 'admin', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'imposter@example.com']);
    }

    public function test_brief_wizard_validates_steps_and_submits_for_review_as_current_business(): void
    {
        $business = $this->user('business');
        $component = Livewire::actingAs($business)->test(BriefWizard::class)
            ->call('next')->assertHasErrors(['title'])->assertSet('step', 1)
            ->set('title', 'New creator campaign')->set('brand', 'Our brand')->call('next')->assertSet('step', 2)
            ->set('geos', ['Germany'])->call('next')->assertSet('step', 3)
            ->set('budget', '5000')->set('deliverables', '4 live streams')->set('start_date', now()->addDays(3)->toDateString())->set('end_date', now()->addDays(10)->toDateString())
            ->call('next')->assertSet('step', 4)->call('submit')->assertHasNoErrors();
        $brief = Brief::where('title', 'New creator campaign')->firstOrFail();
        $component->assertRedirect(route('campaigns.show', $brief));
        $this->assertSame($business->id, $brief->user_id);
        $this->assertSame('submitted', $brief->status);
        $this->assertFalse($brief->payment_ready);
    }

    public function test_creator_application_validates_channel_and_awaits_review(): void
    {
        $user = User::factory()->create(['name' => 'New Creator', 'role' => 'creator']);
        $component = Livewire::actingAs($user)->test(CreatorApplication::class)
            ->set('country', 'Germany')->call('next')->assertSet('step', 2)
            ->set('channel_url', 'javascript:alert(1)')->call('next')->assertHasErrors('channel_url')->assertSet('step', 2)
            ->set('channel_url', 'https://www.twitch.tv/new_creator')->call('next')->assertSet('step', 3)
            ->set('followers', '25000')->set('average_viewers', '700')->set('audience_geos', ['Germany'])->call('next')->assertSet('step', 4)
            ->set('rate', '500')->call('next')->assertSet('step', 5)->call('submit')->assertHasNoErrors()->assertRedirect(route('creator.opportunities'));
        $profile = CreatorProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $profile->status);
    }

    public function test_public_and_role_specific_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/register?role=creator'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->actingAs($this->user('business'));
        foreach (['/dashboard', '/campaigns', '/campaigns/842', '/campaigns/839', '/proposals', '/documents', '/briefs/create'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->actingAs($this->user('creator'));
        foreach (['/creator/opportunities', '/creator/apply', '/documents'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_rendered_business_proposals_do_not_leak_identity_or_creator_fees(): void
    {
        $invitation = Invitation::where('brief_id', 839)->firstOrFail();
        $invitation->creatorProfile->update(['public_name' => 'PRIVATE_CREATOR_92', 'channel_url' => 'https://example.com/PRIVATE_CHANNEL_92', 'rate' => 981234.56]);
        $invitation->update(['compensation' => 978765.43]);
        $this->actingAs($this->user('business'))->get('/proposals')->assertOk()->assertSee($invitation->creatorProfile->code)->assertDontSee('PRIVATE_CREATOR_92')->assertDontSee('PRIVATE_CHANNEL_92')->assertDontSee('981234')->assertDontSee('978765');
        app(CampaignWorkflow::class)->introduce($invitation, $this->user('admin'));
        $this->get('/proposals')->assertOk()->assertSee('PRIVATE_CREATOR_92')->assertSee('PRIVATE_CHANNEL_92')->assertDontSee('981234')->assertDontSee('978765');
    }

    public function test_creator_page_does_not_receive_or_render_business_budget_or_private_notes(): void
    {
        Brief::findOrFail(831)->update(['notes' => 'PRIVATE_BUSINESS_NOTE_67', 'budget' => 876543.21]);
        $response = $this->actingAs($this->user('creator'))->get('/creator/opportunities')->assertOk()->assertSee('2,400')->assertDontSee('PRIVATE_BUSINESS_NOTE_67')->assertDontSee('876543')->assertDontSee('876,543');
        $brief = $response->viewData('invitations')->firstWhere('brief_id', 831)->brief;
        $this->assertArrayNotHasKey('budget', $brief->getAttributes());
        $this->assertArrayNotHasKey('notes', $brief->getAttributes());
    }

    public function test_profile_changes_await_reapproval_before_publication_or_advancement(): void
    {
        $invitation = Invitation::where('brief_id', 839)->firstOrFail();
        $invitation->creatorProfile->update(['status' => 'pending', 'public_name' => 'UNREVIEWED_CREATOR_23', 'followers' => 987654321]);
        $this->actingAs($this->user('business'))->get('/proposals')->assertOk()->assertDontSee($invitation->creatorProfile->code)->assertDontSee('UNREVIEWED_CREATOR_23')->assertDontSee('987654321');
        try {
            app(CampaignWorkflow::class)->transition($invitation->brief, 'contracts', $this->user('admin'));
            $this->fail('A changed creator profile must be approved again.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('proposal', $invitation->brief->fresh()->status);
    }
}
