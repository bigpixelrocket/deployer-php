<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Symfony\Component\Filesystem\Filesystem;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../TestHelpers.php';

function mockFilesystem(bool $exists = true, string $content = '', bool $throwError = false): Filesystem
{
    return new class ($exists, $content, $throwError) extends Filesystem {
        public function __construct(private readonly bool $exists, private readonly string $content, private readonly bool $error)
        {
        }
        public function exists(string|iterable $files): bool
        {
            return $this->exists;
        }
        public function readFile(string $filename): string
        {
            if ($this->error) {
                throw new \RuntimeException('Permission denied');
            }
            return $this->content;
        }
    };
}

//
// Unit tests
// -------------------------------------------------------------------------------

describe('EnvService', function () {
    beforeEach(function () {
        foreach (['TEST_KEY', 'API_KEY', 'KEY1', 'KEY2', 'MISSING_KEY', 'CUSTOM_KEY'] as $key) {
            setEnv($key, null);
        }
    });

    it('resolves environment variables from multiple sources with correct precedence', function ($env, $fileContent, $fileError, $keys, $expected) {
        // ARRANGE
        foreach ($env as $key => $value) {
            setEnv($key, $value);
        }
        $service = new EnvService(mockFilesystem(!empty($fileContent), $fileContent, $fileError), '.env');

        // ACT
        $result = $service->get($keys, false);

        // ASSERT
        expect($result)->toBe($expected);

        // CLEANUP
        foreach (array_keys($env) as $key) {
            setEnv($key, null);
        }
    })->with([
        // Single key scenarios
        [[], 'API_KEY=from_file', false, 'API_KEY', 'from_file'],                           // File only
        [['API_KEY' => 'from_env'], 'API_KEY=from_file', false, 'API_KEY', 'from_env'],    // Env wins
        [['API_KEY' => ''], 'API_KEY=from_file', false, 'API_KEY', 'from_file'],           // Empty env ignored
        [[], 'API_KEY=', false, 'API_KEY', null],                                          // Empty file ignored
        [[], '', false, 'API_KEY', null],                                                  // File missing
        [[], 'API_KEY=value', true, 'API_KEY', null],                                      // File read error

        // Multiple key scenarios (iterates in order, returns first found)
        [['KEY1' => 'from_env'], 'KEY2=file_val', false, ['KEY1', 'KEY2'], 'from_env'],   // First key in env
        [[], "KEY1=file_val\nKEY2=other", false, ['KEY1', 'KEY2'], 'file_val'],           // First key in file
        [[], '', false, ['KEY1', 'KEY2'], null],                                          // No keys found
    ]);

    it('handles required vs optional parameters', function ($keys, $required, $expectsException, $expectedMessage) {
        // ARRANGE
        $service = new EnvService(mockFilesystem(false), '.env');

        // ACT & ASSERT
        if ($expectsException) {
            expect(fn () => $service->get($keys, $required))
                ->toThrow(\RuntimeException::class, $expectedMessage);
        } else {
            expect($service->get($keys, $required))->toBeNull();
        }
    })->with([
        ['MISSING_KEY', true, true, 'Missing environment variable: MISSING_KEY'],
        [['KEY1', 'KEY2'], true, true, 'Missing environment variables: KEY1, KEY2'],
        ['MISSING_KEY', false, false, null],
        ['MISSING_KEY', true, true, 'Missing environment variable: MISSING_KEY'], // Default required=true
    ]);

    it('loads from custom .env path and returns correct values', function () {
        // ARRANGE
        $service = new EnvService(mockFilesystem(true, 'CUSTOM_KEY=custom_value'), '/custom/.env');

        // ACT
        $result = $service->get('CUSTOM_KEY', false);

        // ASSERT
        expect($result)->toBe('custom_value');
    });
});
