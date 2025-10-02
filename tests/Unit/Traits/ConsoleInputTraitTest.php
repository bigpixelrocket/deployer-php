<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('ConsoleInputTrait', function () {
    beforeEach(function () {
        $container = new Container();
        $this->command = new TestConsoleCommand($container, mockEnvService(true), mockInventoryService(true));
        $this->tester = new CommandTester($this->command);
    });

    //
    // getOptionOrPrompt

    it('returns option value and sets wasProvided to true when option provided', function () {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPrompt');

        // ACT
        $this->tester->execute(['--name' => 'production']);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Result: production')
            ->and($output)->toContain('Provided: true');
    });
});
