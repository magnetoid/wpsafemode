<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

final class Version
{
    public const APP = '2.0.0-dev';

    /** Minimum PHP version this application supports. */
    public const MIN_PHP = '8.0.0';

    /** Storage schema version. Bump when the schema changes. */
    public const SCHEMA = 4;
}
