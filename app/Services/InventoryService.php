<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * Generic YAML-backed inventory service for CRUD/query operations.
 *
 * Acts as a thin IO layer over .deployer/inventory.yml with collection/key APIs.
 */
class InventoryService
{
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
    /**
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
     * Check if a key exists in a collection.
     */
    public function has(string $collection, string $key): bool
    {
        $inventory = $this->readInventory();

        return isset($inventory[$collection])
            && is_array($inventory[$collection])
            && array_key_exists($key, $inventory[$collection]);
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

    /**
     * Read inventory YAML into a structured array.
     *
     * @return array<string, mixed>
     */
    private function readInventory(): array
    {
        $path = $this->getInventoryPath();

        if (!is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);
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
        $dir = $this->getInventoryDir();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Unable to create inventory directory: {$dir}");
            }
        }

        $path = $this->getInventoryPath();
        $yaml = Yaml::dump($inventory, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $bytes = @file_put_contents($path, $yaml);
        if ($bytes === false) {
            throw new \RuntimeException("Failed to write inventory file at {$path}");
        }
    }

    private function getInventoryDir(): string
    {
        return rtrim((string) getcwd(), '/').'/.deployer';
    }

    private function getInventoryPath(): string
    {
        return $this->getInventoryDir().'/inventory.yml';
    }
}
