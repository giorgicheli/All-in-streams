<?php

namespace Tests\Feature;

use App\Livewire\BriefWizard;
use App\Livewire\CreatorApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerVerificationRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revocation_blocks_already_mounted_campaign_and_creator_forms(): void
    {
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        foreach (['business' => BriefWizard::class, 'creator' => CreatorApplication::class] as $role => $component) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            $form = Livewire::test($component);
            User::whereKey($user->id)->update(['partner_verification_status' => 'pending']);
            $form->call('submit')->assertForbidden();
        }
        $this->assertDatabaseCount('briefs', 0);
        $this->assertDatabaseCount('creator_profiles', 0);
    }

    public function test_pending_login_ignores_a_previously_saved_workspace_destination(): void
    {
        $user = User::factory()->pendingPartner()->create(['role' => 'business', 'password' => 'Pending123456!']);
        $this->withSession(['url.intended' => '/briefs/create'])
            ->post('/login', ['email' => $user->email, 'password' => 'Pending123456!'])
            ->assertRedirect('/account/pending');
    }
}
