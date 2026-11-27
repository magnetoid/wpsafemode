<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Security\SelfTestResult;

final class InstallState
{
    public function __construct(
        public bool $installed,
        public string $step,
        public bool $hasOwner,
        public bool $hasApiKey,
        public ?SelfTestResult $selfTest,
        public string $encryption,
        public bool $hasWordPress,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'installed'     => $this->installed,
            'step'          => $this->step,
            'has_owner'     => $this->hasOwner,
            'has_api_key'   => $this->hasApiKey,
            'self_test'     => $this->selfTest === null ? null : $this->selfTest->toArray(),
            'encryption'    => $this->encryption,
            'has_wordpress' => $this->hasWordPress,
        ];
    }

    /**
     * What an installed copy tells anyone who asks: nothing about its
     * configuration.
     *
     * @return array{installed: true, step: string}
     */
    public function toPublicArray(): array
    {
        return ['installed' => true, 'step' => Installer::STEP_DONE];
    }
}
