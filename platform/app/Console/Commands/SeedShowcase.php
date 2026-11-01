<?php

namespace App\Console\Commands;

use Database\Seeders\ShowcaseSeeder;
use Illuminate\Console\Command;
use LogicException;
use RuntimeException;
use Throwable;

class SeedShowcase extends Command
{
    protected $signature = 'showcase:seed {--force : Explicitly allow fictional sample data on production} {--credentials-file= : Absolute private JSON file for newly created sample account credentials}';

    protected $description = 'Create a clearly marked, fictional workspace with companies, agencies, creators and campaign statistics';

    public function handle(ShowcaseSeeder $seeder): int
    {
        try {
            if (app()->environment('production') && ! $this->option('force')) {
                throw new LogicException('Production sample data requires --force. No records were created.');
            }

            $file = $this->option('credentials-file');
            [$destination, $existing] = $file ? $this->credentialDestination((string) $file) : [null, []];
            $result = $seeder->provision((bool) $this->option('force'), function (array $result) use ($destination, $existing) {
                if ($destination && $result['new_credentials']) {
                    $accounts = [];
                    foreach (array_merge($existing, $result['new_credentials']) as $account) {
                        $accounts[$account['email']] = $account;
                    }
                    $this->writeCredentials($destination, array_values($accounts));
                }
            });

            $this->info('Fictional sample workspace ready. Existing records and account passwords were preserved.');
            foreach ($result['counts'] as $label => $count) {
                $this->line(ucwords(str_replace('_', ' ', $label)).': '.$count);
            }
            $this->line('New sample accounts: '.count($result['new_credentials']));
            if ($destination) {
                $this->line('Private credential file: '.$destination);
            } elseif ($result['new_credentials']) {
                $this->warn('No credential file requested. Generated passwords were not printed or retained.');
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Only our own validation errors are safe to print. Database and filesystem errors may contain secrets.
            $this->error($exception instanceof LogicException ? $exception->getMessage() : 'Sample setup failed; diagnostic details were suppressed to protect private data.');
            return self::FAILURE;
        }
    }

    private function credentialDestination(string $file): array
    {
        if (! preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $file)) {
            throw new LogicException('The credentials file must use an absolute private path outside the public directory.');
        }
        $parent = realpath(dirname($file));
        if (! $parent || ! is_dir($parent) || ! is_writable($parent)) {
            throw new LogicException('The private credentials directory must already exist and be writable.');
        }
        $destination = $parent.DIRECTORY_SEPARATOR.basename($file);
        $public = realpath(public_path());
        $normalized = str_replace('\\', '/', $destination);
        $normalizedPublic = str_replace('\\', '/', $public ?: public_path());
        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = strtolower($normalized);
            $normalizedPublic = strtolower($normalizedPublic);
        }
        if ($normalized === $normalizedPublic || str_starts_with($normalized, rtrim($normalizedPublic, '/').'/')) {
            throw new LogicException('The credentials file cannot be stored under the public directory.');
        }
        if (is_link($destination) || (file_exists($destination) && ! is_file($destination))) {
            throw new LogicException('The credentials destination must not be a symbolic link or special file.');
        }
        if (! file_exists($destination)) {
            return [$destination, []];
        }
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($destination) & 0077) !== 0) {
            throw new LogicException('An existing credential file must be private (mode 0600).');
        }
        $document = json_decode(file_get_contents($destination), true);
        if (! is_array($document) || ($document['workspace'] ?? null) !== ShowcaseSeeder::PREFIX || ! is_array($document['accounts'] ?? null)) {
            throw new LogicException('The existing file does not belong to this sample workspace and was preserved.');
        }
        foreach ($document['accounts'] as $account) {
            if (! is_array($account) || ! is_string($account['email'] ?? null) || ! is_string($account['password'] ?? null)) {
                throw new LogicException('The existing sample credential metadata is invalid and was preserved.');
            }
        }
        return [$destination, $document['accounts']];
    }

    private function writeCredentials(string $destination, array $accounts): void
    {
        $temporary = tempnam(dirname($destination), '.ais-showcase-');
        if ($temporary === false) {
            throw new RuntimeException('Could not prepare a private credential file.');
        }
        try {
            if (! chmod($temporary, 0600)) {
                throw new RuntimeException('Could not restrict credential file permissions.');
            }
            $document = ['workspace' => ShowcaseSeeder::PREFIX, 'notice' => 'Fictional sample users. Keep these generated credentials private.', 'accounts' => $accounts];
            $content = json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            if (file_put_contents($temporary, $content) !== strlen($content) || ! rename($temporary, $destination)) {
                throw new RuntimeException('Could not save the private credential file.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
