<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

interface Storage
{
    public function config(): ConfigStore;

    public function sessions(): SessionRepo;

    public function messages(): MessageLog;

    public function users(): UserStore;

    public function rateLimiter(): RateLimiter;

    public function authSessions(): AuthSessionStore;

    public function auditLog(): AuditLog;

    /** Either 'sqlite' or 'json'. */
    public function backend(): string;
}
