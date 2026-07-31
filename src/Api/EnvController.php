<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\Version;
use WPSafeMode\Storage\Contracts\Storage;

final class EnvController
{
    public function __construct(private Storage $storage)
    {
    }

    public function __invoke(Request $request): Response
    {
        return Response::json([
            'app'         => ['version' => Version::APP],
            'environment' => Environment::probe()->toArray(),
            'storage'     => ['backend' => $this->storage->backend()],
        ]);
    }
}
