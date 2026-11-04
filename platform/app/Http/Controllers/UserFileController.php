<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFile;
use App\Services\PrivateUserFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserFileController extends Controller
{
    public function store(Request $request, PrivateUserFiles $files)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'title' => ['nullable', 'string', 'max:180'], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $records = $files->upload($request->user(), User::findOrFail($data['user_id']), $request->file('files', []), $request->input('title'), $request->input('notes'));
        return response()->json(['files' => collect($records)->map(fn (UserFile $file) => ['id' => $file->id, 'title' => $file->title, 'original_name' => $file->original_name])->all()], 201);
    }

    public function download(Request $request, UserFile $userFile, PrivateUserFiles $files)
    {
        $path = $files->path($request->user(), $userFile);
        return Storage::disk('local')->download($path, $userFile->original_name, [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    public function destroy(Request $request, UserFile $userFile, PrivateUserFiles $files)
    {
        $files->delete($request->user(), $userFile);
        return response()->noContent();
    }
}
