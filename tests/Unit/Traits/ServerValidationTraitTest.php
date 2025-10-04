<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\Traits\ServerValidationTrait;

//
// Test fixture
// -------------------------------------------------------------------------------

class TestServerValidator
{
    use ServerValidationTrait;

    /**
     * Expose protected validateHost for testing.
     */
    public function testValidateHost(string $host): void
    {
        $this->validateHost($host);
    }

    /**
     * Expose protected validatePort for testing.
     */
    public function testValidatePort(int $port): void
    {
        $this->validatePort($port);
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

describe('ServerValidationTrait', function () {
    beforeEach(function () {
        $this->validator = new TestServerValidator();
    });

    //
    // validateHost
    // -------------------------------------------------------------------------------

    it('accepts valid IPv4 addresses', function (string $host) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->validator->testValidateHost($host))->not->toThrow(\InvalidArgumentException::class);
    })->with([
        'standard IPv4' => ['192.168.1.100'],
        'localhost' => ['127.0.0.1'],
        'zero address' => ['0.0.0.0'],
        'broadcast' => ['255.255.255.255'],
    ]);

    it('accepts valid IPv6 addresses', function (string $host) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->validator->testValidateHost($host))->not->toThrow(\InvalidArgumentException::class);
    })->with([
        'full IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
        'compressed IPv6' => ['2001:db8::1'],
        'localhost' => ['::1'],
    ]);

    it('accepts valid domain names', function (string $host) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->validator->testValidateHost($host))->not->toThrow(\InvalidArgumentException::class);
    })->with([
        'simple domain' => ['example.com'],
        'subdomain' => ['server.example.com'],
        'deep subdomain' => ['app.server.example.com'],
        'hyphenated domain' => ['my-server.example.com'],
        'numeric in domain' => ['server1.example.com'],
    ]);

    it('rejects invalid hosts', function (string $host) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->validator->testValidateHost($host))
            ->toThrow(\InvalidArgumentException::class, 'Invalid host');
    })->with([
        'empty string' => [''],
        'underscore' => ['server_name'],
        'spaces' => ['my server'],
        'special chars' => ['server!@#'],
        'double dots' => ['example..com'],
    ]);

    it('provides helpful error message for invalid hosts', function () {
        // ARRANGE
        $invalidHost = 'invalid_host';

        // ACT & ASSERT
        try {
            $this->validator->testValidateHost($invalidHost);
            throw new \Exception('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            expect($e->getMessage())
                ->toContain('Invalid host')
                ->and($e->getMessage())->toContain($invalidHost)
                ->and($e->getMessage())->toContain('Examples:')
                ->and($e->getMessage())->toContain('192.168.1.100')
                ->and($e->getMessage())->toContain('example.com');
        }
    });

    //
    // validatePort
    // -------------------------------------------------------------------------------

    it('accepts valid port numbers', function (int $port) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->validator->testValidatePort($port))->not->toThrow(\InvalidArgumentException::class);
    })->with([
        'SSH default' => [22],
        'HTTP' => [80],
        'HTTPS' => [443],
        'custom high' => [8080],
        'alternative SSH' => [2222],
        'minimum port' => [1],
        'maximum port' => [65535],
    ]);

    it('rejects invalid port numbers', function (int $port) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->validator->testValidatePort($port))
            ->toThrow(\InvalidArgumentException::class, 'between 1 and 65535');
    })->with([
        'zero' => [0],
        'negative' => [-1],
        'large negative' => [-100],
        'too high' => [65536],
        'way too high' => [100000],
    ]);

    it('provides helpful error message for invalid ports', function () {
        // ARRANGE
        $invalidPort = 99999;

        // ACT & ASSERT
        try {
            $this->validator->testValidatePort($invalidPort);
            throw new \Exception('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            expect($e->getMessage())
                ->toContain('Invalid port')
                ->and($e->getMessage())->toContain((string) $invalidPort)
                ->and($e->getMessage())->toContain('between 1 and 65535')
                ->and($e->getMessage())->toContain('Common SSH ports:')
                ->and($e->getMessage())->toContain('22 (default)')
                ->and($e->getMessage())->toContain('2222');
        }
    });
});
