<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\ProcessFactory;
use Bigpixelrocket\DeployerPHP\Services\VersionService;

require_once __DIR__ . '/../../TestHelpers.php';

describe('VersionService', function () {
    it('returns version with correct fallback priority', function (string $packageName, string $fallback) {
        // ARRANGE
        $processFactory = mockProcessFactory();
        $filesystemService = new \Bigpixelrocket\DeployerPHP\Services\FilesystemService(new \Symfony\Component\Filesystem\Filesystem());
        $service = new VersionService($processFactory, $filesystemService, $packageName, $fallback);

        // ACT
        $version = $service->getVersion();

        // ASSERT - Version should be a non-empty string
        expect($version)->toBeString()
            ->and(strlen($version))->toBeGreaterThan(0);

        // ASSERT - If we're in a git repo, git version takes priority over fallback
        if ($service->isGitRepository(getcwd())) {
            expect($version)->not->toBe($fallback); // Git version used instead
        } else {
            expect($version)->toBe($fallback); // Fallback used
        }
    })->with([
        'non-existent package with version fallback' => ['definitely/non/existent', 'v2.0.0-fallback'],
        'missing package with custom fallback' => ['missing/package', 'dev-custom'],
        'missing package with default fallback' => ['another/missing', 'dev-main'],
    ]);

    it('detects git repository correctly', function (bool $hasGitDir, bool $expectedResult) {
        // ARRANGE
        $tempDir = sys_get_temp_dir() . '/test-' . uniqid();
        mkdir($tempDir);

        if ($hasGitDir) {
            mkdir($tempDir . '/.git');
        }

        $processFactory = mockProcessFactory();
        $filesystemService = new \Bigpixelrocket\DeployerPHP\Services\FilesystemService(new \Symfony\Component\Filesystem\Filesystem());
        $service = new VersionService($processFactory, $filesystemService);

        // ACT
        $result = $service->isGitRepository($tempDir);

        // ASSERT
        expect($result)->toBe($expectedResult);

        // CLEANUP
        if ($hasGitDir && is_dir($tempDir . '/.git')) {
            rmdir($tempDir . '/.git');
        }
        rmdir($tempDir);
    })->with([
        'git repository' => [true, true],
        'non-git directory' => [false, false],
    ]);

    it('handles git command failures gracefully for all git methods', function (string $method) {
        // ARRANGE
        $processFactory = mockProcessFactory();
        $filesystemService = new \Bigpixelrocket\DeployerPHP\Services\FilesystemService(new \Symfony\Component\Filesystem\Filesystem());
        $service = new VersionService($processFactory, $filesystemService);
        $invalidPath = '/absolutely/non/existent/path';

        // ACT
        $result = $service->$method($invalidPath);

        // ASSERT
        expect($result)->toBeNull();
    })->with([
        'exact git tag method' => ['getExactGitTag'],
        'git describe method' => ['getGitDescribeVersion'],
        'branch with commit method' => ['getBranchWithCommit'],
    ]);

    it('returns null for non-existent composer packages', function () {
        // ARRANGE
        $filesystemService = new \Bigpixelrocket\DeployerPHP\Services\FilesystemService(new \Symfony\Component\Filesystem\Filesystem());
        $processFactory = new ProcessFactory($filesystemService);
        $service = new VersionService($processFactory, $filesystemService, 'absolutely/non/existent/package');

        // ACT
        $result = $service->getVersionFromComposer();

        // ASSERT
        expect($result)->toBeNull();
    });
});
