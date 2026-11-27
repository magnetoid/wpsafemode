<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

/**
 * The self-test's HTTP transport: one GET that never follows a redirect.
 * A seam, so the outcome mapping can be tested without a network.
 */
interface SelfTestFetcher
{
    /**
     * @return array{status: int, body: string}|null status 0 when the request
     *         failed; null when this host has no way to make a request at all
     */
    public function fetch(string $url): ?array;
}
