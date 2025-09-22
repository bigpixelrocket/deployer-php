<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Acts as a thin IO layer over .deployer/inventory.yml with collection-key CRUD APIs.
 */
class InventoryService
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
    }

    //
    // CREATE Operations
    // ----

    /**
     * Set a single record in a collection (create or overwrite).
     */
    public function set(string $collection, string $key, mixed $value): void
    {
        $inventory = $this->readInventory();
        if (!isset($inventory[$collection]) || !is_array($inventory[$collection])) {
            $inventory[$collection] = [];
        }

        $inventory[$collection][$key] = $value;
        $this->writeInventory($inventory);
    }

    /**
     * Replace an entire collection.
     *
     * @param array<string, mixed> $items
     */
    public function setCollection(string $collection, array $items): void
    {
        $inventory = $this->readInventory();
        $inventory[$collection] = $items;
        $this->writeInventory($inventory);
    }

    //
    // READ Operations
    // ----

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
     * Get all records from a collection.
     *
     * @return array<string, mixed>
     */
    public function list(string $collection): array
    {
        $inventory = $this->readInventory();

        $records = $inventory[$collection] ?? [];
        /** @var array<string, mixed> $safe */
        $safe = is_array($records) ? $records : [];
        return $safe;
    }

    /**
     * Get a single record from a collection.
     */
    public function get(string $collection, string $key): mixed
    {
        $inventory = $this->readInventory();
        if (!isset($inventory[$collection]) || !is_array($inventory[$collection]) || !array_key_exists($key, $inventory[$collection])) {
            throw new \RuntimeException("Key '{$key}' not found in collection '{$collection}'.");
        }

        return $inventory[$collection][$key];
    }

    /**
     * Check if a key exists in a collection.
     */
    public function has(string $collection, string $key): bool
    {
        $inventory = $this->readInventory();

        return isset($inventory[$collection])
            && is_array($inventory[$collection])
            && array_key_exists($key, $inventory[$collection]);
    }

    //
    // DELETE Operations
    // ----

    /**
     * Delete a single record from a collection.
     */
    public function delete(string $collection, string $key, bool $mustExist = true): void
    {
        $inventory = $this->readInventory();

        if (!isset($inventory[$collection]) || !is_array($inventory[$collection]) || !array_key_exists($key, $inventory[$collection])) {
            if ($mustExist) {
                throw new \RuntimeException("Key '{$key}' not found in collection '{$collection}'.");
            }

            return;
        }

        unset($inventory[$collection][$key]);
        $this->writeInventory($inventory);
    }

    //
    // Private Helper Methods
    // ----

    /**
     * Read inventory YAML into a structured array.
     *
     * @return array<string, mixed>
     */
    private function readInventory(): array
    {
        $path = $this->getInventoryPath();

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
        $path = $this->getInventoryPath();
        $dir = dirname($path);

        if (!$this->filesystem->exists($dir)) {
            try {
                $this->filesystem->mkdir($dir, 0775);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Unable to create inventory directory: {$dir}", 0, $e);
            }
        }

        $yaml = Yaml::dump($inventory, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        try {
            $this->filesystem->dumpFile($path, $yaml);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Failed to write inventory file at {$path}", 0, $e);
        }
    }

    private function getInventoryPath(): string
    {
        return rtrim((string) getcwd(), '/').'/.deployer/inventory.yml';
    }
}
