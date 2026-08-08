<?php

declare(strict_types=1);

namespace WPSafeMode\Tests;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Version;

final class AutoloadTest extends TestCase
{
    public function testAutoloaderResolvesNamespacedClass(): void
    {
        self::assertTrue(class_exists(Version::class));
        self::assertNotSame('', Version::APP);
    }

    public function testAutoloaderIgnoresForeignNamespaces(): void
    {
        self::assertFalse(class_exists('SomeVendor\\Thing'));
    }
}
