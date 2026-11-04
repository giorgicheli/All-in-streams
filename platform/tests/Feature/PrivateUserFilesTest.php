<?php

namespace Tests\Feature;

use App\Filament\Resources\UserAccountResource;
use App\Filament\Resources\UserAccountResource\Pages\ListUserAccounts;
use App\Filament\Resources\UserFileResource;
use App\Filament\Resources\UserFileResource\Pages\ManageUserFiles;
use App\Models\User;
use App\Models\UserFile;
use App\Services\PrivateUserFiles;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class PrivateUserFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function staff(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        return $admin;
    }

    private function pdf(string $name = 'agreement.pdf'): UploadedFile { return UploadedFile::fake()->create($name, 20, 'application/pdf'); }

    private function docx(): UploadedFile
    {
        $path = Storage::disk('local')->path(uniqid('fixture-', true).'.docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<document>DEMO ONLY</document>');
        $zip->close();
        return new UploadedFile($path, 'annex.docx', null, null, true);
    }

    public function test_staff_can_attach_multiple_private_documents_to_every_account_role(): void
    {
        $admin = $this->staff();
        foreach (['business', 'creator', 'agency', 'admin'] as $role) {
            $account = User::factory()->create(['role' => $role]);
            $response = $this->postJson(route('admin.user-files.store'), ['user_id' => $account->id, 'title' => 'Internal agreement', 'notes' => 'Never shared with partner portals.', 'files' => [$this->pdf(), UploadedFile::fake()->create('terms.doc', 20, 'application/msword'), $this->docx()]])->assertCreated();
            $this->assertCount(3, $response->json('files'));
            $documents = UserFile::where('user_id', $account->id)->get();
            $this->assertCount(3, $documents);
            foreach ($documents as $document) {
                $this->assertSame($admin->id, $document->uploaded_by);
                Storage::disk('local')->assertExists($document->file_path);
                Storage::disk('public')->assertMissing($document->file_path);
                $this->get(route('admin.user-files.download', $document))->assertDownload($document->original_name)->assertHeader('X-Content-Type-Options', 'nosniff');
            }
        }
    }

    public function test_only_administrators_can_list_upload_download_or_delete_internal_user_documents(): void
    {
        $admin = $this->staff();
        $owner = User::factory()->create(['role' => 'business']);
        $document = app(PrivateUserFiles::class)->upload($admin, $owner, [$this->pdf()])[0];
        foreach ([$owner, User::factory()->create(['role' => 'business']), User::factory()->create(['role' => 'creator']), User::factory()->create(['role' => 'agency'])] as $user) {
            $this->actingAs($user)->get(UserFileResource::getUrl())->assertForbidden();
            $this->get(UserAccountResource::getUrl())->assertForbidden();
            $this->get(route('admin.user-files.download', $document))->assertForbidden();
            $this->deleteJson(route('admin.user-files.destroy', $document))->assertForbidden();
            $this->postJson(route('admin.user-files.store'), ['user_id' => $user->id, 'files' => [$this->pdf()]])->assertForbidden();
        }
        $this->assertDatabaseHas('user_files', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
        $this->actingAs($admin)->get(UserFileResource::getUrl())->assertOk()->assertSee('Internal documents');
        $this->get(UserAccountResource::getUrl())->assertOk()->assertSee($owner->email);
    }

    public function test_deleting_one_attachment_removes_its_private_original_and_keeps_other_documents(): void
    {
        $admin = $this->staff();
        $account = User::factory()->create();
        [$first, $second] = app(PrivateUserFiles::class)->upload($admin, $account, [$this->pdf('first.pdf'), $this->pdf('second.pdf')]);
        $this->deleteJson(route('admin.user-files.destroy', $first))->assertNoContent();
        $this->assertDatabaseMissing('user_files', ['id' => $first->id]);
        Storage::disk('local')->assertMissing($first->file_path);
        Storage::disk('local')->assertExists($second->file_path);
        $this->get(route('admin.user-files.download', $first))->assertNotFound();
        $this->get(route('admin.user-files.download', $second))->assertDownload();
    }

    public function test_file_validation_rejects_spoofed_extensions_oversized_and_unsupported_files_before_storing_anything(): void
    {
        $this->staff();
        $account = User::factory()->create();
        foreach ([
            UploadedFile::fake()->create('payload.php', 20, 'application/pdf'),
            UploadedFile::fake()->create('payload.pdf', 20, 'text/x-php'),
            UploadedFile::fake()->create('archive.zip', 20, 'application/zip'),
            UploadedFile::fake()->create('fake.docx', 20, 'application/zip'),
            UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
        ] as $invalid) {
            $this->postJson(route('admin.user-files.store'), ['user_id' => $account->id, 'files' => [$this->pdf(), $invalid]])->assertUnprocessable()->assertJsonValidationErrors('files.1');
            $this->assertDatabaseCount('user_files', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('admin-user-files'));
        }
        $this->postJson(route('admin.user-files.store'), ['user_id' => $account->id, 'files' => array_map(fn () => $this->pdf(), range(1, 11))])->assertUnprocessable()->assertJsonValidationErrors('files');
    }

    public function test_staff_file_actions_attach_to_the_selected_user_and_support_multiple_uploads(): void
    {
        $this->staff();
        $account = User::factory()->create(['role' => 'agency']);
        $other = User::factory()->create();
        Livewire::test(ListUserAccounts::class)->callTableAction('attachFiles', $account, data: [
            'user_id' => $other->id, 'files' => [$this->pdf(), $this->pdf('appendix.pdf')], 'notes' => 'Internal only',
        ])->assertHasNoActionErrors();
        $this->assertDatabaseCount('user_files', 2);
        $this->assertSame(2, UserFile::where('user_id', $account->id)->count());
        $this->assertSame(0, UserFile::where('user_id', $other->id)->count());

        Livewire::test(ManageUserFiles::class)->callAction('upload', data: ['user_id' => $other->id, 'files' => [$this->pdf('staff-note.pdf')]])->assertHasNoActionErrors();
        $this->assertSame(1, UserFile::where('user_id', $other->id)->count());
        Livewire::test(ManageUserFiles::class)->callTableAction('delete', UserFile::where('user_id', $other->id)->first())->assertHasNoActionErrors();
        $this->assertSame(0, UserFile::where('user_id', $other->id)->count());
    }

    public function test_internal_paths_cannot_be_changed_to_download_or_delete_unrelated_private_files(): void
    {
        $admin = $this->staff();
        $file = app(PrivateUserFiles::class)->upload($admin, User::factory()->create(), [$this->pdf()])[0];
        Storage::disk('local')->put('unrelated-secret.pdf', 'Private');
        $file->update(['file_path' => 'unrelated-secret.pdf']);
        $this->get(route('admin.user-files.download', $file))->assertNotFound();
        $this->deleteJson(route('admin.user-files.destroy', $file))->assertNotFound();
        Storage::disk('local')->assertExists('unrelated-secret.pdf');
    }
}
