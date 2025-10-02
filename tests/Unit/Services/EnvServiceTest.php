<?php

declare(strict_types=1);

require_once __DIR__ . '/../../TestHelpers.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('EnvService', function () {
    beforeEach(function () {
        foreach (['TEST_KEY', 'API_KEY', 'KEY1', 'KEY2', 'MISSING_KEY'] as $key) {
            setEnv($key, null);
        }
    });


    it('reports correct status for different .env file scenarios', function ($fileExists, $fileContent, $expectsException, $expectedStatusPattern) {
        // ARRANGE
        $service = mockEnvService($fileExists, $fileContent, $expectsException);

        // ACT & ASSERT
        if ($expectsException) {
            expect(fn () => $service->loadEnvFile())
                ->toThrow(\RuntimeException::class, 'Error reading .env file from');
        } else {
            $service->loadEnvFile();
            $status = $service->getEnvFileStatus();
            expect($status)->toMatch($expectedStatusPattern);
        }
    })->with([
        // No .env file exists
        [false, '', false, '/^No \.env file found at .+$/'],

        // File exists but is empty (no variables)
        [true, '', false, '/^No variables found in .+\.env$/'],

        // File exists and loads successfully with variables
        [true, "API_KEY=test\nDB_HOST=localhost", false, '/^Reading variables from .+\.env$/'],

        // File exists but has read error (throws exception)
        [true, 'API_KEY=test', true, null],
    ]);

    it('resolves environment variables from multiple sources with correct precedence', function ($env, $fileContent, $fileError, $keys, $expected) {
        // ARRANGE
        foreach ($env as $key => $value) {
            setEnv($key, $value);
        }
        $service = mockEnvService(!empty($fileContent), $fileContent, $fileError);
        $service->loadEnvFile();

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
        [[], 'API_KEY=from_file', false, 'API_KEY', 'from_file'],                         // File only
        [['API_KEY' => 'from_env'], 'API_KEY=from_file', false, 'API_KEY', 'from_file'],  // File wins over env
        [['API_KEY' => ''], 'API_KEY=from_file', false, 'API_KEY', 'from_file'],          // Empty env ignored
        [[], 'API_KEY=', false, 'API_KEY', null],                                         // Empty file ignored
        [[], '', false, 'API_KEY', null],                                                 // File missing

        // Multiple key scenarios (iterates in order, returns first found)
        [['KEY1' => 'from_env'], 'KEY2=file_val', false, ['KEY1', 'KEY2'], 'from_env'],  // First key in env
        [[], "KEY1=file_val\nKEY2=other", false, ['KEY1', 'KEY2'], 'file_val'],          // First key in file
        [[], '', false, ['KEY1', 'KEY2'], null],                                         // No keys found
    ]);

    it('handles required vs optional parameters', function ($keys, $required, $expectsException, $expectedMessage) {
        // ARRANGE
        $service = mockEnvService(false, '');
        $service->loadEnvFile();

        // ACT & ASSERT
        if ($expectsException) {
            expect(fn () => $service->get($keys, $required))
                ->toThrow(\RuntimeException::class, $expectedMessage);
        } else {
            expect($service->get($keys, $required))->toBeNull();
        }
    })->with([
        ['MISSING_KEY', true, true, 'Missing required environment variable: MISSING_KEY'],
        [['KEY1', 'KEY2'], true, true, 'Missing required environment variables: KEY1, KEY2'],
        ['MISSING_KEY', false, false, null],
    ]);
});
