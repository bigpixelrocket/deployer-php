<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\Traits\SiteValidationTrait;

//
// Test fixture
// -------------------------------------------------------------------------------

class TestSiteValidator
{
    use SiteValidationTrait;

    public $sites;

    public $servers;

    public $proc;

    /**
     * Expose protected validateDomainInput for testing.
     */
    public function testValidateDomain(mixed $domain): ?string
    {
        return $this->validateDomainInput($domain);
    }

    /**
     * Expose protected validateBranchInput for testing.
     */
    public function testValidateBranch(mixed $branch): ?string
    {
        return $this->validateBranchInput($branch);
    }

    /**
     * Expose protected validateRepoInput for testing.
     */
    public function testValidateRepo(mixed $repo): ?string
    {
        return $this->validateRepoInput($repo);
    }

    /**
     * Expose protected validateGitRepo for testing.
     */
    public function testValidateGitRepo(string $repo): void
    {
        $this->validateGitRepo($repo);
    }

    /**
     * Expose protected validateServers for testing.
     *
     * @param array<int, string> $serverNames
     */
    public function testValidateServers(array $serverNames): void
    {
        $this->validateServers($serverNames);
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../TestHelpers.php';

describe('SiteValidationTrait', function () {
    beforeEach(function () {
        $this->validator = new TestSiteValidator();
    });

    //
    // validateDomainInput
    // -------------------------------------------------------------------------------

    it('accepts valid domain names', function (string $domain) {
        // ARRANGE
        $this->validator->sites = mockSiteRepository(true, ['sites' => []]);

        // ACT
        $error = $this->validator->testValidateDomain($domain);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'simple domain' => ['example.com'],
        'subdomain' => ['blog.example.com'],
        'deep subdomain' => ['api.app.example.com'],
        'hyphenated domain' => ['my-site.example.com'],
        'numeric in domain' => ['site1.example.com'],
        'single letter' => ['x.com'],
        'long TLD' => ['example.agency'],
    ]);

    it('rejects invalid domain formats with error messages', function (string $domain, string $expectedError) {
        // ARRANGE
        $this->validator->sites = mockSiteRepository(true, ['sites' => []]);

        // ACT
        $error = $this->validator->testValidateDomain($domain);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain($expectedError);
    })->with([
        'empty string' => ['', 'valid domain name'],
        'underscore' => ['example_site.com', 'valid domain name'],
        'spaces' => ['my site.com', 'valid domain name'],
        'special chars' => ['site!@#.com', 'valid domain name'],
        'double dots' => ['example..com', 'valid domain name'],
        'starts with dot' => ['.example.com', 'valid domain name'],
    ]);

    it('rejects duplicate domains', function () {
        // ARRANGE
        $this->validator->sites = mockSiteRepository(true, [
            'sites' => [
                ['domain' => 'existing.com', 'servers' => ['web1']],
            ],
        ]);

        // ACT
        $error = $this->validator->testValidateDomain('existing.com');

        // ASSERT
        expect($error)->toContain('already exists in inventory');
    });

    it('rejects non-string domain input', function () {
        // ARRANGE
        $this->validator->sites = mockSiteRepository(true, ['sites' => []]);

        // ACT
        $error = $this->validator->testValidateDomain(123);

        // ASSERT
        expect($error)->toBe('Domain must be a string');
    });

    //
    // validateBranchInput
    // -------------------------------------------------------------------------------

    it('accepts valid branch names', function (string $branch) {
        // ACT
        $error = $this->validator->testValidateBranch($branch);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'main' => ['main'],
        'master' => ['master'],
        'develop' => ['develop'],
        'feature branch' => ['feature/new-ui'],
        'bugfix branch' => ['bugfix/issue-123'],
        'release branch' => ['release/v1.2.3'],
        'numeric' => ['123'],
        'with dots' => ['feature.test'],
        'with underscores' => ['feature_branch'],
    ]);

    it('rejects empty branch names', function () {
        // ACT
        $error = $this->validator->testValidateBranch('');

        // ASSERT
        expect($error)->toContain('cannot be empty');
    });

    it('rejects whitespace-only branch names', function () {
        // ACT
        $error = $this->validator->testValidateBranch('   ');

        // ASSERT
        expect($error)->toContain('cannot be empty');
    });

    it('rejects non-string branch input', function () {
        // ACT
        $error = $this->validator->testValidateBranch(123);

        // ASSERT
        expect($error)->toBe('Branch name must be a string');
    });

    //
    // validateRepoInput
    // -------------------------------------------------------------------------------

    it('accepts valid git repository URLs', function (string $repo) {
        // ACT
        $error = $this->validator->testValidateRepo($repo);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'HTTPS GitHub' => ['https://github.com/user/repo.git'],
        'HTTPS GitLab' => ['https://gitlab.com/user/repo.git'],
        'HTTPS Bitbucket' => ['https://bitbucket.org/user/repo.git'],
        'HTTP URL' => ['http://example.com/repo.git'],
        'SSH GitHub' => ['git@github.com:user/repo.git'],
        'SSH GitLab' => ['git@gitlab.com:user/repo.git'],
        'SSH Bitbucket' => ['git@bitbucket.org:user/repo.git'],
        'SSH protocol' => ['ssh://git@github.com/user/repo.git'],
        'HTTPS without .git' => ['https://github.com/user/repo'],
        'SSH custom port' => ['ssh://git@example.com:2222/repo.git'],
        'HTTPS with subdomain' => ['https://git.example.com/repo.git'],
    ]);

    it('rejects empty repository URLs', function () {
        // ACT
        $error = $this->validator->testValidateRepo('');

        // ASSERT
        expect($error)->toContain('cannot be empty');
    });

    it('rejects whitespace-only repository URLs', function () {
        // ACT
        $error = $this->validator->testValidateRepo('   ');

        // ASSERT
        expect($error)->toContain('cannot be empty');
    });

    it('rejects invalid repository URL formats', function (string $repo, string $expectedError) {
        // ACT
        $error = $this->validator->testValidateRepo($repo);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain($expectedError);
    })->with([
        'no protocol' => ['github.com/user/repo.git', 'must start with'],
        'invalid protocol' => ['ftp://github.com/user/repo.git', 'must start with'],
        'plain path' => ['/path/to/repo', 'must start with'],
        'relative path' => ['../repo', 'must start with'],
        'just domain' => ['example.com', 'must start with'],
    ]);

    it('rejects non-string repository input', function () {
        // ACT
        $error = $this->validator->testValidateRepo(123);

        // ASSERT
        expect($error)->toBe('Repository URL must be a string');
    });

    //
    // validateGitRepo (exception-throwing method)
    // -------------------------------------------------------------------------------
    //
    // Note: validateGitRepo performs heavy I/O (network calls to git repositories)
    // and is tested via integration tests in command test suites.

    //
    // validateServers (exception-throwing method)
    // -------------------------------------------------------------------------------

    it('throws exception when no servers are selected', function () {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT & ASSERT
        expect(fn () => $this->validator->testValidateServers([]))
            ->toThrow(\RuntimeException::class, 'At least one server must be selected');
    });

    it('throws exception when server is not found in inventory', function () {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, [
            'servers' => [
                ['name' => 'web1', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
            ],
        ]);

        // ACT & ASSERT
        expect(fn () => $this->validator->testValidateServers(['nonexistent']))
            ->toThrow(\RuntimeException::class, "Server 'nonexistent' not found in inventory");
    });

    it('passes validation when all servers exist', function () {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, [
            'servers' => [
                ['name' => 'web1', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
                ['name' => 'web2', 'host' => '192.168.1.2', 'port' => 22, 'username' => 'root'],
            ],
        ]);

        // ACT & ASSERT - Should not throw
        $this->validator->testValidateServers(['web1', 'web2']);
        expect(true)->toBeTrue();
    });

    it('throws exception when one of multiple servers is not found', function () {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, [
            'servers' => [
                ['name' => 'web1', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
            ],
        ]);

        // ACT & ASSERT
        expect(fn () => $this->validator->testValidateServers(['web1', 'nonexistent']))
            ->toThrow(\RuntimeException::class, "Server 'nonexistent' not found in inventory");
    });
});
