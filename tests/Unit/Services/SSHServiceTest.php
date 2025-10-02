<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\FilesystemService;
use Bigpixelrocket\DeployerPHP\Services\SSHService;

require_once __DIR__ . '/../../TestHelpers.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('SSHService', function () {
    beforeEach(function () {
        setEnv('HOME', '/home/testuser');
    });

    afterEach(function () {
        setEnv('HOME', null);
    });

    //
    // Private Key Resolution
    //

    it('throws helpful message when no SSH key is found', function () {
        // ARRANGE
        $filesystemService = mockFilesystemService(false, '', false, false, false, 'none');
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT & ASSERT
        try {
            $service->assertCanConnect('example.com', 22, 'deployer');
            throw new \Exception('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            expect($e->getMessage())
                ->toContain('No SSH private key found')
                ->and($e->getMessage())->toContain('~/.ssh/id_ed25519')
                ->and($e->getMessage())->toContain('~/.ssh/id_rsa');
        }
    });

    it('resolves user-provided key path with tilde expansion', function () {
        // ARRANGE
        $filesystemService = mockFilesystemService(true, '', false, false, false, '/home/testuser/.ssh/custom_key');
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT & ASSERT - Test through public API
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrivateKeyPath');
        $actualPath = $method->invoke($service, '~/.ssh/custom_key');

        expect($actualPath)->toBe('/home/testuser/.ssh/custom_key');
    });

    it('prioritizes provided path over defaults and returns first existing', function () {
        // ARRANGE
        $mockFs = mockFilesystem();
        $mockFs->dumpFile('/home/testuser/custom/id_rsa', 'valid_key');
        $mockFs->dumpFile('/home/testuser/.ssh/id_ed25519', 'ed_key');
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrivateKeyPath');
        $actualPath = $method->invoke($service, '/home/testuser/custom/id_rsa');

        // ASSERT
        expect($actualPath)->toBe('/home/testuser/custom/id_rsa');
    });

    it('falls back to default locations when no provided path', function () {
        // ARRANGE
        $mockFs = mockFilesystem();
        $mockFs->dumpFile('/home/testuser/.ssh/id_rsa', 'valid_key');
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolvePrivateKeyPath');
        $actualPath = $method->invoke($service, null);

        // ASSERT
        expect($actualPath)->toBe('/home/testuser/.ssh/id_rsa');
    });

    it('expands tilde in paths correctly', function () {
        // ARRANGE
        $mockFs = mockFilesystem();
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('expandHomePath');
        $expanded = $method->invoke($service, '~/.ssh/key');

        // ASSERT
        expect($expanded)->toBe('/home/testuser/.ssh/key');
    });

    //
    // Key Loading
    //

    it('throws when key content cannot be parsed as private key', function () {
        // ARRANGE
        $invalidContent = 'invalid_key_content_not_ssh';
        $mockFs = mockFilesystem(true, $invalidContent, false, false, false, '/home/testuser/.ssh/id_rsa');
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT & ASSERT
        expect(fn () => $service->assertCanConnect('example.com', 22, 'deployer', '/home/testuser/.ssh/id_rsa'))
            ->toThrow(\RuntimeException::class, 'Error parsing SSH private key');
    });

    //
    // File Validation
    //

    it('validates script file exists before execution', function () {
        // ARRANGE
        $mockFs = mockFilesystem(false, '', false, false, false, './missing.sh');
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT & ASSERT
        expect(fn () => $service->executeScript('host', 22, 'user', './missing.sh'))
            ->toThrow(\RuntimeException::class, 'Script file does not exist');
    });

    it('validates local file exists before upload', function () {
        // ARRANGE
        $mockFs = mockFilesystem(false, '', false, false, false, './missing.txt');
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT & ASSERT
        expect(fn () => $service->uploadFile('host', 22, 'user', './missing.txt', '/remote/file.txt'))
            ->toThrow(\RuntimeException::class, 'Local file does not exist');
    });

    it('includes file path in error messages', function (string $method, array $args, string $expectedPath) {
        // ARRANGE
        $mockFs = mockFilesystem(false, '', false, false, false, $expectedPath);
        $filesystemService = new FilesystemService($mockFs);
        $envService = mockEnvService(false);
        $service = new SSHService($envService, $filesystemService);

        // ACT & ASSERT
        try {
            $service->$method(...$args);
            throw new \Exception('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            expect($e->getMessage())->toContain($expectedPath);
        }
    })->with([
        'script execution' => ['executeScript', ['host', 22, 'user', './deploy.sh'], './deploy.sh'],
        'file upload' => ['uploadFile', ['host', 22, 'user', './data.txt', '/remote'], './data.txt'],
    ]);
});
