<?php

namespace App\Services;

use App\Models\CreatorMediaSetting;
use App\Models\CreatorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatorProofVideo
{
    public function maxUploadMb(): int
    {
        $limits = [CreatorMediaSetting::current()->max_upload_mb, $this->requestMaxMb()];
        foreach (['upload_max_filesize' => 0, 'post_max_size' => 1] as $setting => $reserve) {
            $bytes = $this->iniBytes((string) ini_get($setting));
            if ($bytes > 0) $limits[] = max(1, (int) floor($bytes / 1048576) - $reserve);
        }
        return min($limits);
    }
    public function requestMaxMb(): int { return max(1, min(100, (int) config('creator-media.request_max_mb', 100))); }
    public function maxDurationSeconds(): int { return CreatorMediaSetting::current()->max_duration_seconds; }

    public function rules(): array
    {
        return ['file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.($this->maxUploadMb() * 1024)];
    }

    public function available(string $tool = 'ffprobe'): bool
    {
        if (! in_array($tool, ['ffmpeg', 'ffprobe'], true)) return false;
        try {
            return (bool) (app(CreatorMediaProcessor::class)->request(['operation' => 'capabilities'])[$tool] ?? false);
        } catch (Throwable) { return false; }
    }

    public function store(UploadedFile $file): array
    {
        validator(['verification_video' => $file], ['verification_video' => ['required', ...$this->rules()]])->validate();
        $settings = CreatorMediaSetting::current();
        $extension = match ($file->getMimeType()) { 'video/webm' => 'webm', 'video/quicktime' => 'mov', default => 'mp4' };
        $path = $file->storeAs('creator-proof', Str::uuid().'.'.$extension, 'local');
        if (! $path) $this->invalid('The video could not be stored. Please try again.');
        $disk = Storage::disk('local');
        $compressed = 'creator-proof/'.Str::uuid().'.mp4';
        try {
            // Activate the deployment's inherited staff/web ACL for the isolated CLI worker.
            if (config('creator-media.worker_url') && ! @chmod($disk->path($path), 0660)) $this->invalid('The private video could not be prepared for verification. Please try again later.');
            $this->inspect($path, $settings->max_duration_seconds);
            if ($settings->compression_enabled) {
                $this->compress($path, $compressed, $settings);
                $this->inspect($compressed, $settings->max_duration_seconds);
                if ($disk->size($compressed) < $disk->size($path)) {
                    $disk->delete($path);
                    $path = $compressed;
                } else {
                    $disk->delete($compressed);
                }
            }
            return [
                'verification_video_path' => $path,
                'verification_video_original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 250, ''),
                'verification_video_mime' => $disk->mimeType($path),
                'verification_video_size' => $disk->size($path),
            ];
        } catch (Throwable $exception) {
            $disk->delete([$path, $compressed]);
            throw $exception;
        }
    }

    protected function inspect(string $path, int $maxSeconds): void
    {
        try {
            $data = app(CreatorMediaProcessor::class)->request(['operation' => 'probe', 'source' => $path]);
            $duration = (float) ($data['format']['duration'] ?? 0);
            $video = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video');
            if (! $video || ! is_finite($duration) || $duration <= 0 || $duration > $maxSeconds + 0.1 || ($video['width'] ?? 0) < 1 || ($video['height'] ?? 0) < 1 || ($video['width'] ?? 0) > 7680 || ($video['height'] ?? 0) > 7680) {
                $this->invalid('Upload a playable video no longer than '.$maxSeconds.' seconds, showing your profile and audience countries.');
            }
        } catch (ValidationException $exception) { throw $exception; }
        catch (Throwable $exception) {
            report($exception);
            $this->invalid('Video verification is temporarily unavailable. Please try again later or contact support.');
        }
    }

    protected function compress(string $source, string $target, CreatorMediaSetting $settings): void
    {
        if (! $this->available('ffmpeg')) $this->invalid('Video compression is temporarily unavailable. Please contact support.');
        $width = $settings->compression_max_width;
        $quality = max(18, min(35, $settings->compression_crf));
        try {
            app(CreatorMediaProcessor::class)->request(['operation' => 'compress', 'source' => $source, 'target' => $target, 'width' => $width, 'quality' => $quality]);
        } catch (ValidationException $exception) { throw $exception; }
        catch (Throwable $exception) { report($exception); $this->invalid('Video compression is temporarily unavailable. Please try again later.'); }
    }

    public function delete(CreatorProfile $profile): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
        $path = DB::transaction(function () use ($profile) {
            $profile = CreatorProfile::withTrashed()->lockForUpdate()->findOrFail($profile->id);
            $path = $profile->verification_video_path;
            $profile->update(['verification_video_path' => null, 'verification_video_original_name' => null, 'verification_video_mime' => null, 'verification_video_size' => null]);
            return $path;
        });
        $this->deletePath($path);
    }

    public function deletePath(?string $path): void
    {
        if ($path && str_starts_with($path, 'creator-proof/') && ! str_contains($path, '..') && ! str_contains($path, '\\')) Storage::disk('local')->delete($path);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['verification_video' => $message]);
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if (! preg_match('/\A([0-9]+)\s*([KMG]?)\z/iD', $value, $match)) return 0;
        return (int) $match[1] * (1024 ** (['' => 0, 'K' => 1, 'M' => 2, 'G' => 3][strtoupper($match[2])]));
    }
}
