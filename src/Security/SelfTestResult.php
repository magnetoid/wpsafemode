<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

final class SelfTestResult
{
    public function __construct(
        public bool $reachable,
        public bool $inconclusive,
        public string $detail,
        public ?string $checkedUrl,
        public int $at,
    ) {
    }

    /**
     * Fails closed. "We could not check" is treated exactly like "it is
     * reachable": an installer that stores the API key when the check did not
     * run is the v1.1 failure with extra steps.
     */
    public function blocksApiKey(): bool
    {
        return $this->reachable || $this->inconclusive;
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'reachable'    => $this->reachable,
            'inconclusive' => $this->inconclusive,
            'detail'       => $this->detail,
            'checked_url'  => $this->checkedUrl,
            'at'           => $this->at,
        ];
    }
}
