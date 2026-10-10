<?php

namespace Doppar\Flarion\Tests\Support;

use Phaseolies\DI\Container;

class MockContainer extends Container
{
    protected string $basePath = __DIR__ . '/../..';

    public function basePath(string $path = ''): string
    {
        if ($path === '') {
            return $this->basePath;
        }

        $normalizedPath = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    public function storagePath(string $path = ''): string
    {
        $base = sys_get_temp_dir() . '/flarion_test_storage';

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }

    public function runningInConsole(): bool
    {
        return true;
    }
}
