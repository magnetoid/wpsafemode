<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Serves a copy of src/ with PHP's built-in web server and sends it raw HTTP
 * requests, so a test drives the real src/index.php through a real SAPI:
 * headers, status codes, display_errors and php://input all behave as they do
 * on a host. In-process tests cannot see what PHP itself prints.
 *
 * The copy lives under <base>/root/<folder>/ and the server's document root
 * is <base>/root, so the copy is reached at /<folder>/ exactly as a drop-in
 * install is. The server log sits outside the document root.
 */
final class ServedCopy
{
    public string $base;
    public string $root;
    public string $appDir;
    public string $folder;
    public int $port;

    /** @var resource|null */
    private $process = null;

    private function __construct()
    {
    }

    /**
     * @param array<string, string> $ini extra -d settings for the server, on top of display_errors=1
     */
    public static function start(string $folder = 'wpsafemode', array $ini = []): self
    {
        $copy = new self();
        $copy->folder = $folder;
        $copy->base = sys_get_temp_dir() . '/wpsm-served-' . bin2hex(random_bytes(6));
        $copy->root = $copy->base . '/root';
        $copy->appDir = $copy->root . '/' . $folder;
        mkdir($copy->root, 0700, true);
        self::copy(dirname(__DIR__, 2) . '/src', $copy->appDir, true);

        $copy->port = self::freePort();
        // The host's own php.ini still applies; these settings go on top, so
        // the copy runs where errors would be printed if it let them.
        $command = [
            PHP_BINARY,
            '-d', 'display_errors=1',
            '-d', 'html_errors=1',
            '-d', 'error_reporting=-1',
            '-d', 'zend.exception_ignore_args=0',
        ];

        foreach ($ini as $name => $value) {
            $command[] = '-d';
            $command[] = $name . '=' . $value;
        }

        array_push($command, '-S', '127.0.0.1:' . $copy->port, '-t', $copy->root);

        $log = $copy->base . '/server.log';
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            null,
            // Several workers: a request that makes a loopback call of its
            // own (the self-test) must not wait on itself.
            ['PHP_CLI_SERVER_WORKERS' => '4', 'PATH' => (string) getenv('PATH')]
        );
        Assert::assertIsResource($process, 'php -S did not start');
        $copy->process = $process;

        for ($i = 0; $i < 100; $i++) {
            $probe = @fsockopen('127.0.0.1', $copy->port, $errno, $errstr, 0.1);

            if ($probe !== false) {
                fclose($probe);

                return $copy;
            }

            usleep(50000);
        }

        $copy->stop();
        Assert::fail('php -S did not start listening: ' . (string) @file_get_contents($log));
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, 15);

            for ($i = 0; $i < 50 && proc_get_status($this->process)['running']; $i++) {
                usleep(20000);
            }

            if (proc_get_status($this->process)['running']) {
                proc_terminate($this->process, 9);
            }

            proc_close($this->process);
        }

        $this->process = null;
        self::remove($this->base);
    }

    public function serverLog(): string
    {
        return (string) @file_get_contents($this->base . '/server.log');
    }

    /** The request target for a path under the copy, with the folder percent-encoded. */
    public function target(string $rest = ''): string
    {
        return '/' . rawurlencode($this->folder) . '/' . ltrim($rest, '/');
    }

    /**
     * Sends one raw request. $target is written to the request line verbatim.
     *
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    public function request(string $method, string $target, string $body = '', array $headers = []): array
    {
        $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 5);
        Assert::assertNotFalse($socket, 'Could not connect to php -S: ' . $errstr);
        stream_set_timeout($socket, 15);

        $lines = [
            $method . ' ' . $target . ' HTTP/1.1',
            'Host: 127.0.0.1:' . $this->port,
            'Connection: close',
        ];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        if ($body !== '' || $method === 'POST') {
            $lines[] = 'Content-Length: ' . strlen($body);
        }

        fwrite($socket, implode("\r\n", $lines) . "\r\n\r\n" . $body);

        $raw = '';
        while (!feof($socket)) {
            $chunk = fread($socket, 65536);

            if ($chunk === false) {
                break;
            }

            $raw .= $chunk;
        }
        fclose($socket);

        $split = strpos($raw, "\r\n\r\n");
        Assert::assertNotFalse($split, 'No HTTP answer: ' . $raw);

        $head = explode("\r\n", substr($raw, 0, $split));
        $payload = substr($raw, $split + 4);
        Assert::assertSame(1, preg_match('#^HTTP/\S+ (\d{3})#', (string) array_shift($head), $m));

        $parsed = [];
        foreach ($head as $line) {
            $colon = strpos($line, ':');

            if ($colon !== false) {
                $parsed[strtolower(trim(substr($line, 0, $colon)))][] = trim(substr($line, $colon + 1));
            }
        }

        if (strtolower($parsed['transfer-encoding'][0] ?? '') === 'chunked') {
            $payload = self::dechunk($payload);
        }

        return ['status' => (int) $m[1], 'headers' => $parsed, 'body' => $payload];
    }

    private static function dechunk(string $payload): string
    {
        $out = '';

        while ($payload !== '') {
            $eol = strpos($payload, "\r\n");

            if ($eol === false) {
                break;
            }

            $size = (int) hexdec(substr($payload, 0, $eol));

            if ($size === 0) {
                break;
            }

            $out .= substr($payload, $eol + 2, $size);
            $payload = substr($payload, $eol + 2 + $size + 2);
        }

        return $out;
    }

    private static function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        Assert::assertNotFalse($server, 'No free port: ' . $errstr);
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private static function copy(string $from, string $to, bool $top): void
    {
        mkdir($to, 0755, true);

        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ($top && $entry === 'data')) {
                continue;
            }

            is_dir($from . '/' . $entry)
                ? self::copy($from . '/' . $entry, $to . '/' . $entry, false)
                : copy($from . '/' . $entry, $to . '/' . $entry);
        }
    }

    public static function remove(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        @chmod($path, 0700);

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;

            if (is_dir($full) && !is_link($full)) {
                self::remove($full);
            } else {
                @chmod($full, 0600);
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
