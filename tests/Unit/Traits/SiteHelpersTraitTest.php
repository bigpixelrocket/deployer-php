<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('SiteHelpersTrait', function () {
    beforeEach(function () {
        $container = mockCommandContainer();
        $this->command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $this->tester = new CommandTester($this->command);
    });

    //
    // displaySiteDeets
    // -------------------------------------------------------------------------------

    it('displays git site information with server formatting', function (array $servers) {
        // ARRANGE
        $this->command->setTestMethod('displaySiteDeets', [
            new SiteDTO(
                domain: 'example.com',
                repo: 'git@github.com:user/repo.git',
                branch: 'main',
                servers: $servers
            ),
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Domain:')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('Type:')
            ->and($output)->toContain('Git')
            ->and($output)->toContain('Repo:')
            ->and($output)->toContain('git@github.com:user/repo.git')
            ->and($output)->toContain('Branch:')
            ->and($output)->toContain('main')
            ->and($output)->toContain('Servers:')
            ->and($output)->toContain(implode(', ', $servers));
    })->with([
        'two servers' => [['web1', 'web2']],
        'four servers' => [['web1', 'web2', 'web3', 'web4']],
    ]);

    it('displays local site information with server formatting', function (array $servers, string $domain) {
        // ARRANGE
        $this->command->setTestMethod('displaySiteDeets', [
            new SiteDTO(
                domain: $domain,
                repo: null,
                branch: null,
                servers: $servers
            ),
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Domain:')
            ->and($output)->toContain($domain)
            ->and($output)->toContain('Type:')
            ->and($output)->toContain('Local')
            ->and($output)->not->toContain('Repo:')
            ->and($output)->not->toContain('Branch:')
            ->and($output)->toContain('Servers:')
            ->and($output)->toContain(implode(', ', $servers));
    })->with([
        'single server' => [['web1'], 'single.com'],
        'multiple servers' => [['web1', 'web2', 'web3'], 'multi.com'],
    ]);

    //
    // selectServers (CLI validation)
    // -------------------------------------------------------------------------------

    it('validates server names in CLI option', function () {
        // ARRANGE
        $container = mockCommandContainer(
            inventoryData: ['servers' => [
                ['name' => 'web1', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
                ['name' => 'web2', 'host' => '192.168.1.2', 'port' => 22, 'username' => 'root'],
            ]]
        );
        $command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $command->setTestMethod('selectServers');

        // ACT & ASSERT
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--servers' => 'web1,web2']);

        expect($exitCode)->toBe(\Symfony\Component\Console\Command\Command::SUCCESS);
    });

    it('rejects non-existent server names from CLI option', function () {
        // ARRANGE
        $container = mockCommandContainer(
            inventoryData: ['servers' => [
                ['name' => 'web1', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
            ]]
        );
        $command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $command->setTestMethod('selectServers');

        // ACT & ASSERT
        $tester = new CommandTester($command);
        expect(fn () => $tester->execute(['--servers' => 'web1,non-existent']))
            ->toThrow(\RuntimeException::class, "Server 'non-existent' not found");
    });
});
