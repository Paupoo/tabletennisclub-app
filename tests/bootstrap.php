<?php

declare(strict_types=1);

// Pin the app base path to this project root so tests always use the correct
// storage/framework/views path, regardless of shell environment variables that
// may point to a git worktree (e.g. APP_BASE_PATH set by a subagent session).
$_ENV['APP_BASE_PATH'] = dirname(__DIR__);
$_SERVER['APP_BASE_PATH'] = dirname(__DIR__);

// Clear OPcache at process startup so Livewire compiled class files are always
// loaded fresh from disk — avoids stale bytecode when opcache.enable_cli=1.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

require __DIR__ . '/../vendor/autoload.php';
