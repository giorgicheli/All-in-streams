<?php

namespace App\Http\Controllers;

use App\Models\CreatorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreatorProofVideoController extends Controller
{
    public function show(Request $request, CreatorProfile $creator)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $path = $creator->verification_video_path;
        abort_unless($path && str_starts_with($path, 'creator-proof/') && ! str_contains($path, '..') && ! str_contains($path, '\\') && Storage::disk('local')->exists($path), 404);
        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $creator->verification_video_mime ?: 'video/mp4',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
        $response->setContentDisposition($request->boolean('download') ? 'attachment' : 'inline', 'creator-'.$creator->id.'-verification.'.pathinfo($path, PATHINFO_EXTENSION));
        return $response;
    }
}
