<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Support;

use WPSafeMode\Security\SelfTestFetcher;
use WPSafeMode\Security\WebAccessibilitySelfTest;

/** A self-test transport that answers from a closure and records every URL asked for. */
final class FakeFetcher implements SelfTestFetcher
{
    /** @var list<string> */
    public array $requested = [];

    /** @param \Closure(string): (array{status: int, body: string}|null) $answer */
    public function __construct(private \Closure $answer)
    {
    }

    public function fetch(string $url): ?array
    {
        $this->requested[] = $url;

        return ($this->answer)($url);
    }

    public static function isControl(string $url): bool
    {
        return substr($url, -strlen(WebAccessibilitySelfTest::CONTROL_PATH)) === WebAccessibilitySelfTest::CONTROL_PATH;
    }

    /** @return array{status: int, body: string} what this tool's state route answers */
    public static function toolState(): array
    {
        return ['status' => 200, 'body' => '{"installed":false,"step":"api_key"}'];
    }

    /**
     * The canary gets $canary, the control gets $control (this tool's own
     * answer unless given).
     *
     * @param array{status: int, body: string}|null $canary
     * @param array{status: int, body: string}|null $control
     */
    public static function answering(?array $canary, ?array $control = null, bool $toolAnswers = true): self
    {
        return new self(static function (string $url) use ($canary, $control, $toolAnswers): ?array {
            if (self::isControl($url)) {
                return $control ?? ($toolAnswers ? self::toolState() : null);
            }

            return $canary;
        });
    }

    /** data/ refused by this tool's own server: a pass. */
    public static function refusing(): self
    {
        return self::answering(['status' => 404, 'body' => 'Not found']);
    }

    /** Every request fails at the transport: inconclusive. */
    public static function unreachable(): self
    {
        return new self(static fn (): array => ['status' => 0, 'body' => '']);
    }

    /** Serves the canary out of $dataDir, as nginx ignoring .htaccess would. */
    public static function serving(string $dataDir): self
    {
        return new self(static function (string $url) use ($dataDir): array {
            if (self::isControl($url)) {
                return self::toolState();
            }

            $file = $dataDir . '/' . basename((string) parse_url($url, PHP_URL_PATH));

            return is_file($file)
                ? ['status' => 200, 'body' => (string) file_get_contents($file)]
                : ['status' => 404, 'body' => ''];
        });
    }
}
