<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use RuntimeException;

final class StorageException extends RuntimeException
{
}
