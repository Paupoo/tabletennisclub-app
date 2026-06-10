<?php

declare(strict_types=1);

// Clear OPcache at process startup so Livewire compiled class files are always
// loaded fresh from disk — avoids stale bytecode when opcache.enable_cli=1.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

require __DIR__ . '/../vendor/autoload.php';
