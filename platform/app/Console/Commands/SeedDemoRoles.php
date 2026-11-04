<?php

namespace App\Console\Commands;

use App\Services\DemoRoleWorkspace;
use Illuminate\Console\Command;
use LogicException;
use RuntimeException;
use Throwable;

class SeedDemoRoles extends Command
{
    protected $signature = 'demo:roles {--force : Allow the clearly fictional workspace on production} {--fixtures-dir= : Directory containing sample-agreement.pdf, internal-notes.docx and verification-demo.mp4} {--credentials-file= : Absolute private JSON destination for generated test credentials}';
    protected $description = 'Prepare five fictional role accounts, private example files and staged sample campaigns without resetting existing records';

    public function handle(DemoRoleWorkspace $workspace): int
    {
        try {
            if (app()->environment('production') && ! $this->option('force')) throw new LogicException('Production demo setup requires --force. No records were created.');
            $fixtures = realpath((string) $this->option('fixtures-dir'));
            if (! $fixtures || ! is_dir($fixtures)) throw new LogicException('Provide --fixtures-dir with the three clearly labelled demo files.');
            [$destination, $existing] = $this->destination((string) $this->option('credentials-file'));
            $result = $workspace->provision($fixtures, $existing, fn (array $accounts) => $this->writeCredentials($destination, $accounts));
            $this->info('Fictional role workspace ready. Existing passwords and workflow changes were preserved.');
            foreach ($result as $label => $count) $this->line(ucwords(str_replace('_', ' ', $label)).': '.$count);
            $this->line('Private credentials saved to: '.$destination);
            $this->line('The example video and unsigned agreements are explicitly DEMO material. No real payment or signature was recorded.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception instanceof LogicException ? $exception->getMessage() : 'Demo setup failed. Diagnostic details were suppressed to protect private data.');
            return self::FAILURE;
        }
    }

    private function destination(string $file): array
    {
        if (! preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $file)) throw new LogicException('Provide an absolute --credentials-file path in a private directory.');
        $parent = realpath(dirname($file));
        if (! $parent || ! is_dir($parent) || ! is_writable($parent)) throw new LogicException('The private credentials directory must already exist and be writable.');
        $destination = $parent.DIRECTORY_SEPARATOR.basename($file);
        foreach ([public_path(), storage_path('app/public')] as $publicRoot) {
            $normalized = str_replace('\\', '/', $destination);
            $root = str_replace('\\', '/', realpath($publicRoot) ?: $publicRoot);
            if (PHP_OS_FAMILY === 'Windows') { $normalized = strtolower($normalized); $root = strtolower($root); }
            if ($normalized === $root || str_starts_with($normalized, rtrim($root, '/').'/')) throw new LogicException('Credentials cannot be saved in a publicly served directory.');
        }
        if (is_link($destination) || (file_exists($destination) && ! is_file($destination))) throw new LogicException('The credential destination cannot be a symbolic link or special file.');
        if (! file_exists($destination)) return [$destination, []];
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($destination) & 0077) !== 0) throw new LogicException('An existing credentials file must have private mode 0600.');
        $document = json_decode(file_get_contents($destination), true);
        if (! is_array($document) || ($document['workspace'] ?? null) !== DemoRoleWorkspace::WORKSPACE || ! is_array($document['accounts'] ?? null)) throw new LogicException('The existing credentials file belongs to another workspace and was preserved.');
        foreach ($document['accounts'] as $account) {
            if (! is_array($account) || ! in_array($account['role'] ?? null, DemoRoleWorkspace::ROLES, true)
                || ($account['email'] ?? null) !== $account['role'].'@demo.allinstreams.example' || ! is_string($account['password'] ?? null) || $account['password'] === '') {
                throw new LogicException('Existing demo credential metadata is invalid and was preserved.');
            }
        }
        return [$destination, $document['accounts']];
    }

    private function writeCredentials(string $destination, array $accounts): void
    {
        $temporary = tempnam(dirname($destination), '.ais-role-');
        if ($temporary === false) throw new RuntimeException('Could not prepare the private credentials file.');
        try {
            if (! chmod($temporary, 0600)) throw new RuntimeException('Could not restrict credential file permissions.');
            $content = json_encode(['workspace' => DemoRoleWorkspace::WORKSPACE, 'notice' => 'PRIVATE TEST ACCESS. Fictional accounts only. password_is_current=false means the test user changed their password; it was not reset.', 'accounts' => $accounts], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            if (file_put_contents($temporary, $content) !== strlen($content) || ! rename($temporary, $destination)) throw new RuntimeException('Could not save the private credentials file.');
        } finally {
            if (file_exists($temporary)) unlink($temporary);
        }
    }
}
