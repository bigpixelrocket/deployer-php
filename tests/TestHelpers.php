<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;

if (!function_exists('setEnv')) {
    /**
     * Set or unset environment variables for testing.
     */
    function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv("{$key}");
        } else {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

if (!function_exists('mockFilesystem')) {
    /**
     * Create a mock filesystem for testing with comprehensive error simulation.
     */
    function mockFilesystem(
        bool $exists = true,
        string $content = '',
        bool $throwOnRead = false,
        bool $throwOnMkdir = false,
        bool $throwOnDump = false
    ): Filesystem {
        return new class ($exists, $content, $throwOnRead, $throwOnMkdir, $throwOnDump) extends Filesystem {
            public function __construct(
                private readonly bool $exists,
                private readonly string $content,
                private readonly bool $throwOnRead,
                private readonly bool $throwOnMkdir,
                private readonly bool $throwOnDump
            ) {
            }

            public function exists(string|iterable $files): bool
            {
                return $this->exists;
            }

            public function readFile(string $filename): string
            {
                if ($this->throwOnRead) {
                    throw new \RuntimeException('Permission denied');
                }
                return $this->content;
            }

            public function mkdir($dirs, int $mode = 0777): void
            {
                if ($this->throwOnMkdir) {
                    throw new \Exception('Permission denied');
                }
            }

            public function dumpFile(string $filename, $content): void
            {
                if ($this->throwOnDump) {
                    throw new \Exception('Write failed');
                }
            }
        };
    }
}
