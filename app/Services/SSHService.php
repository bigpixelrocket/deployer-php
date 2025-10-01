<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Symfony\Component\Filesystem\Filesystem;

/**
 * SSH and SFTP operations for remote server management.
 *
 * Provides connectivity testing, command execution, and file transfer capabilities.
 * All operations are stateless - connections are created and destroyed per operation.
 *
 * @example
 * // Test SSH connectivity
 * $ssh->assertCanConnect('example.com', 22, 'deployer');
 * $ssh->assertCanConnect('example.com', 22, 'deployer', '~/.ssh/custom_key');
 *
 * // Execute single commands
 * $result = $ssh->executeCommand('example.com', 22, 'deployer', 'uptime');
 * echo $result['output'];     // "15:30:01 up 42 days, 3:14, 1 user..."
 * echo $result['exit_code'];  // 0
 *
 * // Execute bash scripts
 * $result = $ssh->executeScript('example.com', 22, 'deployer', './scripts/deploy.sh');
 * if ($result['exit_code'] === 0) {
 *     echo "Deployment successful";
 * }
 *
 * // Upload files to remote server
 * $ssh->uploadFile('example.com', 22, 'deployer', './local.txt', '/remote/path/file.txt');
 *
 * // Download files from remote server
 * $ssh->downloadFile('example.com', 22, 'deployer', '/remote/config.yml', './local-config.yml');
 */
class SSHService
{
    public function __construct(
        private readonly EnvService $envService,
        private readonly Filesystem $filesystem,
    ) {
    }

    //
    // Public API
    // -------------------------------------------------------------------------------

    /**
     * Assert that SSH connection and authentication can be established.
     *
     * @throws \RuntimeException When connection or authentication fails
     */
    public function assertCanConnect(string $host, int $port, string $username, ?string $privateKeyPath = null): void
    {
        $ssh = $this->createConnection($host, $port, $username, $privateKeyPath);
        $this->disconnect($ssh);
    }

