<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Server\ServerAddCommand;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createServerAddCommandTester(?SSHService $sshService = null): CommandTester
{
    $container = mockCommandContainer(ssh: $sshService);
    $command = $container->build(ServerAddCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('ServerAddCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('adds server with all options provided non-interactively', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all options for fully non-interactive execution
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'production-web',
            '--host' => '192.168.1.100',
            '--port' => '2222',
            '--username' => 'deployer',
            '--private-key-path' => '~/.ssh/prod_key',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('Server added successfully')
            ->and($output)->toContain('Run non-interactively:')
            ->and($output)->toContain('server:add')
            ->and($output)->toContain('production-web')
            ->and($output)->toContain('192.168.1.100');
    });

    it('adds server with minimal options using defaults', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options to avoid prompting
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'web1',
            '--host' => '192.168.1.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Server added successfully')
            ->and($output)->toContain('Port:')
            ->and($output)->toContain('22')
            ->and($output)->toContain('User:')
            ->and($output)->toContain('root');
    });

    it('adds server with successful SSH connection test', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'test-server',
            '--host' => '10.0.0.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => false,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('SSH connection successful')
            ->and($output)->toContain('Server added successfully');
    });

    it('adds server with skip flag bypassing SSH test', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(false);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'untested-server',
            '--host' => '192.168.1.50',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('⚠')
            ->and($output)->toContain('Skipping SSH connection check')
            ->and($output)->toContain('Server added successfully');
    });

    //
    // Error Scenarios
    // -------------------------------------------------------------------------------

    it('rejects invalid host with helpful error message', function (string $invalidHost) {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT & ASSERT - Provide all required options
        expect(fn () => $tester->execute([
            '--name' => 'test',
            '--host' => $invalidHost,
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]))->toThrow(\InvalidArgumentException::class, 'Invalid host');
    })->with([
        'underscore' => ['server_name'],
        'spaces' => ['my server'],
        'special chars' => ['server!@#'],
    ]);

    it('rejects invalid port with helpful error message', function (string $invalidPort) {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT & ASSERT - Provide all required options
        expect(fn () => $tester->execute([
            '--name' => 'test',
            '--host' => '192.168.1.1',
            '--port' => $invalidPort,
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]))->toThrow(\InvalidArgumentException::class, 'between 1 and 65535');
    })->with([
        'zero' => ['0'],
        'negative' => ['-1'],
        'too high' => ['65536'],
        'way too high' => ['100000'],
    ]);

    it('prevents duplicate server names', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Add first server (capture output)
        ob_start();
        $tester->execute([
            '--name' => 'duplicate-name',
            '--host' => '192.168.1.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ACT - Try to add duplicate (capture output)
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'duplicate-name',
            '--host' => '192.168.1.2',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('Failed to add server')
            ->and($output)->toContain('duplicate-name');
    });

    it('handles SSH connection failure with troubleshooting tips', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(false);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'unreachable',
            '--host' => '192.168.1.99',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => false,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('Common issues:')
            ->and($output)->toContain('Check that the server is accessible')
            ->and($output)->toContain('Verify SSH is running')
            ->and($output)->toContain('Tip:')
            ->and($output)->toContain('--skip');
    });

    it('saves server when confirmation is given', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'confirmed-server',
            '--host' => '192.168.1.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('Server added successfully');
    });

    //
    // Inventory Persistence
    // -------------------------------------------------------------------------------

    it('persists server data to inventory correctly', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $container = mockCommandContainer(ssh: $sshService);
        $command = $container->build(ServerAddCommand::class);
        $tester = new CommandTester($command);

        // ACT - Provide all required options
        ob_start();
        $tester->execute([
            '--name' => 'persisted-server',
            '--host' => '10.20.30.40',
            '--port' => '8022',
            '--username' => 'admin',
            '--private-key-path' => '~/.ssh/admin_key',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT - Verify server persisted in repository
        $repository = $container->build(ServerRepository::class);
        $server = $repository->findByName('persisted-server');

        expect($server)->not->toBeNull()
            ->and($server->name)->toBe('persisted-server')
            ->and($server->host)->toBe('10.20.30.40')
            ->and($server->port)->toBe(8022)
            ->and($server->username)->toBe('admin')
            ->and($server->privateKeyPath)->toBe('~/.ssh/admin_key');
    });

    it('displays complete server information before saving', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options
        ob_start();
        $tester->execute([
            '--name' => 'display-test',
            '--host' => 'example.com',
            '--port' => '22',
            '--username' => 'deployer',
            '--private-key-path' => '~/.ssh/key',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($output)->toContain('Name:')
            ->and($output)->toContain('display-test')
            ->and($output)->toContain('Host:')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('Port:')
            ->and($output)->toContain('22')
            ->and($output)->toContain('User:')
            ->and($output)->toContain('deployer')
            ->and($output)->toContain('Key:')
            ->and($output)->toContain('~/.ssh/key');
    });

    it('shows default SSH key path when not provided', function () {
        // ARRANGE
        $sshService = mockSSHServiceWithBehavior(true);
        $tester = createServerAddCommandTester($sshService);

        // ACT - Provide all required options except private-key-path to test default
        ob_start();
        $tester->execute([
            '--name' => 'default-key',
            '--host' => '192.168.1.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
            '--skip' => true,
            '--yes' => true,
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($output)->toContain('Key:')
            ->and($output)->toContain('default')
            ->and($output)->toContain('~/.ssh/id_ed25519')
            ->and($output)->toContain('~/.ssh/id_rsa');
    });
});
