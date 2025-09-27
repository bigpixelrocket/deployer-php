<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../TestHelpers.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('EnvService', function () {
    beforeEach(function () {
        foreach (['TEST_KEY', 'API_KEY', 'KEY1', 'KEY2', 'MISSING_KEY'] as $key) {
            setEnv($key, null);
        }
    });


    it('reports correct status for different .env file scenarios', function ($fileExists, $fileContent, $fileError, $expectedStatusPattern) {
        // ARRANGE
        $service = new EnvService(
            mockFilesystem($fileExists, $fileContent, $fileError),
            new Dotenv()
        );

        // ACT
        $status = $service->getEnvFileStatus();

        // ASSERT
        expect($status)->toMatch($expectedStatusPattern);
    })->with([
        // No .env file exists
        [false, '', false, '/^No \.env file found at .+$/'],

        // File exists and loads successfully with variables
        [true, "API_KEY=test\nDB_HOST=localhost", false, '/^Reading 2 variables from .+\.env$/'],

        // File exists with single variable
        [true, 'SINGLE_KEY=value', false, '/^Reading 1 variable from .+\.env$/'],

        // File exists but is empty (no variables)
        [true, '', false, '/^Reading 0 variables from .+\.env$/'],

        // File exists but has read error
        [true, 'API_KEY=test', true, '/^Error reading \.env file from .+\.env$/'],

        // File exists with malformed content (triggers parse error)
        [true, "VALID=test\nINVALID_LINE\nOTHER=value", false, '/^Error reading \.env file from .+\.env$/'],
    ]);

    it('resolves environment variables from multiple sources with correct precedence', function ($env, $fileContent, $fileError, $keys, $expected) {
        // ARRANGE
        foreach ($env as $key => $value) {
            setEnv($key, $value);
        }
        $service = new EnvService(
            mockFilesystem(!empty($fileContent), $fileContent, $fileError),
            new Dotenv()
        );

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
        [['API_KEY' => 'from_env'], 'API_KEY=from_file', false, 'API_KEY', 'from_file'],    // File wins over env
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
        $service = new EnvService(
            mockFilesystem(false),
            new Dotenv()
        );

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
});
