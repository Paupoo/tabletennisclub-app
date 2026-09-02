<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;

it('public and protected methods declare a return type', function (): void {
    $appPath = base_path('app');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath));

    $errors = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (! preg_match('/namespace\s+([^;\s]+)\s*;/', $contents, $nsMatch)) {
            continue;
        }
        if (! preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
            continue;
        }

        $namespace = trim($nsMatch[1]);
        $className = $classMatch[1];
        $fqcn = $namespace . '\\' . $className;

        // Try to autoload the class; if it fails, require the file (best-effort)
        if (! class_exists($fqcn)) {
            try {
                require_once $file->getPathname();
            } catch (Throwable) {
                // skip files that cannot be loaded
                continue;
            }
        }

        if (! class_exists($fqcn)) {
            continue;
        }

        try {
            $ref = new ReflectionClass($fqcn);
        } catch (ReflectionException) {
            continue;
        }

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Skip methods declared in traits or external classes
            if ($method->getDeclaringClass()->getName() !== $ref->getName()) {
                continue;
            }

            if (! $method->hasReturnType()) {
                $errors[] = sprintf('%s::%s has no return type', $fqcn, $method->getName());
            }
        }
    }

    if ($errors !== []) {
        throw new AssertionFailedError("Methods without return types:\n" . implode("\n", $errors));
    }
});
