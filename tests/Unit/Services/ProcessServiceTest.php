<?php

declare(strict_types=1);


require_once __DIR__ . '/../../TestHelpers.php';

describe('ProcessService', function () {
    beforeEach(function () {
        $this->validCwd = __DIR__;
        $this->proc = mockProcessService();
    });

    it('executes process and returns result', function (?float $inputTimeout, ?float $expectedTimeout) {
        // ARRANGE
        $command = ['echo', 'test'];

        // ACT
        $process = $inputTimeout === null
            ? $this->proc->run($command, $this->validCwd)
            : $this->proc->run($command, $this->validCwd, $inputTimeout);

        // ASSERT
        expect($process->getCommandLine())->toContain('echo')
            ->and($process->getWorkingDirectory())->toBe($this->validCwd)
            ->and($process->getTimeout())->toBe($expectedTimeout)
            ->and($process->isSuccessful())->toBeTrue();
    })->with([
        'null timeout defaults to 3.0' => [null, 3.0],
        'explicit default timeout' => [3.0, 3.0],
        'short custom timeout' => [1.5, 1.5],
        'long custom timeout' => [10.0, 10.0],
        'zero timeout removes timeout' => [0.0, null],
    ]);

    it('throws exception when command is empty', function () {
        // ARRANGE
        $emptyCommand = [];

        // ACT & ASSERT
        expect(fn () => $this->proc->run($emptyCommand, $this->validCwd))
            ->toThrow(InvalidArgumentException::class, 'Process command cannot be empty');
    });

    it('throws exception for invalid working directories', function (string $invalidPath) {
        // ARRANGE
        $command = ['echo', 'test'];

        // ACT & ASSERT
        expect(fn () => $this->proc->run($command, $invalidPath))
            ->toThrow(InvalidArgumentException::class);
    })->with([
        'empty string' => [''],
        'non-existent path' => ['/this/path/does/not/exist'],
        'nonexistent directory' => ['/nonexistent/directory/path'],
        'file instead of directory' => [__FILE__],
    ]);
});
