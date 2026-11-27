<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Core;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\PublicFile;
use WPSafeMode\Tests\Support\ServedCopy;

final class PublicFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-public-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/public/assets', 0700, true);
        mkdir($this->dir . '/public-x', 0700, true);
        file_put_contents($this->dir . '/public/index.html', 'shell');
        file_put_contents($this->dir . '/public/assets/app.js', 'js');
        file_put_contents($this->dir . '/public-x/secret.txt', 'outside');
        file_put_contents($this->dir . '/index.php', '<?php');
    }

    protected function tearDown(): void
    {
        ServedCopy::remove($this->dir);
    }

    public function testAFileInsidePublicResolves(): void
    {
        self::assertSame(realpath($this->dir . '/public/assets/app.js'), PublicFile::resolve($this->dir . '/public', '/assets/app.js'));
        self::assertSame(realpath($this->dir . '/public/index.html'), PublicFile::resolve($this->dir . '/public', '/index.html'));
    }

    public function testASiblingThatSharesThePrefixIsOutside(): void
    {
        self::assertNull(PublicFile::resolve($this->dir . '/public', '/../public-x/secret.txt'));
    }

    public function testTraversalADirectoryAndANulByteResolveToNothing(): void
    {
        self::assertNull(PublicFile::resolve($this->dir . '/public', '/../index.php'));
        self::assertNull(PublicFile::resolve($this->dir . '/public', '/assets'));
        self::assertNull(PublicFile::resolve($this->dir . '/public', '/'));
        self::assertNull(PublicFile::resolve($this->dir . '/public', "/index.html\0.js"));
        self::assertNull(PublicFile::resolve($this->dir . '/public', '/missing.js'));
    }
}
