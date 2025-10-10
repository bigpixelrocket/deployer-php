<?php

declare(strict_types=1);

require_once __DIR__ . '/../../TestHelpers.php';

describe('VersionService', function () {
    it('returns version with correct fallback priority', function (string $packageName, string $fallback) {
        // ARRANGE
        $service = mockVersionService($packageName, $fallback);

        // ACT
        $version = $service->getVersion();

        // ASSERT - Version should match valid version patterns (semver, git-describe, branch names, or commit hashes)
        expect($version)->toMatch('/^(v?\d+\.\d+\.\d+|dev-|main-|master-|[0-9a-f]{7,40})/')
            ->and($version)->not->toBeEmpty();

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

        $service = mockVersionService();

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
        $invalidPath = '/absolutely/non/existent/path';
        $service = mockVersionService();

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
        $service = mockVersionService('absolutely/non/existent/package');

        // ACT
        $result = $service->getVersionFromComposer();

        // ASSERT
        expect($result)->toBeNull();
    });
});
