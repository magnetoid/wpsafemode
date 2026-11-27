<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/** How a request reached the tool: the browser UI or the remote (agent) interface. */
final class Channel
{
    public const BROWSER = 'browser';
    public const REMOTE  = 'remote';

    public const ALL = [self::BROWSER, self::REMOTE];
}
