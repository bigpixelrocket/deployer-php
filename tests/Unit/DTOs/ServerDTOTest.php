<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;

describe('ServerDTO', function () {
    it('creates server with all properties', function () {
        // ARRANGE & ACT
        $server = new ServerDTO(
            name: 'production-web',
            host: '192.168.1.100',
            port: 2222,
            username: 'deployer',
            privateKeyPath: '~/.ssh/custom_key'
        );

        // ASSERT
        expect($server->name)->toBe('production-web')
            ->and($server->host)->toBe('192.168.1.100')
            ->and($server->port)->toBe(2222)
            ->and($server->username)->toBe('deployer')
            ->and($server->privateKeyPath)->toBe('~/.ssh/custom_key');
    });

    it('uses default values for optional properties', function () {
        // ARRANGE & ACT
        $server = new ServerDTO(name: 'test-server', host: '127.0.0.1');

        // ASSERT
        expect($server->port)->toBe(22)
            ->and($server->username)->toBe('root')
            ->and($server->privateKeyPath)->toBeNull();
    });
});
