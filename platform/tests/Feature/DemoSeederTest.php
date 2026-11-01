<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\AgencyProfile;
use App\Models\AgencyTalent;
use App\Models\Brief;
use App\Models\Invitation;
use App\Models\User;
use LogicException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_fixtures_keep_agencies_separate_and_active_offers_explicitly_selected(): void
    {
        $this->seed();
        $agency = User::where('email', 'agency@allinstreams.test')->sole();
        $this->assertSame('agency', $agency->role);
        $this->assertSame('approved', $agency->agencyProfile->status);
        $this->assertSame(1, $agency->agencyTalent()->where('status', 'approved')->count());
        $this->assertDatabaseCount('agency_profiles', 1);
        $this->assertDatabaseCount('agency_talents', 1);
        foreach (Brief::whereIn('status', ['contracts','live','completed'])->get() as $brief) {
            $selected = $brief->invitations()->where('selection_status','selected')->sole();
            $this->assertSame('interested', $selected->status);
            $this->assertSame($selected->offer_version, $selected->accepted_offer_version);
            $this->assertNotNull($selected->selected_at);
        }
        foreach (Invitation::all() as $invitation) {
            $this->assertNotEmpty($invitation->offer_details);
            $this->assertSame('EUR', $invitation->currency);
        }
    }

    public function test_production_cannot_create_accounts_with_known_demo_credentials(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->app->make(\Database\Seeders\DatabaseSeeder::class)->run();
            $this->fail('Production must reject the demo seeder before creating accounts.');
        } catch (LogicException $exception) {
            $this->assertSame('Demo data may only be seeded in local or testing environments.', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertDatabaseCount('users', 0);
    }
}
