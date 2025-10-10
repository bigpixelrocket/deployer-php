<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\FilesystemService;
use Symfony\Component\Filesystem\Filesystem;

describe('FilesystemService', function () {
    //
    // Symfony Filesystem Wrappers (Delegation Tests)
    // -------------------------------------------------------------------------------

    it('delegates to Symfony Filesystem correctly', function (string $method, array $args, mixed $expected) {
        // ARRANGE
        $delegationVerified = false;
        $fs = new class ($delegationVerified, $method, $args, $expected) extends Filesystem {
            public function __construct(
                private bool &$verified,
                private readonly string $expectedMethod,
                private readonly array $expectedArgs,
                private readonly mixed $returnValue
            ) {
            }

            public function exists(string|iterable $files): bool
            {
                if ($this->expectedMethod === 'exists') {
                    $this->verified = $this->expectedArgs === [$files];
                    return $this->returnValue;
                }
                return false;
            }

            public function readFile(string $filename): string
            {
                if ($this->expectedMethod === 'readFile') {
                    $this->verified = $this->expectedArgs === [$filename];
                    return $this->returnValue;
                }
                return '';
            }

            public function dumpFile(string $filename, $content): void
            {
                if ($this->expectedMethod === 'dumpFile') {
                    $this->verified = $this->expectedArgs === [$filename, $content];
                }
            }
        };

        $service = new FilesystemService($fs);

        // ACT
        $result = match ($method) {
            'exists' => $service->exists(...$args),
            'readFile' => $service->readFile(...$args),
            'dumpFile' => $service->dumpFile(...$args),
        };

        // ASSERT
        expect($delegationVerified)->toBeTrue('Method delegation verified');
        if ($method !== 'dumpFile') {
            expect($result)->toBe($expected);
        }
    })->with([
        'exists method' => ['exists', ['/test/path'], true],
        'readFile method' => ['readFile', ['/test/file.txt'], 'file contents'],
        'dumpFile method' => ['dumpFile', ['/test/file.txt', 'content'], null],
    ]);

    //
    // Gap-Filling Methods (Business Logic Tests)
    // -------------------------------------------------------------------------------

    it('gets current working directory', function () {
        // ARRANGE
        $filesystem = new Filesystem();
        $service = new FilesystemService($filesystem);

        // ACT
        $result = $service->getCwd();

        // ASSERT - Should return a valid directory path
        expect($result)->toBeString()
            ->and($service->isDirectory($result))->toBeTrue('getCwd should return valid directory');
    });

    it('checks if path is directory', function (string $path, bool $expected) {
        // ARRANGE
        $filesystem = new Filesystem();
        $service = new FilesystemService($filesystem);

        // ACT
        $result = $service->isDirectory($path);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        'existing directory' => [__DIR__, true],
        'existing file' => [__FILE__, false],
        'nonexistent path' => ['/nonexistent/path/that/does/not/exist', false],
    ]);

    it('gets parent directory', function (string $path, int $levels, string $expected) {
        // ARRANGE
        $filesystem = new Filesystem();
        $service = new FilesystemService($filesystem);

        // ACT
        $result = $service->getParentDirectory($path, $levels);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        'single level' => ['/path/to/file.txt', 1, '/path/to'],
        'two levels' => ['/path/to/file.txt', 2, '/path'],
        'three levels' => ['/path/to/deep/file.txt', 3, '/path'],
    ]);

    it('throws exception for invalid parent directory levels', function () {
        // ARRANGE
        $filesystem = new Filesystem();
        $service = new FilesystemService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->getParentDirectory('/path', 0))
            ->toThrow(\InvalidArgumentException::class, 'Levels must be at least 1');
    });
});