    /**
     * Execute a command on the remote server and return its output.
     *
     * @return array{output: string, exit_code: int}
     *
     * @throws \RuntimeException When connection, authentication, or command execution fails
     */
    public function executeCommand(string $host, int $port, string $username, string $command, ?string $privateKeyPath = null): array
    {
        $ssh = $this->createConnection($host, $port, $username, $privateKeyPath);

        try {
            $output = $ssh->exec($command);
            $exitCode = (int) $ssh->getExitStatus();

            return [
                'output' => is_string($output) ? $output : '',
                'exit_code' => $exitCode,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error executing command on {$host}: " . $e->getMessage(), previous: $e);
        } finally {
            $this->disconnect($ssh);
        }
    }

    /**
     * Execute a local bash script file on the remote server.
     *
     * @return array{output: string, exit_code: int}
     *
     * @throws \RuntimeException When script file cannot be read or execution fails
     */
    public function executeScript(string $host, int $port, string $username, string $scriptPath, ?string $privateKeyPath = null): array
    {
        if (!$this->filesystem->exists($scriptPath)) {
            throw new \RuntimeException("Script file does not exist: {$scriptPath}");
        }

        $scriptContents = $this->filesystem->readFile($scriptPath);

        $ssh = $this->createConnection($host, $port, $username, $privateKeyPath);

        try {
            // Execute script contents through bash using heredoc
            $command = "bash <<'DEPLOYER_SCRIPT_EOF'\n{$scriptContents}\nDEPLOYER_SCRIPT_EOF";
            $output = $ssh->exec($command);
            $exitCode = (int) $ssh->getExitStatus();

            return [
                'output' => is_string($output) ? $output : '',
                'exit_code' => $exitCode,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error executing script {$scriptPath} on {$host}: " . $e->getMessage(), previous: $e);
        } finally {
            $this->disconnect($ssh);
        }
    }

    /**
     * Upload a local file to the remote server via SFTP.
     *
     * @throws \RuntimeException When file operations fail
     */
    public function uploadFile(string $host, int $port, string $username, string $localPath, string $remotePath, ?string $privateKeyPath = null): void
    {
        if (!$this->filesystem->exists($localPath)) {
            throw new \RuntimeException("Local file does not exist: {$localPath}");
        }

        $sftp = $this->createSFTPConnection($host, $port, $username, $privateKeyPath);

        try {
            $contents = $this->filesystem->readFile($localPath);

            $uploaded = $sftp->put($remotePath, $contents);
            if (!$uploaded) {
                throw new \RuntimeException("Error uploading file to {$remotePath} on {$host}");
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error uploading file to {$remotePath} on {$host}: " . $e->getMessage(), previous: $e);
        } finally {
            $this->disconnect($sftp);
        }
    }

    /**
     * Download a remote file from the server via SFTP.
     *
     * @throws \RuntimeException When file operations fail
     */
    public function downloadFile(string $host, int $port, string $username, string $remotePath, string $localPath, ?string $privateKeyPath = null): void
    {
        $sftp = $this->createSFTPConnection($host, $port, $username, $privateKeyPath);

        try {
            $contents = $sftp->get($remotePath);
            if ($contents === false) {
                throw new \RuntimeException("Error downloading file from {$remotePath} on {$host}");
            }

            $this->filesystem->dumpFile($localPath, is_string($contents) ? $contents : '');
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error downloading file from {$remotePath} on {$host}: " . $e->getMessage());
        } finally {
            $this->disconnect($sftp);
        }
    }

    //
    // Connection Management
    // -------------------------------------------------------------------------------

    /**
     * Create and authenticate an SSH connection.
     *
     * @throws \RuntimeException When connection or authentication fails
     */
    private function createConnection(string $host, int $port, string $username, ?string $privateKeyPath): SSH2
    {
        $key = $this->loadPrivateKey($privateKeyPath);

        try {
            $ssh = new SSH2($host, $port);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error initiating SSH connection to {$host}:{$port}: " . $e->getMessage());
        }

        try {
            $loggedIn = $ssh->login($username, $key);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error authenticating SSH for {$username}@{$host}: " . $e->getMessage());
        }

        if ($loggedIn !== true) {
            throw new \RuntimeException("SSH authentication failed for {$username}@{$host}. Check username and key permissions");
        }

        return $ssh;
    }

    /**
     * Create and authenticate an SFTP connection.
     *
     * @throws \RuntimeException When connection or authentication fails
     */
    private function createSFTPConnection(string $host, int $port, string $username, ?string $privateKeyPath): SFTP
    {
        $key = $this->loadPrivateKey($privateKeyPath);

        try {
            $sftp = new SFTP($host, $port);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error initiating SFTP connection to {$host}:{$port}: " . $e->getMessage());
        }

        try {
            $loggedIn = $sftp->login($username, $key);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error authenticating SFTP for {$username}@{$host}: " . $e->getMessage());
        }

        if ($loggedIn !== true) {
            throw new \RuntimeException("SFTP authentication failed for {$username}@{$host}. Check username and key permissions");
        }

        return $sftp;
    }

    /**
     * Disconnect from remote server (best-effort, ignores errors).
     */
    private function disconnect(SSH2|SFTP $connection): void
    {
        try {
            $connection->disconnect();
        } catch (\Throwable) {
            // Ignore disconnect errors
        }
    }

    //
    // Private Key Management
    // -------------------------------------------------------------------------------

    /**
     * Load and validate private key from resolved path.
     *
     * @throws \RuntimeException When key cannot be found, read, or parsed
     */
    private function loadPrivateKey(?string $privateKeyPath): PrivateKey
    {
        $resolvedKeyPath = $this->resolvePrivateKeyPath($privateKeyPath);

        if ($resolvedKeyPath === null) {
            throw new \RuntimeException('No SSH private key found. Provide a key path or place a key at ~/.ssh/id_ed25519 or ~/.ssh/id_rsa');
        }

        if (!$this->filesystem->exists($resolvedKeyPath)) {
            throw new \RuntimeException("SSH key does not exist: {$resolvedKeyPath}");
        }

        $keyContents = $this->filesystem->readFile($resolvedKeyPath);

        try {
            $key = PublicKeyLoader::load($keyContents);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Error parsing SSH private key at {$resolvedKeyPath}: " . $e->getMessage());
        }

        if (!$key instanceof PrivateKey) {
            throw new \RuntimeException("File at {$resolvedKeyPath} is not a valid private key");
        }

        return $key;
    }

    /**
     * Resolve a usable private key path.
     *
     * Priority order:
     * 1. Provided path (with ~ expansion)
     * 2. ~/.ssh/id_ed25519
     * 3. ~/.ssh/id_rsa
     */
    private function resolvePrivateKeyPath(?string $path): ?string
    {
        $candidates = [];

        // User-provided path takes priority
        if (is_string($path) && $path !== '') {
            $candidates[] = $this->expandHomePath($path);
        }

        // Default SSH key locations
        $home = $this->envService->get('HOME', required: false);
        if ($home !== null && $home !== '') {
            $home = rtrim($home, '/');
            $candidates[] = $home.'/.ssh/id_ed25519';
            $candidates[] = $home.'/.ssh/id_rsa';
        }

        // Return first existing candidate
        foreach ($candidates as $candidate) {
            if ($this->filesystem->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Expand leading tilde (~) to user's home directory.
     */
    private function expandHomePath(string $path): string
    {
        if ($path === '' || $path[0] !== '~') {
            return $path;
        }

        $home = $this->envService->get('HOME', required: false);
        if ($home === null || $home === '') {
            return $path;
        }

        return $home.substr($path, 1);
    }
}
