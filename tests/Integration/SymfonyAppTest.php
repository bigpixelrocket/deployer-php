<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\SymfonyApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe('SymfonyApp', function () {

    //
    // Core functionality

    it('initializes properly with version detection and command registration', function () {
        // ARRANGE & ACT
        $container = new Container();
        $app = $container->build(SymfonyApp::class);
        $name = $app->getName();
        $version = $app->getVersion();
        $command = $app->find('hello');
        $commands = $app->all();
        $commandNames = array_keys($commands);

        // ASSERT - SymfonyApp setup
        expect(strlen($name))->toBeGreaterThan(0)
            ->and(strlen($version))->toBeGreaterThan(0)
            ->and($app->getHelp())->toBe('')
            ->and($commandNames)->toContain('hello');

        // ASSERT - Command can execute successfully
        expect($command->getName())->toBe('hello');
    });

    //
    // Banner display

    it('displays banner with branding elements', function (array $expectedBannerElements) {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        $input = new ArrayInput(['command' => 'list']);

        $output = new BufferedOutput();
        $output->setDecorated(false);

        $version = $app->getVersion();

        // ACT
        $app->doRun($input, $output);
        $outputContent = $output->fetch();

        // ASSERT - Banner elements verification
        foreach ($expectedBannerElements as $element) {
            if ($element === 'VERSION_LINE') {
                expect($outputContent)->toContain('─┴┘└─┘┴  ┴─┘└─┘ ┴ └─┘┴└─PHP '.$version);
            } else {
                expect($outputContent)->toContain($element);
            }
        }
    })->with([
        'banner' => [[
            '┌┬┐┌─┐┌─┐┬  ┌─┐┬ ┬┌─┐┬─┐', // ASCII art line 1
            ' ││├┤ ├─┘│  │ │└┬┘├┤ ├┬┘', // ASCII art line 2
            'VERSION_LINE', // Dynamic version line
            'The Server & Site Deployment Tool for PHP',
        ]]
    ]);

    //
    // Command Execution

    it('executes valid commands successfully', function (string $command, array $expectedContent) {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        $input = new ArrayInput(['command' => $command]);

        $output = new BufferedOutput();
        $output->setDecorated(false);

        // ACT
        $exitCode = $app->doRun($input, $output);
        $outputContent = $output->fetch();

        // ASSERT
        expect($exitCode)->toBe(0);
        foreach ($expectedContent as $content) {
            expect($outputContent)->toContain($content);
        }
    })->with([
        'hello command' => ['hello', ['Hello', '┌┬┐┌─┐┌─┐', 'Environment:', 'Inventory:']], // Banner + greeting + status
        'list command' => ['list', ['Available commands', '┌┬┐┌─┐┌─┐']], // Banner + command list
    ]);

    it('handles invalid commands gracefully', function (string $command, string $expectedError) {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        $input = new ArrayInput(['command' => $command]);

        $output = new BufferedOutput();
        $output->setDecorated(false);

        // ACT & ASSERT
        try {
            $exitCode = $app->doRun($input, $output);
            $outputContent = $output->fetch();
        } catch (CommandNotFoundException $e) {
            $exitCode = 1;
            $outputContent = $e->getMessage();
        }

        expect($exitCode)->toBe(1)
            ->and($outputContent)->toContain($expectedError);
    })->with([
        'non-existent command' => ['non-existent-command', 'Command "non-existent-command" is not defined'],
        'typo command' => ['helo', 'Command "helo" is not defined'],
    ]);

    it('maintains state consistency across multiple executions', function () {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        $input = new ArrayInput(['command' => 'list']);

        $output = new BufferedOutput();
        $output->setDecorated(false);

        // ACT - Multiple runs
        $exitCode1 = $app->doRun($input, $output);
        $output1 = $output->fetch();

        $exitCode2 = $app->doRun($input, $output);
        $output2 = $output->fetch();

        // ASSERT - Consistent behavior
        expect($exitCode1)->toBe(Command::SUCCESS)
            ->and($exitCode2)->toBe(Command::SUCCESS)
            ->and($output1)->toContain('┌┬┐┌─┐┌─┐')
            ->and($output2)->toContain('┌┬┐┌─┐┌─┐');
    });

    //
    // Custom Input Definition

    it('exposes only custom-defined input options', function () {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        // ACT
        $definition = $app->getDefinition();
        $availableOptions = array_keys($definition->getOptions());

        // ASSERT
        expect($availableOptions)->toContain('help')
            ->and($availableOptions)->toContain('version')
            ->and($availableOptions)->toContain('ansi')
            ->and($availableOptions)->not->toContain('quiet')
            ->and($availableOptions)->not->toContain('verbose')
            ->and($availableOptions)->not->toContain('no-interaction');
    });

    it('exits early when --version flag is provided', function () {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        $input = new ArrayInput(['--version' => true]);

        $output = new BufferedOutput();
        $output->setDecorated(false);

        // ACT
        $exitCode = $app->doRun($input, $output);
        $outputContent = $output->fetch();

        // ASSERT
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($outputContent)->toContain('┌┬┐┌─┐┌─┐')
            ->and($outputContent)->not->toContain('Available commands');
    });

    it('always displays banner regardless of output settings', function () {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);

        $input = new ArrayInput(['command' => 'list']);

        $output = new BufferedOutput();
        $output->setDecorated(false);

        // ACT
        $app->doRun($input, $output);
        $outputContent = $output->fetch();

        // ASSERT
        expect($outputContent)->toContain('┌┬┐┌─┐┌─┐')
            ->and($outputContent)->toContain('The Server & Site Deployment Tool for PHP');
    });

});
