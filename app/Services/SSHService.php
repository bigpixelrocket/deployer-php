<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * Minimal SSH connectivity checker.
 *
 * Responsible only for validating that a connection and authentication can be
 * established with the provided details. No command execution or I/O here.
 */
class SSHService
{
    /**
     * Assert that we can connect and authenticate to the remote server.
     *
     * @throws \RuntimeException When connection or authentication fails
     */
    public function assertCanConnect(string $host, int $port, string $username, ?string $privateKeyPath = null): void
    {
        $resolvedKeyPath = $this->resolvePrivateKeyPath($privateKeyPath);

        if ($resolvedKeyPath === null) {
            throw new \RuntimeException('No SSH private key found. Provide --key or place a key at ~/.ssh/id_rsa (or ~/.ssh/id_ed25519).');
        }

        if (!is_file($resolvedKeyPath) || !is_readable($resolvedKeyPath)) {
            throw new \RuntimeException("SSH key is not readable: {$resolvedKeyPath}");
        }

        $keyContents = (string) file_get_contents($resolvedKeyPath);

        try {
            $key = PublicKeyLoader::load($keyContents);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to load SSH private key: '.$e->getMessage(), previous: $e);
        }

        if (!$key instanceof PrivateKey) {
            throw new \RuntimeException('Provided key is not a valid private key.');
        }

        try {
            $ssh = new SSH2($host, $port);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to initiate SSH connection to {$host}: {$e->getMessage()}", previous: $e);
        }

        try {
            $loggedIn = $ssh->login($username, $key);
        } catch (\Throwable $e) {
            throw new \RuntimeException('SSH authentication error: '.$e->getMessage(), previous: $e);
        }

        if ($loggedIn !== true) {
            throw new \RuntimeException('SSH authentication failed. Check username and key permissions.');
        }

        // Best-effort disconnect; ignore errors
        try {
            $ssh->disconnect();
        } catch (\Throwable) {
            // no-op
        }
    }

    /**
     * Resolve a usable private key path. Preference order:
     * 1) Provided path (supports ~ expansion)
     * 2) ~/.ssh/id_ed25519
     * 3) ~/.ssh/id_rsa
     */
    private function resolvePrivateKeyPath(?string $path): ?string
    {
        $candidates = [];

        if (is_string($path) && $path !== '') {
            $candidates[] = $this->expandHomePath($path);
        }

        $home = rtrim((string) getenv('HOME'), '/');
        if ($home !== '') {
            // Default to id_rsa first, then try id_ed25519
            $candidates[] = $home.'/.ssh/id_rsa';
            $candidates[] = $home.'/.ssh/id_ed25519';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Expand a leading tilde in a filesystem path to the user's HOME.
     */
    private function expandHomePath(string $path): string
    {
        if ($path === '' || $path[0] !== '~') {
            return $path;
        }

        $home = (string) getenv('HOME');
        if ($home === '') {
            return $path; // best effort
        }

        return $home.substr($path, 1);
    }
}
