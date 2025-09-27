<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Inventory file CRUD operations.
 *
 * @example
 * // Store values using dot notation
 * $inventory->set('servers.production.host', 'example.com');
 * $inventory->set('servers.production.user', 'deployer');
 *
 * // Or set entire object at once
 * $inventory->set('servers.production', ['host' => 'example.com', 'user' => 'deployer']);
 *
 * // Retrieve values at any depth
 * $inventory->get('servers.production.host');  // 'example.com'
 * $inventory->get('servers.production');       // ['host' => 'example.com', 'user' => 'deployer']
 * $inventory->get('servers');                  // ['production' => ['host' => 'example.com', 'user' => 'deployer']]
 * $inventory->get('servers.staging');          // null
 *
 * // Check if path exists
 * if ($inventory->has('servers.production')) {
 *     // Path exists
 * }
 *
 * // Delete path
 * $inventory->delete('servers.production');
 */
class InventoryService
{
    private readonly string $inventoryPath;
    private readonly string $inventoryDir;

    private string $inventoryFileStatus = '';

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        $this->inventoryPath = rtrim((string) getcwd(), '/').'/.deployer/inventory.yml';
        $this->inventoryDir = dirname($this->inventoryPath);
        $this->initializeInventoryFile();
    }

    //
    // Public
    // -------------------------------------------------------------------------------

    /**
     * Set a value using dot notation path.
     */
    public function set(string $path, mixed $value): void
    {
        $inventory = $this->readInventory();
        $segments = $this->parsePath($path);

        $this->setByPath($inventory, $segments, $value);
        $this->writeInventory($inventory);
    }

    /**
     * Get a value using dot notation path.
     */
    public function get(string $path): mixed
    {
        $inventory = $this->readInventory();
        $segments = $this->parsePath($path);

        return $this->getByPath($inventory, $segments);
    }

    /**
     * Get the entire inventory structure.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->readInventory();
    }

    /**
     * Check if a path exists using dot notation.
     */
    public function has(string $path): bool
    {
        $inventory = $this->readInventory();
        $segments = $this->parsePath($path);

        return $this->hasByPath($inventory, $segments);
    }

    /**
     * Delete a value using dot notation path.
     */
    public function delete(string $path): void
    {
        $inventory = $this->readInventory();
        $segments = $this->parsePath($path);

        $this->unsetByPath($inventory, $segments);
        $this->writeInventory($inventory);
    }

    /**
     * Get the status of the inventory file.
     */
    public function getInventoryFileStatus(): string
    {
        return $this->inventoryFileStatus;
    }

    //
    // Private
    // -------------------------------------------------------------------------------

    //
    // Initialization

    /**
     * Initialize inventory file and set status.
     */
    private function initializeInventoryFile(): void
    {
        if (!$this->filesystem->exists($this->inventoryPath)) {
            // Create empty inventory file
            try {
                if (!$this->filesystem->exists($this->inventoryDir)) {
                    $this->filesystem->mkdir($this->inventoryDir, 0775);
                }

                $emptyYaml = Yaml::dump([], 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
                $this->filesystem->dumpFile($this->inventoryPath, $emptyYaml);
                $this->inventoryFileStatus = "Creating inventory file at {$this->inventoryPath}";
            } catch (\Throwable $e) {
                $this->inventoryFileStatus = "Error creating inventory file at {$this->inventoryPath}: {$e->getMessage()}";
            }

            return;
        }

        // File exists - validate it
        try {
            $this->readInventory();
            $this->inventoryFileStatus = "Reading inventory from {$this->inventoryPath}";
        } catch (\Throwable $e) {
            $this->inventoryFileStatus = "Error reading inventory file from {$this->inventoryPath}: {$e->getMessage()}";
        }
    }

    //
    // Dot Notation Helpers

    /**
     * Parse dot notation path into array segments.
     *
     * @return array<int, string>
     */
    private function parsePath(string $path): array
    {
        return explode('.', $path);
    }

    /**
     * Get value from nested array using dot notation path segments.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $segments
     */
    private function getByPath(array $data, array $segments): mixed
    {
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set value in nested array using dot notation path segments.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $segments
     */
    private function setByPath(array &$data, array $segments, mixed $value): void
    {
        $current = &$data;

        foreach ($segments as $segment) {
            if (!is_array($current)) {
                $current = [];
            }

            if (!array_key_exists($segment, $current)) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Check if path exists in nested array using dot notation path segments.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $segments
     */
    private function hasByPath(array $data, array $segments): bool
    {
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    /**
     * Remove path from nested array using dot notation path segments.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $segments
     */
    private function unsetByPath(array &$data, array $segments): bool
    {
        if (empty($segments)) {
            return false;
        }

        $lastSegment = array_pop($segments);
        $current = &$data;

        // Navigate to parent of target
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false; // Path doesn't exist
            }
            $current = &$current[$segment];
        }

        if (!is_array($current) || !array_key_exists($lastSegment, $current)) {
            return false; // Target doesn't exist
        }

        unset($current[$lastSegment]);
        return true;
    }

    //
    // File Operations

    /**
     * Read inventory YAML into a structured array.
     *
     * @return array<string, mixed>
     */
    private function readInventory(): array
    {
        $path = $this->inventoryPath;

        if (!$this->filesystem->exists($path)) {
            return [];
        }

        $raw = $this->filesystem->readFile($path);
        $parsed = Yaml::parse($raw);

        /** @var array<string, mixed> $result */
        $result = is_array($parsed) ? $parsed : [];
        return $result;
    }

    /**
     * Persist inventory data to YAML file.
     *
     * @param array<string, mixed> $inventory
     */
    private function writeInventory(array $inventory): void
    {
        $path = $this->inventoryPath;

        if (!$this->filesystem->exists($this->inventoryDir)) {
            try {
                $this->filesystem->mkdir($this->inventoryDir, 0775);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Unable to create inventory directory: {$this->inventoryDir}", 0, $e);
            }
        }

        $yaml = Yaml::dump($inventory, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        try {
            $this->filesystem->dumpFile($path, $yaml);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to write inventory file at {$path}", 0, $e);
        }
    }
}
