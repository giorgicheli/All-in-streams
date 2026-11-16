<?php

namespace Tests\Feature;

use App\Filament\Resources\UserAccountResource\Pages\ListUserAccounts;
use App\Http\Middleware\EnsurePartnerVerified;
use App\Models\User;
use App\Services\PartnerVerification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PartnerVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
    }

    public function test_new_partner_accounts_start_pending_and_only_staff_can_approve_them(): void
    {
        $partner = User::create(['name' => 'New partner', 'email' => 'new@example.test', 'password' => 'A-strong-password-245', 'role' => 'business']);
        $this->assertSame('pending', $partner->partner_verification_status);
        $this->assertFalse($partner->hasVerifiedPartnerAccount());
        $admin = User::factory()->create(['role' => 'admin']);
        $service = app(PartnerVerification::class);
        foreach (['business', 'agency', 'creator'] as $role) {
            try {
                $service->verify(User::factory()->create(['role' => $role]), $partner);
                $this->fail('A partner approved an account');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $approved = $service->verify($admin, $partner);
        $this->assertTrue($approved->hasVerifiedPartnerAccount());
        $this->assertSame($admin->id, $approved->partner_verified_by);
        $this->assertNotNull($approved->partner_verified_at);
        $this->assertSame('pending', $service->revoke($admin, $approved)->partner_verification_status);
        $rejected = $service->reject($admin, $approved);
        $this->assertSame('rejected', $rejected->partner_verification_status);
        $this->assertNull($rejected->partner_verified_at);
        $this->assertFalse($rejected->hasVerifiedPartnerAccount());
        $this->assertTrue($service->verify($admin, $rejected)->hasVerifiedPartnerAccount());
    }

    public function test_administrators_are_implicitly_verified_and_cannot_revoke_their_own_access(): void
    {
        $admin = User::factory()->pendingPartner()->create(['role' => 'admin']);
        $this->assertTrue($admin->hasVerifiedPartnerAccount());
        $this->expectException(ValidationException::class);
        app(PartnerVerification::class)->revoke($admin, $admin);
    }

    public function test_pending_and_rejected_accounts_are_redirected_from_role_workspaces(): void
    {
        foreach (['pending', 'rejected'] as $status) {
            foreach (['business' => '/dashboard', 'agency' => '/agency/dashboard', 'creator' => '/creator/opportunities'] as $role => $path) {
                $user = User::factory()->pendingPartner()->create(['role' => $role, 'partner_verification_status' => $status]);
                $this->actingAs($user)->get($path)->assertRedirect('/account/pending');
                $this->get('/account/pending')->assertOk()->assertSee($status === 'rejected' ? 'Your account has not been approved.' : 'Your account is awaiting verification.');
            }
        }
        $this->actingAs(User::factory()->pendingPartner()->create(['role' => 'creator']))->get('/dashboard')->assertForbidden();
    }

    public function test_settings_cannot_self_approve_and_verified_middleware_guards_provider_operations(): void
    {
        $user = User::factory()->pendingPartner()->create(['role' => 'creator']);
        $this->actingAs($user)->post('/account/settings', ['telegram' => '@valid_creator', 'partner_verification_status' => 'verified', 'partner_verified_by' => $user->id])
            ->assertRedirect('/account/settings');
        $this->assertSame('pending', $user->fresh()->partner_verification_status);
        $this->assertNull($user->fresh()->partner_verified_by);
        $request = Request::create('/provider/deliveries', 'POST');
        $request->setUserResolver(fn () => $user->fresh());
        $response = app(EnsurePartnerVerified::class)->handle($request, fn () => response('MUTATED'));
        $this->assertTrue($response->isRedirect(route('account.pending')));
        $this->get('/provider/deliveries')->assertRedirect('/account/pending');
        $this->get('/documents')->assertRedirect('/account/pending');
    }

    public function test_staff_can_verify_reject_and_revoke_from_the_account_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = User::factory()->pendingPartner()->create(['role' => 'agency']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListUserAccounts::class)->callTableAction('verifyPartner', $partner)->assertHasNoActionErrors()
            ->assertTableColumnStateSet('partner_verification_status', 'verified', $partner);
        $this->assertTrue($partner->fresh()->hasVerifiedPartnerAccount());
        Livewire::test(ListUserAccounts::class)->callTableAction('revokePartner', $partner->fresh())->assertHasNoActionErrors()
            ->assertTableColumnStateSet('partner_verification_status', 'pending', $partner);
        $this->assertSame('pending', $partner->fresh()->partner_verification_status);
        Livewire::test(ListUserAccounts::class)->callTableAction('rejectPartner', $partner->fresh())->assertHasNoActionErrors()
            ->assertTableColumnStateSet('partner_verification_status', 'rejected', $partner);
        $this->assertSame('rejected', $partner->fresh()->partner_verification_status);
        $this->assertNull($partner->fresh()->partner_verified_by);
    }
}
