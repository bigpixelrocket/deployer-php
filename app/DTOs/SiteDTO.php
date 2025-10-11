<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\DTOs;

readonly class SiteDTO
{
    /**
     * @param array<int, string> $servers
     */
    public function __construct(
        public string $domain,
        public string $repo,
        public string $branch,
        public array $servers,
    ) {
    }
}
