<?php

namespace Tests\Feature;

use App\Filament\Resources\CreatorShowcaseResource;
use App\Filament\Resources\CreatorShowcaseResource\Pages\ManageCreatorShowcases;
use App\Models\CreatorShowcase;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    private function staff(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $admin;
    }

    private function profileData(array $overrides = []): array
    {
        return array_replace([
            'internal_title' => 'PRIVATE_STAFF_REFERENCE_441',
            'region' => 'Europe',
            'country' => 'Germany',
            'creator_type' => 'streamer',
            'platforms' => ['Twitch', 'Kick'],
            'language' => 'German',
            'image_theme' => 'gaming-studio',
            'followers' => 48200,
            'average_views' => 1200,
            'sort_order' => 0,
            'is_demo' => true,
            'is_verified' => true,
            'is_published' => true,
        ], $overrides);
    }

    public function test_public_profiles_use_an_explicit_identity_free_dto_and_reviewed_scope(): void
    {
        $this->staff();
        $shown = CreatorShowcase::create($this->profileData());
        CreatorShowcase::create($this->profileData(['country' => 'France', 'is_published' => false]));
        CreatorShowcase::create($this->profileData(['country' => 'Poland', 'is_verified' => false, 'is_published' => false]));
        $private = User::factory()->create(['role' => 'creator', 'name' => 'PRIVATE_PERSON_441', 'telegram' => '@PRIVATE_CONTACT_441']);
        $public = CreatorShowcase::published()->get()->map->publicData();

        $this->assertCount(1, $public);
        $this->assertTrue(Str::isUuid($shown->public_id));
        $this->assertSame($shown->public_id, $public->first()['public_id']);
        $this->assertSame([
            'public_id', 'region', 'country', 'creator_type', 'type_label', 'caption',
            'platforms', 'language', 'image_url', 'image_alt', 'followers', 'average_views', 'is_demo',
        ], array_keys($public->first()));
        foreach (['user_id', 'creator_profile_id', 'name', 'email', 'telegram', 'contact', 'profile_url', 'rate', 'fee'] as $field) {
            $this->assertFalse(Schema::hasColumn('creator_showcases', $field));
        }
        $html = view('site.blocks.creator-network', ['creatorShowcases' => $public, 'data' => $this->settings()])->render();
        $this->assertStringContainsString('Demo profile', $html);
        $this->assertStringContainsString('Protected identity', $html);
        foreach ([$shown->internal_title, $private->name, $private->email, $private->telegram] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
            $this->assertStringNotContainsString($secret, json_encode($public));
        }
    }

    public function test_invalid_public_facts_and_unreviewed_publication_are_rejected(): void
    {
        $this->staff();
        foreach ([
            ['is_verified' => false], ['country' => 'PRIVATE_COMPANY_912'],
            ['platforms' => ['https://twitch.tv/private']], ['creator_type' => 'PRIVATE_PERSON'],
            ['platforms' => ['Instagram']], ['creator_type' => 'influencer'],
            ['region' => 'North America'], ['followers' => -1], ['average_views' => -1],
            ['language' => 'private@example.test'], ['image_theme' => 'https://private.example/photo.jpg'],
        ] as $invalid) {
            try {
                CreatorShowcase::create($this->profileData($invalid));
                $this->fail('An invalid public card was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('creator_showcases', 0);
    }

    public function test_staff_can_manage_public_cards_and_other_roles_cannot_publish_them(): void
    {
        $this->staff();
        Livewire::test(ManageCreatorShowcases::class)
            ->callAction('create', data: $this->profileData())->assertHasNoActionErrors();
        $record = CreatorShowcase::sole();
        $this->assertNotNull($record->reviewed_at);
        Livewire::test(ManageCreatorShowcases::class)
            ->callTableAction('edit', $record, data: $this->profileData(['followers' => 51000]))->assertHasNoActionErrors();
        $this->assertSame(51000, $record->fresh()->followers);
        Livewire::test(ManageCreatorShowcases::class)
            ->callTableAction('unpublish', $record)->assertHasNoActionErrors();
        $this->assertSame(0, CreatorShowcase::published()->count());
        foreach (['business', 'creator', 'agency'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(CreatorShowcaseResource::getUrl())->assertForbidden();
        }
        try {
            $record->fresh()->update(['is_published' => true]);
            $this->fail('A partner published a reviewed card.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_published', $exception->errors());
        }
        $this->staff();
        Livewire::test(ManageCreatorShowcases::class)
            ->callTableAction('delete', $record)->assertHasNoActionErrors();
        $this->assertDatabaseCount('creator_showcases', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_seed_publishes_three_mixed_demo_profiles_per_region_and_preserves_staff_edits(): void
    {
        $this->staff();
        $this->artisan('showcase:creators')->assertSuccessful();
        $this->assertDatabaseCount('creator_showcases', 24);
        foreach (array_keys(CreatorShowcase::COUNTRIES_BY_REGION) as $region) {
            $profiles = CreatorShowcase::published()->where('region', $region)->get();
            $this->assertCount(3, $profiles);
            $this->assertEqualsCanonicalizing(['streamer', 'influencer', 'both'], $profiles->pluck('creator_type')->all());
            $this->assertTrue($profiles->every(fn ($profile) => $profile->is_demo));
        }
        $record = CreatorShowcase::where('country', 'Germany')->sole();
        $record->update(['followers' => 49999, 'is_published' => false]);
        $this->artisan('showcase:creators')->assertSuccessful();
        $this->assertDatabaseCount('creator_showcases', 24);
        $this->assertSame(49999, $record->fresh()->followers);
        $this->assertFalse($record->fresh()->is_published);
    }

    public function test_gallery_limits_each_region_to_three_and_shows_europe_first(): void
    {
        $this->staff();
        $this->artisan('showcase:creators')->assertSuccessful();
        CreatorShowcase::create($this->profileData(['sort_order' => 100]));
        $html = view('site.blocks.creator-network', [
            'creatorShowcases' => CreatorShowcase::published()->orderBy('sort_order')->get()->map->publicData(),
            'data' => $this->settings(),
        ])->render();
        $this->assertSame(24, substr_count($html, 'data-public-profile='));
        $this->assertSame(8, substr_count($html, 'data-creator-region='));
        $this->assertStringContainsString('data-creator-panel="Europe" >', $html);
        $this->assertStringContainsString('data-creator-panel="CIS"  hidden ', $html);
        $this->assertStringNotContainsString('All regions', $html);
    }

    private function settings(): array
    {
        return [
            'eyebrow' => 'Discover your next match',
            'title_line_1' => 'Different voices.',
            'title_line_2' => 'The right influence.',
            'description' => 'Discover creators by audience region.',
            'request_label' => 'Request more profiles',
            'request_url' => '/talk-to-us?type=brand',
        ];
    }
}
