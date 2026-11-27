<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\Redact;

final class RedactTest extends TestCase
{
    public function testNestedArraysAreRedacted(): void
    {
        $out = Redact::meta(['outer' => ['inner' => ['password' => 'x', 'name' => 'ok']]]);

        self::assertSame(['outer' => ['inner' => ['password' => '[redacted]', 'name' => 'ok']]], $out);
    }

    public function testCaseDoesNotMatter(): void
    {
        $out = Redact::meta(['API_KEY' => 'x', 'Password' => 'y']);

        self::assertSame(['API_KEY' => '[redacted]', 'Password' => '[redacted]'], $out);
    }

    public function testAnEmbeddedWordIsCaught(): void
    {
        self::assertSame('[redacted]', Redact::meta(['session_token' => 'x'])['session_token']);
    }

    public function testSeparatorAndCamelCaseVariantsAreCaught(): void
    {
        $out = Redact::meta([
            'api-key' => 'a', 'apiKey' => 'b', 'privateKey' => 'c', 'private.key' => 'd',
            'db passwd' => 'e', 'Set-Cookie' => 'f', 'bearer' => 'g', 'user_credentials' => 'h', 'pwd' => 'i',
        ]);

        foreach ($out as $key => $value) {
            self::assertSame('[redacted]', $value, $key);
        }
    }

    public function testPlainKeyAndOrdinaryKeysAreLeftIntact(): void
    {
        $out = Redact::meta(['key' => 'model', 'from' => 'a', 'to' => 'b']);

        self::assertSame(['key' => 'model', 'from' => 'a', 'to' => 'b'], $out);
    }
}
