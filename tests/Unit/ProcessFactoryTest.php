<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\ProcessFactory;

describe('ProcessFactory', function () {
    beforeEach(function () {
        $this->factory = new ProcessFactory();
        $this->validCwd = __DIR__;
    });

    it('creates process with valid command and timeout configuration', function (?float $timeout, float $expected) {
        // ARRANGE
        $command = ['ls', '-la'];

        // ACT
        $process = $this->factory->create($command, $this->validCwd, $timeout);

        // ASSERT
        expect($process->getCommandLine())->toContain('ls')
            ->and($process->getWorkingDirectory())->toBe($this->validCwd)
            ->and($process->getTimeout())->toBe($expected);
    })->with([
        'default timeout' => [3.0, 3.0],
        'null timeout defaults to 3.0' => [null, 3.0],
    ]);

    it('creates process with custom timeout', function (float $timeout, ?float $expected) {
        // ARRANGE
        $command = ['echo', 'test'];

        // ACT
        $process = $this->factory->create($command, $this->validCwd, $timeout);

        // ASSERT
        expect($process->getTimeout())->toBe($expected);
    })->with([
        'short timeout' => [1.5, 1.5],
        'long timeout' => [10.0, 10.0],
        'zero timeout removes timeout' => [0.0, null],
    ]);

    it('throws exception when command is empty', function () {
        // ARRANGE
        $emptyCommand = [];

        // ACT & ASSERT
        expect(fn () => $this->factory->create($emptyCommand, $this->validCwd))
            ->toThrow(InvalidArgumentException::class, 'Process command cannot be empty');
    });

    it('throws exception for invalid working directories', function (string $invalidPath) {
        // ARRANGE
        $command = ['echo', 'test'];

        // ACT & ASSERT
        expect(fn () => $this->factory->create($command, $invalidPath))
            ->toThrow(InvalidArgumentException::class);
    })->with([
        'empty string' => [''],
        'non-existent path' => ['/this/path/does/not/exist'],
        'nonexistent directory' => ['/nonexistent/directory/path'],
        'file instead of directory' => [__FILE__],
    ]);
});
