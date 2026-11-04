<?php

namespace Tests\Concerns;

use App\Services\CreatorProofVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FakeCreatorProofVideo
{
    private function fakeCreatorProofVideos(): void
    {
        Storage::fake('local');
        // Onboarding tests exercise validation, storage and authorization. Media
        // inspection/compression is tested separately against the video service.
        $this->partialMock(CreatorProofVideo::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldReceive('inspect')->andReturnNull();
        });
    }

    private function creatorProofVideo(): UploadedFile
    {
        return UploadedFile::fake()->create('profile-and-audience.mp4', 100, 'video/mp4');
    }
}
