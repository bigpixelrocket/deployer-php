<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\VersionDetectionService;

describe('VersionDetectionService', function () {

    it('returns version from available sources with fallback priority', function (string $packageName, string $fallback) {
        // ARRANGE
        $service = new VersionDetectionService($packageName, $fallback);

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
        'non-existent package' => ['definitely/non/existent', 'v2.0.0-fallback'],
        'custom fallback' => ['missing/package', 'dev-custom'],
        'default fallback' => ['another/missing', 'dev-main'],
    ]);

    it('detects git repository correctly', function (bool $hasGitDir, bool $expectedResult) {
        // ARRANGE
        $tempDir = sys_get_temp_dir() . '/test-' . uniqid();
        mkdir($tempDir);

        if ($hasGitDir) {
            mkdir($tempDir . '/.git');
        }

        $service = new VersionDetectionService();

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
        'directory with .git' => [true, true],
        'directory without .git' => [false, false],
    ]);

    it('handles git command failures gracefully', function (string $method, string $invalidPath) {
        // ARRANGE
        $service = new VersionDetectionService();

        // ACT
        $result = $service->$method($invalidPath);

        // ASSERT
        expect($result)->toBeNull();
    })->with([
        'exact git tag on invalid path' => ['getExactGitTag', '/absolutely/non/existent/path'],
        'git describe on invalid path' => ['getGitDescribeVersion', '/absolutely/non/existent/path'],
        'branch with commit on invalid path' => ['getBranchWithCommit', '/absolutely/non/existent/path'],
    ]);

    it('returns null for non-existent composer packages', function () {
        // ARRANGE
        $service = new VersionDetectionService('absolutely/non/existent/package');

        // ACT
        $result = $service->getVersionFromComposer();

        // ASSERT
        expect($result)->toBeNull();
    });
});
