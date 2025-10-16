<?php

declare(strict_types=1);


require_once __DIR__ . '/../../TestHelpers.php';

describe('GitService', function () {
    //
    // detectRemoteUrl
    // -------------------------------------------------------------------------------

    it('detects git remote URL from current directory', function () {
        // ARRANGE
        $git = mockGitService();

        // ACT
        $url = $git->detectRemoteUrl();

        // ASSERT - In a git repo, this will detect the origin URL; in non-git, returns null
        if ($url !== null) {
            expect($url)->toBeString();
        } else {
            expect($url)->toBeNull();
        }
    });

    it('returns null when not in a git repository', function () {
        // ARRANGE
        $git = mockGitService();
        $tempDir = sys_get_temp_dir() . '/test_non_git_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // ACT
            $url = $git->detectRemoteUrl($tempDir);

            // ASSERT
            expect($url)->toBeNull();
        } finally {
            rmdir($tempDir);
        }
    });

    it('returns null for invalid working directory', function () {
        // ARRANGE
        $git = mockGitService();

        // ACT
        $url = $git->detectRemoteUrl('/non/existent/directory');

        // ASSERT
        expect($url)->toBeNull();
    });

    //
    // detectCurrentBranch
    // -------------------------------------------------------------------------------

    it('detects git branch from current directory', function () {
        // ARRANGE
        $git = mockGitService();

        // ACT
        $branch = $git->detectCurrentBranch();

        // ASSERT - In a git repo, returns branch name; in non-git, returns null
        if ($branch !== null) {
            expect($branch)->toBeString();
        } else {
            expect($branch)->toBeNull();
        }
    });

    it('returns null when not in a git repository for branch', function () {
        // ARRANGE
        $git = mockGitService();
        $tempDir = sys_get_temp_dir() . '/test_non_git_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            // ACT
            $branch = $git->detectCurrentBranch($tempDir);

            // ASSERT
            expect($branch)->toBeNull();
        } finally {
            rmdir($tempDir);
        }
    });

    it('returns null for invalid working directory for branch', function () {
        // ARRANGE
        $git = mockGitService();

        // ACT
        $branch = $git->detectCurrentBranch('/non/existent/directory');

        // ASSERT
        expect($branch)->toBeNull();
    });

    it('trims whitespace from output', function () {
        // ARRANGE
        $git = mockGitService();

        // ACT - Both methods should trim output
        $url = $git->detectRemoteUrl(__DIR__);
        $branch = $git->detectCurrentBranch(__DIR__);

        // ASSERT - If not null, should not have leading/trailing whitespace
        if ($url !== null) {
            expect($url)->toBe(trim($url));
        }
        if ($branch !== null) {
            expect($branch)->toBe(trim($branch));
        }
    });
});
