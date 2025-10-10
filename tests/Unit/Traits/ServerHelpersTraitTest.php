<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('ServerHelpersTrait', function () {
    beforeEach(function () {
        $container = mockCommandContainer();
        $this->command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $this->tester = new CommandTester($this->command);
    });

    //
    // displayServerInfo
    // -------------------------------------------------------------------------------

    it('displays server information with all fields', function () {
        // ARRANGE
        $this->command->setTestMethod('displayServerInfo', [
            new ServerDTO(
                name: 'production-web',
                host: '192.168.1.100',
                port: 2222,
                username: 'deployer',
                privateKeyPath: '~/.ssh/custom_key'
            ),
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Name:')
            ->and($output)->toContain('production-web')
            ->and($output)->toContain('Host:')
            ->and($output)->toContain('192.168.1.100')
            ->and($output)->toContain('Port:')
            ->and($output)->toContain('2222')
            ->and($output)->toContain('User:')
            ->and($output)->toContain('deployer')
            ->and($output)->toContain('Key:')
            ->and($output)->toContain('~/.ssh/custom_key');
    });

    it('displays default SSH key message when privateKeyPath is null', function () {
        // ARRANGE
        $this->command->setTestMethod('displayServerInfo', [
            new ServerDTO(name: 'test-server', host: '127.0.0.1'),
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Key:')
            ->and($output)->toContain('default')
            ->and($output)->toContain('~/.ssh/id_ed25519')
            ->and($output)->toContain('~/.ssh/id_rsa');
    });

    it('displays server info with default values', function () {
        // ARRANGE
        $this->command->setTestMethod('displayServerInfo', [
            new ServerDTO(name: 'minimal', host: 'example.com'),
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('minimal')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('22')
            ->and($output)->toContain('root');
    });
});
