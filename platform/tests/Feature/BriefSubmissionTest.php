<?php

namespace Tests\Feature;

use App\Livewire\BriefWizard;
use App\Models\AgencyProfile;
use App\Models\Brief;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BriefSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Storage::fake('local');
    }

    private function details(): array
    {
        return ['title' => 'Private spring launch', 'brand' => 'Private Brand', 'vertical' => 'Gaming', 'geos' => ['Germany'], 'language' => 'German', 'platform' => 'Twitch', 'provider_type' => 'agency', 'budget' => '18000', 'currency' => 'EUR', 'commercial_model' => 'Fixed + CPA', 'deliverables' => 'Four live streams and two VODs', 'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(20)->toDateString(), 'kpis' => 'Private goals', 'notes' => 'Private contacts'];
    }

    public function test_company_submits_provider_preference_and_private_pdf_only_owner_and_staff_can_download(): void
    {
        $client = User::factory()->create(['role' => 'business']);
        Livewire::actingAs($client)->test(BriefWizard::class)->set($this->details())
            ->set('attachment', UploadedFile::fake()->create('brief.pdf', 4, 'application/pdf'))
            ->call('submit')->assertHasNoErrors()->assertRedirect(route('campaigns.show', Brief::firstOrFail()));
        $brief = Brief::firstOrFail();
        $this->assertSame('agency', $brief->provider_type);
        $this->assertSame('submitted', $brief->status);
        Storage::disk('local')->assertExists($brief->attachment_path);
        $this->actingAs($client)->get(route('briefs.attachment', $brief))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'creator']))->get(route('briefs.attachment', $brief))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'business']))->get(route('briefs.attachment', $brief))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('briefs.attachment', $brief))->assertOk();
    }

    public function test_request_agency_can_submit_its_client_brief_while_supply_only_agency_cannot(): void
    {
        $agency = User::factory()->create(['role' => 'agency', 'account_type' => 'agency']);
        AgencyProfile::create(['user_id' => $agency->id, 'services' => ['request'], 'languages' => ['German'], 'audience_geos' => ['Germany'], 'platforms' => ['Twitch'], 'status' => 'pending']);
        Livewire::actingAs($agency)->test(BriefWizard::class)->set($this->details())->call('submit')->assertHasNoErrors()->assertRedirect(route('agency.campaigns.show', Brief::firstOrFail()));
        $this->actingAs($agency)->get('/agency/campaigns')->assertOk()->assertSee('/agency/campaigns/'.Brief::firstOrFail()->id);
        $agency->agencyProfile->update(['services' => ['supply']]);
        $this->actingAs($agency)->get('/agency/briefs/create')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'creator']))->get('/agency/campaigns')->assertForbidden();
    }

    public function test_requested_changes_can_be_corrected_and_resubmitted_only_by_owner(): void
    {
        $owner = User::factory()->create(['role' => 'business']);
        $brief = Brief::create($this->details() + ['user_id' => $owner->id, 'status' => 'changes_requested', 'payment_ready' => true]);
        $this->actingAs(User::factory()->create(['role' => 'business']))->get(route('briefs.edit', $brief))->assertForbidden();
        Livewire::actingAs($owner)->test(BriefWizard::class, ['brief' => $brief])->set('budget', '20000')->call('submit')->assertHasNoErrors();
        $this->assertSame('submitted', $brief->fresh()->status);
        $this->assertSame('20000.00', $brief->fresh()->budget);
        $this->assertFalse($brief->fresh()->payment_ready);
        $this->assertSame(1, Brief::count());
        $this->actingAs($owner)->get(route('briefs.edit', $brief))->assertForbidden();
    }
}
