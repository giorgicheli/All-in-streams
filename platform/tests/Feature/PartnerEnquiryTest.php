<?php

namespace Tests\Feature;

use App\Filament\Resources\PartnerEnquiryResource;
use App\Filament\Resources\PartnerEnquiryResource\Pages\ManagePartnerEnquiries;
use App\Models\PartnerEnquiry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerEnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('i', 32))]);
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function brand(array $changes = []): array
    {
        return array_replace([
            'submission_key' => (string) Str::uuid(), 'kind' => 'brand', 'name' => 'Brand Contact',
            'email' => 'brand@example.test', 'telegram' => '@brand_contact', 'website' => 'https://example.test',
            'audience_geos' => ['Canada', 'Portugal'], 'requirements' => 'Twitch streams in June.', 'age_confirmed' => '1',
        ], $changes);
    }

    private function creator(string $type = 'both', array $changes = []): array
    {
        $data = [
            'submission_key' => (string) Str::uuid(), 'kind' => 'creator', 'creator_type' => $type,
            'name' => 'Creator Contact', 'telegram' => 'https://t.me/creator_contact', 'age_confirmed' => '1',
            'audience_geos' => ['Canada', 'Kyrgyzstan'], 'currency' => 'EUR',
        ];
        if (in_array($type, ['streamer', 'both'], true)) $data += ['twitch_url' => 'https://twitch.tv/Creator_Test', 'stream_rate' => '500'];
        if (in_array($type, ['influencer', 'both'], true)) $data += ['instagram_url' => 'https://instagram.com/Creator.Test', 'story_rate' => '100.50', 'reel_rate' => '300'];
        return array_replace($data, $changes);
    }

    public function test_brand_submission_is_private_and_does_not_create_an_account_or_campaign(): void
    {
        $data = $this->brand(['status' => 'closed', 'staff_notes' => 'Forged staff notes', 'creator_type' => 'both', 'stream_rate' => '1', 'audience_geos' => 'canada, Portugal']);
        $this->post('/talk-to-us', $data)->assertRedirect('/talk-to-us?type=brand#success')->assertSessionHas('enquiry_submitted', true);
        $record = PartnerEnquiry::sole();
        $this->assertSame('new', $record->status);
        $this->assertNull($record->staff_notes);
        $this->assertNull($record->creator_type);
        $this->assertNull($record->stream_rate);
        $this->assertNull($record->budget);
        $this->assertSame(['Canada', 'Portugal'], $record->audience_geos);
        $this->assertNotNull($record->age_confirmed_at);
        $this->assertArrayNotHasKey('submission_key', $record->toArray());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
        $this->assertDatabaseCount('briefs', 0);
        foreach (['/', '/white'] as $url) $this->get($url)->assertOk()->assertDontSee('brand@example.test')->assertDontSee('@brand_contact');
    }

    public function test_agency_budget_and_scope_are_stored_without_inventing_campaign_terms(): void
    {
        $this->post('/talk-to-us', $this->brand(['kind' => 'agency', 'budget' => '2500.50', 'currency' => 'eur', 'streams' => 5, 'budget_scope' => 'per_stream']))
            ->assertRedirect('/talk-to-us?type=agency#success');
        $record = PartnerEnquiry::sole();
        $this->assertSame('agency', $record->kind);
        $this->assertSame('2500.50', $record->budget);
        $this->assertSame('EUR', $record->currency);
        $this->assertSame(5, $record->streams);
        $this->assertSame('per_stream', $record->budget_scope);
        $this->assertDatabaseCount('briefs', 0);
    }

    public function test_all_creator_types_apply_their_own_profile_links_and_rates(): void
    {
        foreach (['streamer', 'influencer', 'both'] as $type) {
            $this->post('/talk-to-us', $this->creator($type))->assertRedirect('/talk-to-us?type=creator#success');
            $record = PartnerEnquiry::latest('id')->firstOrFail();
            $this->assertSame($type, $record->creator_type);
            $this->assertSame('@creator_contact', $record->telegram);
            $this->assertNull($record->email);
            $this->assertSame(['Canada', 'Kyrgyzstan'], $record->audience_geos);
            $this->assertSame($type === 'influencer' ? null : 'https://www.twitch.tv/creator_test', $record->twitch_url);
            $this->assertSame($type === 'streamer' ? null : 'https://www.instagram.com/creator.test', $record->instagram_url);
            $this->assertSame($type === 'influencer' ? null : '500.00', $record->stream_rate);
            $this->assertSame($type === 'streamer' ? null : '100.50', $record->story_rate);
        }
        $this->assertDatabaseCount('partner_enquiries', 3);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_server_rejects_missing_conditional_fields_and_invalid_links_countries_money_or_age(): void
    {
        $invalid = [
            [$this->brand(['age_confirmed' => false]), 'age_confirmed'],
            [$this->brand(['website' => 'javascript:alert(1)']), 'website'],
            [$this->brand(['website' => 'https://user:password@example.test']), 'website'],
            [$this->brand(['email' => null]), 'email'],
            [$this->brand(['telegram' => 'not valid']), 'telegram'],
            [$this->brand(['audience_geos' => ['Private company name']]), 'audience_geos.0'],
            [$this->brand(['budget' => 500, 'currency' => null]), 'currency'],
            [$this->brand(['kind' => 'agency']), 'streams'],
            [$this->brand(['kind' => 'agency', 'budget' => 500, 'currency' => 'EUR', 'streams' => 5, 'budget_scope' => 'other']), 'budget_scope'],
            [$this->creator('streamer', ['twitch_url' => null]), 'twitch_url'],
            [$this->creator('both', ['instagram_url' => 'https://instagram.com/p/private']), 'instagram_url'],
            [$this->creator('influencer', ['story_rate' => null]), 'story_rate'],
            [$this->creator('both', ['stream_rate' => -1]), 'stream_rate'],
            [$this->creator('both', ['reel_rate' => '10.001']), 'reel_rate'],
            [$this->creator('both', ['currency' => 'BTC']), 'currency'],
            [$this->creator('both', ['audience_geos' => ['Canada']]), 'audience_geos'],
            [$this->creator('both', ['audience_geos' => ['Canada', 'Canada']]), 'audience_geos.0'],
            [$this->brand(['requirements' => '<script>alert(1)</script>']), 'requirements'],
        ];
        foreach ($invalid as [$data, $field]) $this->postJson('/talk-to-us', $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('partner_enquiries', 0);
    }

    public function test_duplicate_submissions_do_not_create_duplicates_or_overwrite_the_original(): void
    {
        $data = $this->brand();
        $this->post('/talk-to-us', $data)->assertRedirect();
        $this->post('/talk-to-us', array_replace($data, ['name' => 'A different submission', 'email' => 'different@example.test']))->assertRedirect();
        $this->assertDatabaseCount('partner_enquiries', 1);
        $this->assertSame('Brand Contact', PartnerEnquiry::sole()->name);
    }

    public function test_only_admins_can_review_or_delete_an_enquiry_and_submitted_details_stay_immutable(): void
    {
        $this->post('/talk-to-us', $this->brand())->assertRedirect();
        $record = PartnerEnquiry::sole();
        foreach (['business', 'creator', 'agency'] as $role) $this->actingAs(User::factory()->create(['role' => $role]))->get(PartnerEnquiryResource::getUrl())->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get(PartnerEnquiryResource::getUrl())->assertOk()->assertSee('Brand Contact');
        Livewire::test(ManagePartnerEnquiries::class)->callTableAction('edit', $record, data: ['status' => 'in_review', 'staff_notes' => 'Contact privately.', 'name' => 'Forged replacement'])
            ->assertHasNoActionErrors();
        $this->assertSame('in_review', $record->fresh()->status);
        $this->assertSame('Contact privately.', $record->fresh()->staff_notes);
        $this->assertSame('Brand Contact', $record->fresh()->name);
        Livewire::test(ManagePartnerEnquiries::class)->callTableAction('delete', $record)->assertHasNoActionErrors();
        $this->assertDatabaseCount('partner_enquiries', 0);
    }
}
