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
        $this->originals = [];

        foreach (['USER', 'USERNAME'] as $key) {

            $value = getenv($key);

            $this->originals[$key] = $value === false ? null : $value;

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
    })->with([
        'USER variable' => [['USER' => 'johndoe'], 'Hello johndoe!'],
        'USERNAME variable' => [['USERNAME' => 'janedoe'], 'Hello janedoe!'],
        'USER wins over USERNAME' => [['USER' => 'primary', 'USERNAME' => 'secondary'], 'Hello primary!'],
        'defaults when empty' => [[], 'Hello there!'],
    ]);

    it('suppresses all output in quiet mode', function () {
        // ARRANGE
        setEnv('USER', 'testuser');
        $tester = createCommandTester();

        // ACT
        $exitCode = $tester->execute([], ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_QUIET]);

        // ASSERT
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($tester->getDisplay())->toBe('');
    });

    afterEach(function () {
        foreach ($this->originals as $key => $value) {
            setEnv($key, $value);
        }
    });
});
