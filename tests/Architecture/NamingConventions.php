<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;

it('PHP classes are defined in files matching their class name', function () {
    $appPath = base_path('app');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath));

    $errors = [];
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (! preg_match('/namespace\s+([^;\s]+)\s*;/', $contents, $nsMatch)) {
            continue;
        }

        if (! preg_match('/class\s+([A-Za-z0-9_]+)/', $contents, $classMatch)) {
            continue;
        }

        $namespace = trim($nsMatch[1]);
        $className = $classMatch[1];
        $expectedFile = $className . '.php';
        $actualFile = $file->getBasename();

        if ($expectedFile !== $actualFile) {
            $errors[] = sprintf('%s: expected file %s for class %s\\%s', $file->getPathname(), $expectedFile, $namespace, $className);

            continue;
        }

        // Additional Laravel-specific checks
        if (str_contains($namespace, 'Http\\Controllers') && ! str_ends_with($className, 'Controller')) {
            $errors[] = sprintf('%s: controller class should be suffixed with Controller', $file->getPathname());
        }

        if (str_contains($namespace, 'Http\\Livewire') && ! str_ends_with($className, 'Component') && ! str_ends_with($className, 'Livewire')) {
            $errors[] = sprintf('%s: livewire class should be suffixed with Component or Livewire', $file->getPathname());
        }
    }

    if (! empty($errors)) {
        throw new AssertionFailedError("Naming convention violations:\n" . implode("\n", $errors));
    }
});
