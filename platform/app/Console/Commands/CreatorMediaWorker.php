<?php

namespace App\Console\Commands;

use App\Services\CreatorMediaProcessor;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/** Loopback-only, token-authenticated bridge for hosts that disable process execution in PHP-FPM. */
class CreatorMediaWorker extends Command
{
    protected $signature = 'creator-media:worker {--port=18743 : Loopback listener port}';
    protected $description = 'Process private creator videos without enabling process execution in PHP-FPM';

    public function handle(CreatorMediaProcessor $processor): int
    {
        $port = filter_var($this->option('port'), FILTER_VALIDATE_INT);
        $token = (string) config('creator-media.worker_token');
        if ($port < 1024 || $port > 65535 || strlen($token) < 32 || ! function_exists('proc_open')) {
            $this->error('A valid port, private worker token and CLI process execution are required.');
            return self::FAILURE;
        }
        $server = @stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $error);
        if (! $server) { $this->error('The private media listener could not start.'); return self::FAILURE; }
        umask(0007);
        $this->info('Private creator media worker listening on loopback.');
        while (true) {
            $client = @stream_socket_accept($server, 10);
            if (! $client) continue;
            stream_set_timeout($client, 5);
            $status = 200;
            try {
                $request = $this->readRequest($client, $token);
                $payload = ['result' => $processor->execute($request)];
            } catch (Throwable) {
                // Do not disclose filesystem paths, tokens or executable output over HTTP.
                $status = 422;
                $payload = ['error' => 'The private media request could not be processed.'];
            }
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            @fwrite($client, 'HTTP/1.1 '.$status.($status === 200 ? ' OK' : ' Unprocessable Entity')."\r\nContent-Type: application/json\r\nCache-Control: no-store\r\nConnection: close\r\nContent-Length: ".strlen($json)."\r\n\r\n".$json);
            fclose($client);
        }
    }

    private function readRequest($client, string $token): array
    {
        $line = fgets($client, 1024);
        if (! is_string($line) || ! preg_match('~\APOST /media HTTP/1\.[01]\r\n\z~D', $line)) throw new RuntimeException('Invalid request.');
        $headers = [];
        $size = strlen($line);
        while (($line = fgets($client, 4096)) !== false && $line !== "\r\n") {
            $size += strlen($line);
            if ($size > 8192 || ! str_contains($line, ':')) throw new RuntimeException('Invalid headers.');
            [$key, $value] = explode(':', $line, 2);
            $key = strtolower(trim($key));
            if (isset($headers[$key])) throw new RuntimeException('Duplicate header.');
            $headers[$key] = trim($value);
        }
        if ($line !== "\r\n" || isset($headers['transfer-encoding']) || ! hash_equals('Bearer '.$token, $headers['authorization'] ?? '')) throw new RuntimeException('Invalid authentication.');
        $length = filter_var($headers['content-length'] ?? null, FILTER_VALIDATE_INT);
        if ($length === false || $length < 2 || $length > 4096) throw new RuntimeException('Invalid request size.');
        $body = '';
        while (strlen($body) < $length) {
            $part = fread($client, $length - strlen($body));
            if ($part === false || $part === '') throw new RuntimeException('Incomplete request.');
            $body .= $part;
        }
        $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! in_array($data['operation'] ?? null, ['probe', 'compress', 'capabilities'], true)) throw new RuntimeException('Invalid operation.');
        return $data;
    }
}
