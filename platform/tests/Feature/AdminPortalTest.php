<?php

namespace Tests\Feature;

use App\Filament\Resources\BriefResource;
use App\Filament\Resources\BriefResource\Pages\ManageBriefs;
use App\Filament\Resources\CampaignDocumentResource;
use App\Filament\Resources\CampaignDocumentResource\Pages\ManageDocuments;
use App\Filament\Resources\CreatorProfileResource;
use App\Filament\Resources\CreatorProfileResource\Pages\ManageCreators;
use App\Filament\Resources\InvitationResource;
use App\Models\Brief;
use App\Models\CreatorProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_only_staff_can_access_every_admin_resource(): void
    {
        $business = User::factory()->create(['role' => 'business']);
        $creator = User::factory()->create(['role' => 'creator']);
        $agency = User::factory()->create(['role' => 'agency']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([$business, $creator, $agency] as $user) {
            foreach (['/admin', BriefResource::getUrl(), CreatorProfileResource::getUrl(), InvitationResource::getUrl(), CampaignDocumentResource::getUrl()] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }

        foreach (['/admin', BriefResource::getUrl(), CreatorProfileResource::getUrl(), InvitationResource::getUrl(), CampaignDocumentResource::getUrl()] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_staff_can_approve_and_invite_without_launching_a_campaign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $brief = $this->brief();
        $creator = $this->creator();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageBriefs::class)->callTableAction('approve', $brief)->assertHasNoActionErrors();
        $this->assertSame('matching', $brief->refresh()->status);

        Livewire::test(ManageCreators::class)->callTableAction('invite', $creator, data: [
            'brief_id' => $brief->id,
            'compensation' => 2400,
            'deliverables' => '4 live streams',
            'notes' => 'Tracking links required.',
        ])->assertHasNoActionErrors();

        $this->assertDatabaseHas('invitations', ['brief_id' => $brief->id, 'creator_profile_id' => $creator->id, 'status' => 'pending', 'compensation' => 2400]);
        $this->assertSame('matching', $brief->refresh()->status);
    }

    public function test_upload_is_private_and_does_not_mark_a_document_signed(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $brief = $this->brief();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageDocuments::class)->callAction('create', data: [
            'title' => 'Business agreement',
            'brief_id' => $brief->id,
            'audience' => 'business',
            'file_path' => UploadedFile::fake()->create('agreement.pdf', 20, 'application/pdf'),
        ])->assertHasNoActionErrors();

        $document = $brief->documents()->sole();
        $this->assertSame('uploaded', $document->signature_status);
        Storage::disk('local')->assertExists($document->file_path);
        $this->actingAs(User::factory()->create(['role' => 'business']))->get('/documents/'.$document->id.'/download')->assertForbidden();
    }

    public function test_launch_action_explains_missing_agreements_and_keeps_campaign_in_contracts(): void
    {
        $brief = $this->brief();
        $brief->update(['status' => 'contracts']);
        $brief->invitations()->create(['creator_profile_id' => $this->creator()->id, 'status' => 'interested', 'selection_status' => 'selected', 'offer_version' => 1, 'accepted_offer_version' => 1, 'compensation' => 2400, 'currency' => 'EUR', 'deliverables' => '4 streams']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageBriefs::class)->callTableAction('advance', $brief)->assertNotified('Campaign cannot advance yet');

        $this->assertSame('contracts', $brief->refresh()->status);
    }

    private function brief(): Brief
    {
        return Brief::create([
            'user_id' => User::factory()->create(['role' => 'business'])->id,
            'title' => 'DACH creator campaign', 'brand' => 'Brand One', 'vertical' => 'Gaming',
            'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch',
            'budget' => 18000, 'currency' => 'EUR', 'commercial_model' => 'Fixed + CPA',
            'deliverables' => '4 live streams', 'start_date' => '2026-12-01', 'end_date' => '2026-12-30',
            'status' => 'submitted',
        ]);
    }

    private function creator(): CreatorProfile
    {
        return CreatorProfile::create([
            'user_id' => User::factory()->create(['role' => 'creator'])->id,
            'public_name' => 'Stream Nova', 'country' => 'Germany', 'language' => 'German',
            'platform' => 'Twitch', 'channel_url' => 'https://twitch.tv/stream-nova',
            'followers' => 48000, 'average_viewers' => 1200, 'audience_geos' => ['Germany'],
            'vertical' => 'Gaming', 'rate' => 750, 'commercial_model' => 'Fixed + CPA',
            'availability' => 'Open', 'status' => 'approved',
        ]);
    }
}
