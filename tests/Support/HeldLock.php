<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Plays a second request that is part-way through a locked first-run step.
 *
 * A child PHP process takes GuardedJson's exclusive lock on $path, runs
 * $before, signals that it is ready, holds the lock for $holdMicros, then
 * runs $after and exits. The test process calls $whileHeld() once the child
 * is ready, so it runs while the other "request" holds the lock and is
 * mid-step. The lock is taken in another process because flock() is not
 * reentrant: the test process blocking on its own lock would wait forever.
 *
 * $before and $after are PHP statements run inside the locked callback, with
 * $path in scope. Whatever $after echoes is returned.
 */
final class HeldLock
{
    public static function run(string $path, string $before, string $after, callable $whileHeld, int $holdMicros = 500_000): string
    {
        $dir = sys_get_temp_dir() . '/wpsm-heldlock-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $script = $dir . '/child.php';
        $ready = $dir . '/ready';

        file_put_contents($script, sprintf(
            <<<'PHP'
                <?php
                declare(strict_types=1);
                define('WPSM_ENTRY', true);
                require %s;
                $path = $argv[1];
                $ready = $argv[2];
                \WPSafeMode\Security\GuardedJson::withExclusiveLock($path, static function () use ($path, $ready): void {
                    %s
                    touch($ready);
                    usleep(%d);
                    %s
                });
                PHP,
            var_export(dirname(__DIR__, 2) . '/src/autoload.php', true),
            $before,
            $holdMicros,
            $after
        ));

        $process = proc_open(
            [PHP_BINARY, $script, $path, $ready],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        Assert::assertIsResource($process);

        try {
            $deadline = microtime(true) + 10;
            while (!is_file($ready)) {
                if (microtime(true) > $deadline) {
                    Assert::fail('The lock-holding child never became ready: ' . stream_get_contents($pipes[2]));
                }
                usleep(5_000);
            }

            $whileHeld();

            $out = (string) stream_get_contents($pipes[1]);
            $err = (string) stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);
            @unlink($ready);
            @unlink($script);
            @rmdir($dir);
        }

        Assert::assertSame(0, $status, 'The lock-holding child failed: ' . $err);

        return $out;
    }
}
