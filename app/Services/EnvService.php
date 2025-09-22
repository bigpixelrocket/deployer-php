<?php

declare(strict_types=1);

namespace DeployerPlus\DeployerPHP\Services;

use Symfony\Component\Dotenv\Dotenv;

/**
 * Environment variable reader with fallback to .env file if value not found in environment variables.
 */
class EnvService
{
    /** @var array<string, string> */
    private array $dotenv = [];

    public function __construct()
    {
        $path = getcwd().'/.env';
        if (is_file($path) && is_readable($path)) {
            $dotenv = new Dotenv();
            $parsed = $dotenv->parse((string) file_get_contents($path), $path);
            foreach ($parsed as $k => $v) {
                if (is_string($k) && is_string($v)) {
                    $this->dotenv[$k] = $v;
                }
            }
        }
    }

    /**
     * Get the first non-empty value for the given key(s).
     *
     * @param  array<int, string>|string  $keys
     */
    public function get(array|string $keys, bool $required = true): ?string
    {
        $keysList = is_array($keys) ? $keys : [$keys];
        foreach ($keysList as $key) {
            $value = $_ENV[$key] ?? getenv($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }

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

}
