<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Environment variable reader (first checks .env file then system environment variables)
 */
class EnvService
{
    /** @var array<string, string> */
    private array $dotenv = [];

    private string $envFileStatus = '';

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
            // Check .env file first
            if (isset($this->dotenv[$key]) && $this->dotenv[$key] !== '') {
                return $this->dotenv[$key];
            }

            // Check environment variables second
            $value = $_ENV[$key] ?? getenv($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if ($required) {
            $list = implode(', ', $keysList);
            $label = count($keysList) > 1 ? 'variables' : 'variable';
            throw new \RuntimeException("Missing environment {$label}: {$list}");
        }

        return null;
    }

    /**
     * Get the status of the .env file.
     */
    public function getEnvFileStatus(): string
    {
        return $this->envFileStatus;
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
            $envDir = dirname($envPath);
            $this->envFileStatus = "No .env file found at {$envDir}";
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

            $varCount = count($this->dotenv);
            $label = $varCount === 1 ? 'variable' : 'variables';
            $this->envFileStatus = "Loaded {$varCount} {$label} from {$envPath}";
        } catch (\Throwable) {
            $this->dotenv = [];
            $this->envFileStatus = "Error reading .env file from {$envPath}";
        }
    }
}
