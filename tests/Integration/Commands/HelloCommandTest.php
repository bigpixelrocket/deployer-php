<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\HelloCommand;
use Bigpixelrocket\DeployerPHP\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../TestHelpers.php';

function createCommandTester(): CommandTester
{
    $container = new Container();
    $command = $container->build(HelloCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('HelloCommand', function () {
    beforeEach(function () {
        foreach (['USER', 'USERNAME'] as $key) {
            setEnv($key, null);
        }
    });

    it('displays correct greeting based on environment', function (array $env, string $expectedMessage) {
        // ARRANGE
        foreach ($env as $key => $value) {
            setEnv($key, $value);
        }
        $tester = createCommandTester();

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($tester->getDisplay())->toContain($expectedMessage);

        // CLEANUP
        foreach (array_keys($env) as $key) {
            setEnv($key, null);
        }
    })->with([
        'USER variable' => [['USER' => 'johndoe'], 'Hello johndoe!'],
        'USERNAME variable' => [['USERNAME' => 'janedoe'], 'Hello janedoe!'],
        'USER wins over USERNAME' => [['USER' => 'primary', 'USERNAME' => 'secondary'], 'Hello primary!'],
        'defaults when empty' => [[], 'Hello there!'],
    ]);
});
