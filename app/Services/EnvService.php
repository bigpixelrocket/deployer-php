<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Environment variable reader with .env file fallback.
 */
class EnvService
{
    /** @var array<string, string> */
    private array $dotenv = [];

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        $this->loadDotenvFile();
    }

    //
    // Public
    // -------------------------------------------------------------------------------

    /**
     * Get first non-empty value for given key(s).
     *
     * @param array<int, string>|string $keys
     */
    public function get(array|string $keys, bool $required = true): ?string
    {
        $keysList = is_array($keys) ? $keys : [$keys];

        foreach ($keysList as $key) {
            // Check environment variables first
            $value = $_ENV[$key] ?? getenv($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }

            // Check .env file fallback
            if (isset($this->dotenv[$key]) && $this->dotenv[$key] !== '') {
                return $this->dotenv[$key];
            }
        }

        if ($required) {
            $list = implode(', ', $keysList);
            $label = count($keysList) > 1 ? 'variables' : 'variable';
            throw new \RuntimeException("Missing environment {$label}: {$list}");
        }

        return null;
    }

    //
    // Private
    // -------------------------------------------------------------------------------

    /**
     * Load and parse .env file if it exists.
     */
    private function loadDotenvFile(): void
    {
        $envPath = rtrim((string) getcwd(), '/') . '/.env';

        if (!$this->filesystem->exists($envPath)) {
            return;
        }

        try {
            $content = $this->filesystem->readFile($envPath);
            $dotenv = new Dotenv();
            $parsed = $dotenv->parse($content, $envPath);

            foreach ($parsed as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $this->dotenv[$k] = $v;
                }
            }
        } catch (\Throwable) {
            // Silently ignore file reading errors
            $this->dotenv = [];
        }
    }
}
