<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\SymfonyApp;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Exception\CommandNotFoundException;

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

    it('displays complete banner with branding elements', function (array $expectedBannerElements) {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);
        $input = new ArrayInput(['command' => 'list']);
        $output = new BufferedOutput();
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
        'complete banner' => [[
            '┌┬┐┌─┐┌─┐┬  ┌─┐┬ ┬┌─┐┬─┐', // ASCII art line 1
            ' ││├┤ ├─┘│  │ │└┬┘├┤ ├┬┘', // ASCII art line 2
            'VERSION_LINE', // Dynamic version line
            'The Server Provisioning & Deployment Tool for PHP',
            'Support this project on GitHub ♥',
            'https://github.com/bigpixelrocket/deployer-php',
            'Environment:'
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

        // ACT
        $exitCode = $app->doRun($input, $output);
        $outputContent = $output->fetch();

        // ASSERT
        expect($exitCode)->toBe(0);
        foreach ($expectedContent as $content) {
            expect($outputContent)->toContain($content);
        }
    })->with([
        'hello command' => ['hello', ['Hello', '┌┬┐┌─┐┌─┐']], // Banner + greeting
        'list command' => ['list', ['Available commands', '┌┬┐┌─┐┌─┐']], // Banner + command list
    ]);

    it('handles invalid commands gracefully', function (string $command, string $expectedError) {
        // ARRANGE
        $container = new Container();
        $app = $container->build(SymfonyApp::class);
        $input = new ArrayInput(['command' => $command]);
        $output = new BufferedOutput();

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

        // ACT - Multiple runs
        $exitCode1 = $app->doRun($input, $output);
        $output1 = $output->fetch();

        $exitCode2 = $app->doRun($input, $output);
        $output2 = $output->fetch();

        // ASSERT - Consistent behavior
        expect($exitCode1)->toBe(0)
            ->and($exitCode2)->toBe(0)
            ->and($output1)->toContain('┌┬┐┌─┐┌─┐')
            ->and($output2)->toContain('┌┬┐┌─┐┌─┐');
    });

});
