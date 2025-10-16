<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Server\ServerAddCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createServerAddCommandTester(): CommandTester
{
    $container = mockCommandContainer();
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
        $tester = createServerAddCommandTester();

        // ACT - Provide all options for fully non-interactive execution
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'production-web',
            '--host' => '192.168.1.100',
            '--port' => '2222',
            '--username' => 'deployer',
            '--private-key-path' => '~/.ssh/prod_key',
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
        $tester = createServerAddCommandTester();

        // ACT - Provide all required options to avoid prompting
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'web1',
            '--host' => '192.168.1.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
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

    //
    // Error Scenarios
    // -------------------------------------------------------------------------------

    it('rejects invalid host with helpful error message', function (string $invalidHost) {
        // ARRANGE
        $tester = createServerAddCommandTester();

        // ACT
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'test',
            '--host' => $invalidHost,
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('valid');
    })->with([
        'underscore' => ['server_name'],
        'spaces' => ['my server'],
        'special chars' => ['server!@#'],
    ]);

    it('rejects invalid port with helpful error message', function (string $invalidPort) {
        // ARRANGE
        $tester = createServerAddCommandTester();

        // ACT
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'test',
            '--host' => '192.168.1.1',
            '--port' => $invalidPort,
            '--username' => 'root',
            '--private-key-path' => '',
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toMatch('/Port must be|between 1 and 65535/');
    })->with([
        'zero' => ['0'],
        'negative' => ['-1'],
        'too high' => ['65536'],
        'way too high' => ['100000'],
    ]);

    it('prevents duplicate server names', function () {
        // ARRANGE
        $tester = createServerAddCommandTester();

        // ACT - Add first server (capture output)
        ob_start();
        $tester->execute([
            '--name' => 'duplicate-name',
            '--host' => '192.168.1.1',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
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
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('already exists')
            ->and($output)->toContain('duplicate-name');
    });

    it('prevents duplicate server hosts', function () {
        // ARRANGE
        $tester = createServerAddCommandTester();

        // ACT - Add first server (capture output)
        ob_start();
        $tester->execute([
            '--name' => 'server-one',
            '--host' => '192.168.1.100',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
        ]);
        ob_end_clean();

        // ACT - Try to add different server with same host (capture output)
        ob_start();
        $exitCode = $tester->execute([
            '--name' => 'server-two',
            '--host' => '192.168.1.100',
            '--port' => '22',
            '--username' => 'root',
            '--private-key-path' => '',
        ]);
        ob_end_clean();

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('already used by server')
            ->and($output)->toContain('server-one');
    });
});
