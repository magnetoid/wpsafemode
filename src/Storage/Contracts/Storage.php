<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

interface Storage
{
    public function config(): ConfigStore;

    public function sessions(): SessionRepo;

    public function messages(): MessageLog;

    public function users(): UserStore;

    public function rateLimiter(): RateLimiter;

    public function authSessions(): AuthSessionStore;

    /** Either 'sqlite' or 'json'. */
    public function backend(): string;
}
