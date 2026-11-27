<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Core;

use PHPUnit\Framework\TestCase;

/**
 * A drop-in install has no rewrite rules, so every .php file under src/ can be
 * fetched by URL. Only index.php may run that way; every other file must stop
 * before it declares or loads anything, or PHP's own error names its path.
 */
final class EntryGuardTest extends TestCase
{
    private const GUARD = "if(!defined('WPSM_ENTRY')){http_response_code(404);exit;}";

    /** @return list<string> every shipped .php file except the entry point, relative to src/ */
    private static function shippedPhpFiles(): array
    {
        $src = dirname(__DIR__, 2) . '/src';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($src) + 1);

            if (
                $file->getExtension() !== 'php'
                || $relative === 'index.php'
                || strpos($relative, 'data/') === 0
                || strpos($relative, 'public/') === 0
            ) {
                continue;
            }

            $files[] = $relative;
        }

        sort($files);

        return $files;
    }

    /** The file's code with whitespace and comments removed. */
    private static function code(string $path): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    public function testEveryFileButTheEntryPointStopsFirstWhenFetchedDirectly(): void
    {
        $files = self::shippedPhpFiles();
        self::assertGreaterThan(50, count($files), 'The scan found too few files to mean anything.');

        $src = dirname(__DIR__, 2) . '/src/';

        foreach ($files as $relative) {
            // The first statement after declare() and namespace, so nothing
            // (not even a use import's class) runs before it.
            self::assertMatchesRegularExpression(
                '/\Adeclare\(strict_types=1\);(namespace[A-Za-z0-9_\\\\]+;)?' . preg_quote(self::GUARD, '/') . '/',
                self::code($src . $relative),
                $relative . ' lacks the WPSM_ENTRY guard as its first statement.'
            );
        }
    }

    public function testOnlyTheEntryPointDefinesTheConstantAndItDoesSoBeforeTheBootstrap(): void
    {
        $src = dirname(__DIR__, 2) . '/src/';
        $index = self::code($src . 'index.php');

        $define = strpos($index, "define('WPSM_ENTRY',true);");
        $require = strpos($index, "require__DIR__.'/bootstrap.php';");
        self::assertNotFalse($define, 'index.php does not define WPSM_ENTRY.');
        self::assertNotFalse($require);
        self::assertLessThan($require, $define, 'index.php must define WPSM_ENTRY before it requires bootstrap.php.');

        foreach (self::shippedPhpFiles() as $relative) {
            self::assertStringNotContainsString("define('WPSM_ENTRY'", self::code($src . $relative), $relative . ' defines WPSM_ENTRY.');
        }
    }
}
