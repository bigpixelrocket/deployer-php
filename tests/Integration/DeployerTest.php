<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Deployer;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

describe('Deployer Symfony Console application', function () {

    //
    // Core functionality

    it('initializes properly with version detection and command registration', function () {
        // ARRANGE & ACT
        $container = new Container();
        $app = $container->build(Deployer::class);
        $version = $app->getVersion();
        $command = $app->find('hello');
        $commands = $app->all();
        $commandNames = array_keys($commands);

        // ASSERT - Application setup
        expect($app->getName())->toBe('Deployer')
            ->and($app->getHelp())->toBe('') // Custom help override
            ->and(strlen($version))->toBeGreaterThan(0)  // Valid version format
            ->and($commandNames)->toContain('hello');

        // ASSERT - Command can execute successfully
        expect($command->getName())->toBe('hello');
    });

    //
    // Banner Display & Console Output

    it('displays complete banner with version and branding when running commands', function () {
        // ARRANGE
        $container = new Container();
        $app = $container->build(Deployer::class);
        $input = new ArrayInput(['command' => 'list']);
        $output = new BufferedOutput();
        $version = $app->getVersion();

        // ACT
        $app->doRun($input, $output);
        $outputContent = $output->fetch();

        // ASSERT - Complete banner verification
        expect($outputContent)
            ->toContain('┌┬┐┌─┐┌─┐┬  ┌─┐┬ ┬┌─┐┬─┐') // ASCII header
            ->toContain(' ││├┤ ├─┘│  │ │└┬┘├┤ ├┬┘') // ASCII middle
            ->toContain('─┴┘└─┘┴  ┴─┘└─┘ ┴ └─┘┴└─PHP') // ASCII footer
            ->toContain('The Server Provisioning & Deployment Tool for PHP') // Tagline
            ->toContain('Support this project on GitHub ♥') // Support message
            ->toContain('https://github.com/bigpixelrocket/deployer-php') // URL
            ->toContain($version) // Version in banner
            ->toContain('Environment:'); // Environment status display
    });

    //
    // Application Execution & Edge Cases

    it('executes commands and handles various scenarios', function (string $command, int $expectedExitCode, array $expectedContent, ?string $expectedException) {
        // ARRANGE
        $container = new Container();
        $app = $container->build(Deployer::class);
        $input = new ArrayInput(['command' => $command]);
        $output = new BufferedOutput();

        // ACT & ASSERT
        if ($expectedException) {
            expect(fn () => $app->doRun($input, $output))
                ->toThrow($expectedException);
        } else {
            $exitCode = $app->doRun($input, $output);
            $outputContent = $output->fetch();

            expect($exitCode)->toBe($expectedExitCode);

            foreach ($expectedContent as $content) {
                expect($outputContent)->toContain($content);
            }
        }
    })->with([
        'hello command execution' => ['hello', 0, ['Hello', '┌┬┐┌─┐┌─┐'], null],
        'list command execution' => ['list', 0, ['Available commands', '┌┬┐┌─┐┌─┐'], null],
        'invalid command handling' => ['non-existent-command', 0, [], \Symfony\Component\Console\Exception\CommandNotFoundException::class],
    ]);

    it('maintains state consistency across multiple executions', function () {
        // ARRANGE
        $container = new Container();
        $app = $container->build(Deployer::class);
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
