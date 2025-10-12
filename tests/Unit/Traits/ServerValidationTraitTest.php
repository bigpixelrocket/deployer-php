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

    public $servers;

    /**
     * Expose protected validateNameInput for testing.
     */
    public function testValidateName(mixed $name): ?string
    {
        return $this->validateNameInput($name);
    }

    /**
     * Expose protected validateHostInput for testing.
     */
    public function testValidateHost(mixed $host): ?string
    {
        return $this->validateHostInput($host);
    }

    /**
     * Expose protected validatePortInput for testing.
     */
    public function testValidatePort(mixed $portString): ?string
    {
        return $this->validatePortInput($portString);
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../TestHelpers.php';

describe('ServerValidationTrait', function () {
    beforeEach(function () {
        $this->validator = new TestServerValidator();
    });

    //
    // validateNameInput
    // -------------------------------------------------------------------------------

    it('accepts valid server names', function (string $name) {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT
        $error = $this->validator->testValidateName($name);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'simple name' => ['web1'],
        'hyphenated' => ['web-server-01'],
        'underscored' => ['web_server_01'],
        'numeric' => ['server123'],
        'mixed case' => ['WebServer01'],
    ]);

    it('rejects empty server names', function () {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT
        $error = $this->validator->testValidateName('');

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain('cannot be empty');
    });

    it('rejects duplicate server names', function () {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, [
            'servers' => [
                ['name' => 'existing-server', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
            ],
        ]);

        // ACT
        $error = $this->validator->testValidateName('existing-server');

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain('already exists');
    });

    //
    // validateHostInput
    // -------------------------------------------------------------------------------

    it('accepts valid IPv4 addresses', function (string $host) {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT
        $error = $this->validator->testValidateHost($host);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'standard IPv4' => ['192.168.1.100'],
        'localhost' => ['127.0.0.1'],
        'zero address' => ['0.0.0.0'],
        'broadcast' => ['255.255.255.255'],
    ]);

    it('accepts valid IPv6 addresses', function (string $host) {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT
        $error = $this->validator->testValidateHost($host);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'full IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
        'compressed IPv6' => ['2001:db8::1'],
        'localhost' => ['::1'],
    ]);

    it('accepts valid domain names', function (string $host) {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT
        $error = $this->validator->testValidateHost($host);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'simple domain' => ['example.com'],
        'subdomain' => ['server.example.com'],
        'deep subdomain' => ['app.server.example.com'],
        'hyphenated domain' => ['my-server.example.com'],
        'numeric in domain' => ['server1.example.com'],
    ]);

    it('rejects invalid hosts with error messages', function (string $host, string $expectedError) {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, ['servers' => []]);

        // ACT
        $error = $this->validator->testValidateHost($host);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain($expectedError);
    })->with([
        'empty string' => ['', 'valid'],
        'underscore' => ['server_name', 'valid'],
        'spaces' => ['my server', 'valid'],
        'special chars' => ['server!@#', 'valid'],
        'double dots' => ['example..com', 'valid'],
    ]);

    it('rejects duplicate server hosts', function (string $host) {
        // ARRANGE
        $this->validator->servers = mockServerRepository(true, [
            'servers' => [
                ['name' => 'existing-server', 'host' => $host, 'port' => 22, 'username' => 'root'],
            ],
        ]);

        // ACT
        $error = $this->validator->testValidateHost($host);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain('already used by server')
            ->and($error)->toContain('existing-server');
    })->with([
        'IP address' => ['192.168.1.100'],
        'domain' => ['example.com'],
    ]);

    //
    // validatePortInput
    // -------------------------------------------------------------------------------

    it('accepts valid port numbers', function (string $portString) {
        // ARRANGE & ACT
        $error = $this->validator->testValidatePort($portString);

        // ASSERT
        expect($error)->toBeNull();
    })->with([
        'SSH default' => ['22'],
        'HTTP' => ['80'],
        'HTTPS' => ['443'],
        'custom high' => ['8080'],
        'alternative SSH' => ['2222'],
        'minimum port' => ['1'],
        'maximum port' => ['65535'],
    ]);

    it('rejects non-numeric port strings', function (string $portString) {
        // ARRANGE & ACT
        $error = $this->validator->testValidatePort($portString);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain('must be a number');
    })->with([
        'letters' => ['abc'],
        'empty' => [''],
        'special chars' => ['22!'],
        'floating point' => ['22.5'],
    ]);

    it('rejects out of range port numbers', function (string $portString, string $expectedError) {
        // ARRANGE & ACT
        $error = $this->validator->testValidatePort($portString);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain($expectedError);
    })->with([
        'zero' => ['0', 'between 1 and 65535'],
        'too high' => ['65536', 'between 1 and 65535'],
        'way too high' => ['100000', 'between 1 and 65535'],
    ]);

    it('rejects negative port numbers as non-numeric', function (string $portString) {
        // ARRANGE & ACT
        $error = $this->validator->testValidatePort($portString);

        // ASSERT
        expect($error)->not->toBeNull()
            ->and($error)->toContain('must be a number');
    })->with([
        'negative' => ['-1'],
        'large negative' => ['-100'],
    ]);
});
