<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

use Bigpixelrocket\DeployerPHP\Services\SSHService;

/**
 * Mock SSHService that simulates connection behavior without network calls.
 *
 * Allows testing SSH-dependent code paths without actual SSH connections:
 * - Configurable success/failure for connection attempts
 * - Simulated command execution with controllable output
 * - File upload/download simulation
 *
 * @example
 *   // Simulate successful connection
 *   $ssh = new MockSSHService(canConnect: true);
 *   $ssh->assertCanConnect('host', 22, 'user'); // No exception
 *
 * @example
 *   // Simulate connection failure
 *   $ssh = new MockSSHService(canConnect: false);
 *   $ssh->assertCanConnect('host', 22, 'user'); // Throws RuntimeException
 */
class MockSSHService extends SSHService
{
    public function __construct(private readonly bool $canConnect)
    {
        // Skip parent constructor to avoid dependency injection
    }

    public function assertCanConnect(
        string $host,
        int $port,
        string $username,
        ?string $privateKeyPath = null
    ): void {
        if (!$this->canConnect) {
            throw new \RuntimeException('Failed to connect to SSH server');
        }
        // Success - no exception thrown
    }

    public function executeCommand(
        string $host,
        int $port,
        string $username,
        string $command,
        ?string $privateKeyPath = null
    ): array {
        return ['output' => 'command output', 'exit_code' => 0];
    }

    public function executeScript(
        string $host,
        int $port,
        string $username,
        string $scriptPath,
        ?string $privateKeyPath = null
    ): array {
        return ['output' => 'script output', 'exit_code' => 0];
    }

    public function uploadFile(
        string $host,
        int $port,
        string $username,
        string $localPath,
        string $remotePath,
        ?string $privateKeyPath = null
    ): void {
        // Mock implementation - no actual upload
    }

    public function downloadFile(
        string $host,
        int $port,
        string $username,
        string $remotePath,
        string $localPath,
        ?string $privateKeyPath = null
    ): void {
        // Mock implementation - no actual download
    }
}
