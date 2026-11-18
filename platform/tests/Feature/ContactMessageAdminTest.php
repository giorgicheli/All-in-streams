<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\ContactMessageResource\Pages\ManageContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ContactMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32))]);
        $this->withoutVite();
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function message(array $overrides = []): ContactMessage
    {
        return ContactMessage::query()->forceCreate(array_replace([
            'name' => 'Contact Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'Private campaign question',
            'message' => "Please contact me about a campaign.\nMy audience is in Canada.",
            'status' => 'new',
            'staff_notes' => null,
            'submission_key' => hash('sha256', (string) Str::uuid()),
        ], $overrides));
    }

    public function test_contact_inbox_and_actions_are_available_only_to_administrators(): void
    {
        $record = $this->message();
        $this->get(ContactMessageResource::getUrl())->assertRedirect('/admin/login');
        $this->assertNull(ContactMessageResource::getNavigationBadge());

        foreach (['business', 'agency', 'creator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(ContactMessageResource::getUrl())->assertForbidden();
            $this->assertFalse(ContactMessageResource::canViewAny());
            $this->assertFalse(ContactMessageResource::canView($record));
            $this->assertFalse(ContactMessageResource::canEdit($record));
            $this->assertFalse(ContactMessageResource::canDelete($record));
            $this->assertNull(ContactMessageResource::getNavigationBadge());
        }

        $this->staff();
        $this->get(ContactMessageResource::getUrl())->assertOk()->assertSee('Contact messages');
        $this->assertTrue(ContactMessageResource::canView($record));
        $this->assertTrue(ContactMessageResource::canEdit($record));
        $this->assertTrue(ContactMessageResource::canDelete($record));
        $this->assertFalse(ContactMessageResource::canCreate());
        Livewire::test(ManageContactMessages::class)->assertCanSeeTableRecords([$record]);
    }

    public function test_review_only_updates_staff_fields_and_keeps_existing_hidden_notes(): void
    {
        Mail::fake();
        Notification::fake();
        $this->staff();
        $record = $this->message();
        $original = $record->only(['name', 'email', 'subject', 'message', 'submission_key']);
        $receivedAt = $record->created_at->toISOString();
        $this->message();
        $this->message(['status' => 'closed']);
        $this->assertSame('2', ContactMessageResource::getNavigationBadge());

        Livewire::test(ManageContactMessages::class)
            ->callTableAction('edit', $record, data: [
                'status' => 'replied',
                'staff_notes' => 'Replied separately; follow up next week.',
                'name' => 'Forged sender',
                'email' => 'forged@example.test',
                'subject' => 'Forged subject',
                'message' => 'Forged message',
                'submission_key' => str_repeat('x', 64),
                'created_at' => '2000-01-01 00:00:00',
            ])->assertHasNoActionErrors();

        $record->refresh();
        $this->assertSame($original, $record->only(array_keys($original)));
        $this->assertSame($receivedAt, $record->created_at->toISOString());
        $this->assertSame('replied', $record->status);
        $this->assertSame('Replied separately; follow up next week.', $record->staff_notes);
        $this->assertSame('1', ContactMessageResource::getNavigationBadge());

        Livewire::test(ManageContactMessages::class)->mountTableAction('edit', $record)
            ->assertTableActionDataSet([
                'status' => 'replied',
                'staff_notes' => 'Replied separately; follow up next week.',
            ])->unmountTableAction();

        // A separate edit must also prefill private notes hidden by model serialization.
        Livewire::test(ManageContactMessages::class)
            ->callTableAction('edit', $record, data: ['status' => 'in_review'])
            ->assertHasNoActionErrors();
        $this->assertSame('in_review', $record->fresh()->status);
        $this->assertSame('Replied separately; follow up next week.', $record->fresh()->staff_notes);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_review_rejects_unknown_status_and_excessive_staff_notes(): void
    {
        $this->staff();
        $record = $this->message();
        Livewire::test(ManageContactMessages::class)
            ->callTableAction('edit', $record, data: ['status' => 'not_a_status'])
            ->assertHasActionErrors(['status' => 'in']);
        Livewire::test(ManageContactMessages::class)
            ->callTableAction('edit', $record, data: ['status' => 'in_review', 'staff_notes' => str_repeat('a', 5001)])
            ->assertHasActionErrors(['staff_notes' => 'max']);
        $this->assertSame('new', $record->fresh()->status);
        $this->assertNull($record->fresh()->staff_notes);
    }

    public function test_staff_can_search_and_filter_by_status_and_inclusive_received_dates(): void
    {
        $this->staff();
        $first = $this->message(['name' => 'Amber Contact', 'email' => 'amber@example.test', 'created_at' => '2026-01-10 00:00:00']);
        $last = $this->message(['name' => 'Blue Contact', 'email' => 'blue@example.test', 'created_at' => '2026-01-10 23:59:59']);
        $outside = $this->message(['name' => 'Cedar Contact', 'subject' => 'Unique outside subject', 'status' => 'closed', 'created_at' => '2026-01-11 00:00:00']);

        Livewire::test(ManageContactMessages::class)->searchTable('blue@example.test')
            ->assertCanSeeTableRecords([$last])->assertCanNotSeeTableRecords([$first, $outside]);
        Livewire::test(ManageContactMessages::class)->searchTable('Unique outside subject')
            ->assertCanSeeTableRecords([$outside])->assertCanNotSeeTableRecords([$first, $last]);
        Livewire::test(ManageContactMessages::class)->filterTable('status', 'new')
            ->assertCanSeeTableRecords([$first, $last])->assertCanNotSeeTableRecords([$outside]);
        Livewire::test(ManageContactMessages::class)->filterTable('received', ['from' => '2026-01-10', 'until' => '2026-01-10'])
            ->assertCanSeeTableRecords([$first, $last])->assertCanNotSeeTableRecords([$outside]);
    }

    public function test_submitted_message_and_private_notes_are_escaped_in_admin_read_view(): void
    {
        $this->staff();
        $body = '<img src=x onerror="alert(\'CONTACT_MESSAGE_XSS\')">';
        $notes = '<script>window.CONTACT_NOTE_XSS=true</script>';
        $record = $this->message(['message' => $body."\nSecond message line.", 'staff_notes' => $notes]);

        $page = Livewire::test(ManageContactMessages::class)->mountTableAction('view', $record)
            ->assertActionMounted(TestAction::make('view')->table($record));
        $component = $page->instance();
        $html = $component->getSchema($component->getMountedActionSchemaName())->toHtml();

        $this->assertStringContainsString(e($body), $html);
        $this->assertStringNotContainsString($body, $html);
        $this->assertStringContainsString(e($notes), $html);
        $this->assertStringNotContainsString($notes, $html);
        $this->assertStringContainsString('Second message line.', $html);
    }

    public function test_delete_requires_a_confirmation_step_and_removes_only_that_message(): void
    {
        $this->staff();
        $record = $this->message();
        $retained = $this->message();
        $page = Livewire::test(ManageContactMessages::class)->mountTableAction('delete', $record)
            ->assertActionMounted(TestAction::make('delete')->table($record));
        $this->assertModelExists($record);
        $page->callMountedTableAction()->assertHasNoActionErrors();
        $this->assertModelMissing($record);
        $this->assertModelExists($retained);
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertSame('1', ContactMessageResource::getNavigationBadge());
    }
}
