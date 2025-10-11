<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Repositories;

use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;

/**
 * Repository for site CRUD operations using inventory storage.
 *
 * Stores sites as an array of objects to handle any special characters in domain names.
 */
final class SiteRepository
{
    private const PREFIX = 'sites';

    private ?InventoryService $inventory = null;

    /** @var array<int, array<string, mixed>> */
    private array $sites = [];

    //
    // Public
    // -------------------------------------------------------------------------------

    /**
     * Set the inventory service instance to use for storage operations.
     */
    public function loadInventory(InventoryService $inventory): void
    {
        $this->inventory = $inventory;

        $sites = $inventory->get(self::PREFIX);
        if (!is_array($sites)) {
            $sites = [];
            $inventory->set(self::PREFIX, $sites);
        }

        /** @var array<int, array<string, mixed>> $sites */
        $this->sites = $sites;
    }

    /**
     * Create a new site in the inventory.
     */
    public function create(SiteDTO $site): void
    {
        $this->assertInventoryLoaded();

        $existing = $this->findByDomain($site->domain);
        if (null !== $existing) {
            throw new \RuntimeException("Site '{$site->domain}' already exists");
        }

        $this->sites[] = $this->dehydrateSiteDTO($site);

        $this->inventory->set(self::PREFIX, $this->sites);
    }

    /**
     * Find a site by domain.
     */
    public function findByDomain(string $domain): ?SiteDTO
    {
        $this->assertInventoryLoaded();

        foreach ($this->sites as $site) {
            if (isset($site['domain']) && $site['domain'] === $domain) {
                return $this->hydrateSiteDTO($site);
            }
        }

        return null;
    }

    /**
     * Get all sites from the inventory.
     *
     * @return array<int, SiteDTO>
     */
    public function all(): array
    {
        $this->assertInventoryLoaded();

        $result = [];
        foreach ($this->sites as $site) {
            $result[] = $this->hydrateSiteDTO($site);
        }

        return $result;
    }

    /**
     * Delete a site from the inventory.
     */
    public function delete(string $domain): void
    {
        $this->assertInventoryLoaded();

        $filtered = [];
        foreach ($this->sites as $site) {
            if (isset($site['domain']) && $site['domain'] !== $domain) {
                $filtered[] = $site;
            }
        }

        $this->sites = $filtered;

        $this->inventory->set(self::PREFIX, $this->sites);
    }

    //
    // Private
    // -------------------------------------------------------------------------------

    /**
     * Ensure inventory service is loaded before operations.
     *
     * @throws \RuntimeException If inventory is not set
     * @phpstan-assert !null $this->inventory
     */
    private function assertInventoryLoaded(): void
    {
        if ($this->inventory === null) {
            throw new \RuntimeException('Inventory not set. Call loadInventory() first.');
        }
    }

    /**
     * Convert SiteDTO to array for storage.
     *
     * @return array<string, mixed>
     */
    private function dehydrateSiteDTO(SiteDTO $site): array
    {
        return [
            'domain' => $site->domain,
            'repo' => $site->repo,
            'branch' => $site->branch,
            'servers' => $site->servers,
        ];
    }

    /**
     * Hydrate a SiteDTO from inventory data.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateSiteDTO(array $data): SiteDTO
    {
        $domain = $data['domain'] ?? '';
        $repo = $data['repo'] ?? '';
        $branch = $data['branch'] ?? '';
        $servers = $data['servers'] ?? [];

        return new SiteDTO(
            domain: is_string($domain) ? $domain : '',
            repo: is_string($repo) ? $repo : '',
            branch: is_string($branch) ? $branch : '',
            servers: is_array($servers) ? array_values(array_filter($servers, 'is_string')) : [],
        );
    }
}
