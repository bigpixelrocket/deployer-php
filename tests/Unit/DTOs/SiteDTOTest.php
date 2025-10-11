<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;

describe('SiteDTO', function () {
    it('creates site with all properties', function () {
        // ARRANGE & ACT
        $site = new SiteDTO(
            domain: 'example.com',
            repo: 'git@github.com:user/repo.git',
            branch: 'main',
            servers: ['production-web', 'staging-web']
        );

        // ASSERT
        expect($site->domain)->toBe('example.com')
            ->and($site->repo)->toBe('git@github.com:user/repo.git')
            ->and($site->branch)->toBe('main')
            ->and($site->servers)->toBe(['production-web', 'staging-web']);
    });
});
