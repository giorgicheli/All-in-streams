<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Only three fixed operations are accepted; neither clients nor admin forms can provide executable arguments. */
class CreatorMediaProcessor
{
    private array $capabilityCache = [];

    public function request(array $request): array
    {
        if (($request['operation'] ?? null) !== 'capabilities') return $this->dispatch($request);
        $key = hash('sha256', json_encode([config('creator-media.ffprobe'), config('creator-media.ffmpeg'), config('creator-media.worker_url'), config('creator-media.worker_token')], JSON_THROW_ON_ERROR));
        if (array_key_exists($key, $this->capabilityCache)) return $this->capabilityCache[$key];
        try { return $this->capabilityCache[$key] = $this->dispatch($request); }
        catch (\Throwable) { return $this->capabilityCache[$key] = ['ffprobe' => false, 'ffmpeg' => false]; }
    }

    private function dispatch(array $request): array
    {
        $url = config('creator-media.worker_url');
        if (! $url) return $this->execute($request);
        if (! preg_match('~\Ahttp://127\.0\.0\.1:[0-9]{2,5}\z~D', $url) || strlen((string) config('creator-media.worker_token')) < 32) throw new RuntimeException('Invalid media worker configuration.');
        $response = Http::withoutRedirecting()->connectTimeout(2)->timeout(($request['operation'] ?? '') === 'compress' ? 135 : 20)
            ->withToken(config('creator-media.worker_token'))->acceptJson()->post($url.'/media', $request);
        if (! $response->successful() || ! is_array($response->json('result'))) throw new RuntimeException('Media worker could not complete the request.');
        return $response->json('result');
    }

    /** Invoked locally by the private worker, never accepts general commands or arguments. */
    public function execute(array $request): array
    {
        $operation = $request['operation'] ?? null;
        if ($operation === 'capabilities') {
            $result = [];
            foreach (['ffprobe', 'ffmpeg'] as $tool) {
                try { $this->run([config('creator-media.'.$tool), '-version'], 5); $result[$tool] = true; }
                catch (\Throwable) { $result[$tool] = false; }
            }
            return $result;
        }
        if (! in_array($operation, ['probe', 'compress'], true)) throw new RuntimeException('Unsupported media operation.');
        $source = $this->path($request['source'] ?? null, true);
        if ($operation === 'probe') {
            return json_decode($this->run([config('creator-media.ffprobe'), '-v', 'error', ...$this->inputRestrictions(), '-show_entries', 'format=duration:stream=codec_type,width,height', '-of', 'json', $source], 15), true, 16, JSON_THROW_ON_ERROR);
        }
        $target = $this->path($request['target'] ?? null, false);
        if (pathinfo($target, PATHINFO_EXTENSION) !== 'mp4') throw new RuntimeException('Invalid compression destination.');
        $width = filter_var($request['width'] ?? null, FILTER_VALIDATE_INT);
        $quality = filter_var($request['quality'] ?? null, FILTER_VALIDATE_INT);
        if (! in_array($width, [640, 960, 1280, 1920], true) || $quality < 18 || $quality > 35) throw new RuntimeException('Invalid compression parameters.');
        $this->run([config('creator-media.ffmpeg'), '-nostdin', '-v', 'error', ...$this->inputRestrictions(), '-i', $source, '-map', '0:v:0', '-map', '0:a:0?', '-vf', 'scale=trunc(min('.$width.'\,iw)/2)*2:-2', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', (string) $quality, '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', '-n', $target], 120);
        return ['compressed' => true];
    }

    private function inputRestrictions(): array
    {
        // Avoid playlist/network demuxers even when an attacker disguises them with a video filename.
        // MOV external data references stay disabled (FFmpeg's secure default).
        return ['-protocol_whitelist', 'file', '-format_whitelist', 'mov,matroska,webm', '-max_streams', '8'];
    }

    private function path(mixed $relative, bool $existing): string
    {
        if (! is_string($relative) || ! preg_match('~\Acreator-proof/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.(?:mp4|mov|webm)\z~D', $relative)) throw new RuntimeException('Invalid private media path.');
        $directory = Storage::disk('local')->path('creator-proof');
        $root = realpath($directory);
        $diskRoot = realpath(Storage::disk('local')->path(''));
        if (! $root || ! $diskRoot || is_link($directory) || $root !== $diskRoot.DIRECTORY_SEPARATOR.'creator-proof') throw new RuntimeException('Private media storage is unavailable.');
        $path = Storage::disk('local')->path($relative);
        if ($existing) {
            $resolved = realpath($path);
            if (! $resolved || is_link($path) || ! is_file($resolved) || dirname($resolved) !== $root) throw new RuntimeException('Invalid private media source.');
            return $resolved;
        }
        if (file_exists($path) || is_link($path) || realpath(dirname($path)) !== $root) throw new RuntimeException('Invalid private media destination.');
        return $path;
    }

    private function run(array $arguments, int $timeout): string
    {
        if (! function_exists('proc_open')) throw new RuntimeException('Local media processing is unavailable.');
        $process = new Process($arguments);
        $process->setTimeout($timeout)->run();
        if (! $process->isSuccessful()) throw new RuntimeException('Media processing failed.');
        return $process->getOutput();
    }
}
