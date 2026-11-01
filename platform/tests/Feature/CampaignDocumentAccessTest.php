<?php

namespace Tests\Feature;

use App\Filament\Resources\CampaignDocumentResource\Pages\ManageDocuments;
use App\Models\Brief;
use App\Models\CampaignDocument;
use App\Models\CreatorProfile;
use App\Models\Invitation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        Storage::fake('local');
    }

    private function brief(User $owner): Brief
    {
        return Brief::create([
            'user_id' => $owner->id, 'title' => 'Private company campaign', 'brand' => 'Private Company',
            'vertical' => 'Gaming', 'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch',
            'budget' => 18000, 'currency' => 'EUR', 'commercial_model' => 'Fixed', 'deliverables' => '4 live streams',
            'start_date' => today(), 'end_date' => today()->addDays(10), 'status' => 'contracts',
        ]);
    }

    private function provider(Brief $brief, string $role = 'creator', bool $selected = true): array
    {
        $user = User::factory()->create(['role' => $role, 'company' => $role === 'agency' ? 'Talent Agency' : null]);
        $profile = $role === 'creator' ? CreatorProfile::create([
            'user_id' => $user->id, 'public_name' => 'Creator '.$user->id, 'country' => 'Germany', 'language' => 'German', 'platform' => 'Twitch',
            'channel_url' => 'https://twitch.tv/creator'.$user->id, 'audience_geos' => ['Germany'], 'vertical' => 'Gaming',
            'rate' => 750, 'commercial_model' => 'Fixed', 'status' => 'approved',
        ]) : null;
        $invitation = Invitation::create([
            'brief_id' => $brief->id, 'creator_profile_id' => $profile?->id, 'agency_user_id' => $role === 'agency' ? $user->id : null,
            'status' => 'interested', 'selection_status' => $selected ? 'selected' : 'not_selected',
            'offer_version' => 1, 'accepted_offer_version' => 1, 'compensation' => 2400, 'currency' => 'EUR', 'deliverables' => '4 streams',
        ]);

        return [$user, $profile, $invitation];
    }

    private function staff(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    public function test_staff_uploads_only_the_selected_creator_or_agency_provider_agreement(): void
    {
        foreach (['creator', 'agency'] as $role) {
            $brief = $this->brief(User::factory()->create(['role' => 'business']));
            [$selectedUser, $selectedProfile] = $this->provider($brief, $role);
            [$otherUser, $otherProfile] = $this->provider($brief, $role, false);
            $field = $role === 'creator' ? 'creator_profile_id' : 'agency_user_id';
            $this->staff();
            $data = ['title' => 'Provider service agreement', 'brief_id' => $brief->id, 'audience' => $role, 'file_path' => UploadedFile::fake()->create('agreement.pdf', 20, 'application/pdf')];

            Livewire::test(ManageDocuments::class)->callAction('create', data: array_merge($data, [$field => $role === 'creator' ? $otherProfile->id : $otherUser->id]))->assertHasActionErrors([$field]);
            Livewire::test(ManageDocuments::class)->callAction('create', data: array_merge($data, [$field => $role === 'creator' ? $selectedProfile->id : $selectedUser->id]))->assertHasNoActionErrors();

            $document = $brief->documents()->sole();
            $this->assertSame($role, $document->audience);
            $this->assertSame('uploaded', $document->signature_status);
            $this->assertSame($role === 'creator' ? $selectedProfile->id : $selectedUser->id, $document->{$field});
            Storage::disk('local')->assertExists($document->file_path);
        }
    }

    public function test_provider_document_requires_acceptance_of_the_current_offer_and_same_campaign(): void
    {
        $brief = $this->brief(User::factory()->create(['role' => 'business']));
        [$provider, $profile, $invitation] = $this->provider($brief);
        $otherBrief = $this->brief(User::factory()->create(['role' => 'business']));
        $this->staff();
        $data = ['title' => 'Service agreement', 'brief_id' => $otherBrief->id, 'audience' => 'creator', 'creator_profile_id' => $profile->id, 'file_path' => UploadedFile::fake()->create('agreement.pdf', 20, 'application/pdf')];
        Livewire::test(ManageDocuments::class)->callAction('create', data: $data)->assertHasActionErrors(['creator_profile_id']);
        $invitation->update(['accepted_offer_version' => null]);
        $data['brief_id'] = $brief->id;
        Livewire::test(ManageDocuments::class)->callAction('create', data: $data)->assertHasActionErrors(['creator_profile_id']);
        $this->assertDatabaseCount('campaign_documents', 0);
    }

    public function test_agency_provider_and_creator_cannot_download_business_or_other_provider_terms(): void
    {
        foreach (['creator', 'agency'] as $role) {
            $owner = User::factory()->create(['role' => 'business']);
            $brief = $this->brief($owner);
            [$provider, $profile] = $this->provider($brief, $role);
            [$otherProvider] = $this->provider($brief, $role, false);
            Storage::disk('local')->put('campaign-documents/business.pdf', 'Private business terms');
            Storage::disk('local')->put('campaign-documents/provider.pdf', 'Own provider terms');
            $businessDocument = CampaignDocument::create(['brief_id' => $brief->id, 'title' => 'Business private agreement', 'audience' => 'business', 'signature_status' => 'uploaded', 'file_path' => 'campaign-documents/business.pdf']);
            $providerDocument = CampaignDocument::create(['brief_id' => $brief->id, 'title' => 'Provider service agreement', 'audience' => $role, 'creator_profile_id' => $profile?->id, 'agency_user_id' => $role === 'agency' ? $provider->id : null, 'signature_status' => 'uploaded', 'file_path' => 'campaign-documents/provider.pdf']);

            $this->actingAs($provider)->get(route('documents.download', $providerDocument))->assertDownload();
            $this->get(route('documents.download', $businessDocument))->assertForbidden();
            $this->actingAs($owner)->get(route('documents.download', $providerDocument))->assertForbidden();
            $this->get(route('documents.download', $businessDocument))->assertDownload();
            $this->actingAs($otherProvider)->get(route('documents.download', $providerDocument))->assertForbidden();
        }
    }

    public function test_switching_provider_document_to_business_clears_provider_identifiers(): void
    {
        foreach (['creator', 'agency'] as $role) {
            $brief = $this->brief(User::factory()->create(['role' => 'business']));
            [$provider, $profile] = $this->provider($brief, $role);
            Storage::disk('local')->put('campaign-documents/provider.pdf', 'Provider terms');
            $document = CampaignDocument::create(['brief_id' => $brief->id, 'title' => 'Provider service agreement', 'audience' => $role, 'creator_profile_id' => $profile?->id, 'agency_user_id' => $role === 'agency' ? $provider->id : null, 'signature_status' => 'uploaded', 'file_path' => 'campaign-documents/provider.pdf']);
            $this->staff();
            Livewire::test(ManageDocuments::class)->callTableAction('edit', $document, data: [
                'title' => 'Business agreement', 'brief_id' => $brief->id, 'audience' => 'business',
            ])->assertHasNoActionErrors();
            $this->assertNull($document->fresh()->creator_profile_id);
            $this->assertNull($document->fresh()->agency_user_id);
            $this->assertSame('business', $document->fresh()->audience);
        }
    }
}
