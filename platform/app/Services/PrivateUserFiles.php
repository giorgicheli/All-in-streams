<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

class PrivateUserFiles
{
    public const MAX_KILOBYTES = 10240;
    public const MIME_TYPES = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'];

    /** @return list<UserFile> */
    public function upload(User $actor, User $account, array $files, ?string $title = null, ?string $notes = null): array
    {
        abort_unless($actor->role === 'admin', 403);
        Validator::make(compact('files', 'title', 'notes'), [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
            'title' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ])->validate();

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile || ! $this->acceptedDocument($file)) {
                throw ValidationException::withMessages(['files.'.$index => 'Choose a PDF, DOC or DOCX document.']);
            }
        }

        $stored = [];
        try {
            return DB::transaction(function () use ($actor, $account, $files, $title, $notes, &$stored): array {
                $account = User::query()->lockForUpdate()->findOrFail($account->id);
                $records = [];
                foreach ($files as $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $path = $file->storeAs('admin-user-files/'.$account->id, Str::uuid().'.'.$extension, ['disk' => 'local', 'visibility' => 'private']);
                    if (! $path) throw ValidationException::withMessages(['files' => 'The document could not be stored. Please try again.']);
                    $stored[] = $path;
                    $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))), 0, 180);
                    $records[] = UserFile::create([
                        'user_id' => $account->id, 'uploaded_by' => $actor->id,
                        'title' => trim((string) $title) ?: $name, 'original_name' => $name,
                        'file_path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => $file->getSize(), 'notes' => $notes,
                    ]);
                }
                return $records;
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function path(User $actor, UserFile $file): string
    {
        abort_unless($actor->role === 'admin', 403);
        abort_unless(preg_match('#^admin-user-files/'.preg_quote((string) $file->user_id, '#').'/[a-f0-9-]+\.(pdf|doc|docx)$#D', $file->file_path), 404);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);
        return $file->file_path;
    }

    private function acceptedDocument(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        if ($extension === 'pdf') return $mime === 'application/pdf';
        if ($extension === 'doc') return in_array($mime, ['application/msword', 'application/x-ole-storage'], true);
        if ($extension !== 'docx' || ! in_array($mime, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'], true) || ! class_exists(ZipArchive::class)) return false;
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) return false;
        try {
            if ($zip->numFiles > 2000 || $zip->locateName('word/document.xml') === false || $zip->locateName('[Content_Types].xml') === false) return false;
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = str_replace('\\', '/', $entry['name']);
                $size += $entry['size'];
                if ($size > 100 * 1048576 || str_starts_with($name, '/') || in_array('..', explode('/', $name), true) || str_contains(strtolower($name), 'vbaproject')) return false;
            }
            $types = $zip->statName('[Content_Types].xml');
            return $types['size'] <= 1048576 && str_contains($zip->getFromName('[Content_Types].xml'), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');
        } finally { $zip->close(); }
    }

    public function delete(User $actor, UserFile $file): void
    {
        abort_unless($actor->role === 'admin', 403);
        // Never let a modified database path delete an unrelated private file.
        DB::transaction(function () use ($file): void {
            $file = UserFile::query()->lockForUpdate()->findOrFail($file->id);
            abort_unless(preg_match('#^admin-user-files/'.preg_quote((string) $file->user_id, '#').'/[a-f0-9-]+\.(pdf|doc|docx)$#D', $file->file_path), 404);
            $path = $file->file_path;
            $file->delete();
            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }
}
