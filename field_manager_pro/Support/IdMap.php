<?php

namespace YourCompany\FieldManagerPro\Support;

/**
 * Maps "{type}:{naturalKey}" => target id for entities created earlier in the
 * current import run (e.g. "field:hero_image" => 42, "channel:blog" => 3).
 */
class IdMap
{
    protected array $map = [];

    public function put(string $key, int $id): void
    {
        $this->map[$key] = $id;
    }

    public function get(string $key): ?int
    {
        return $this->map[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->map);
    }
}
