<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\DTOs;

readonly class SiteDTO
{
    /**
     * Create a SiteDTO containing the site's domain, repository, branch, and associated servers.
     *
     * @param string $domain The site's domain name (e.g. example.com).
     * @param string|null $repo   The repository URL for git sites, null for local sites.
     * @param string|null $branch The repository branch for git sites (e.g. main), null for local sites.
     * @param array<int, string> $servers Ordered list of server hostnames or addresses associated with the site.
     */
    public function __construct(
        public string $domain,
        public ?string $repo,
        public ?string $branch,
        public array $servers,
    ) {
    }

    /**
     * Check if this is a local site (no git repository).
     */
    public function isLocal(): bool
    {
        return $this->repo === null;
    }
}
