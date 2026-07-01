<?php

namespace YourCompany\FieldManagerPro\Support;

use YourCompany\FieldManagerPro\FieldTypes\AdapterRegistry;

/**
 * Topologically sorts field payloads so that any field another field depends on
 * (via its adapter's dependencies(), restricted to "field:*" keys) is created
 * first. Only dependencies pointing at fields present in this batch create
 * ordering edges; deps on fields that already exist on the target are resolved
 * live at import time and need no ordering.
 *
 * Cycles are a hard error (fail loudly with the field names involved) rather
 * than an infinite loop.
 */
class DependencySorter
{
    protected AdapterRegistry $registry;

    public function __construct(AdapterRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param array $fields list of portable field payloads (each has
     *                      field_name, field_type, settings)
     * @return array same payloads, reordered
     *
     * @throws \RuntimeException on a dependency cycle
     */
    public function sort(array $fields): array
    {
        $byName = [];
        foreach ($fields as $field) {
            $name = $field['field_name'] ?? null;
            if ($name !== null) {
                $byName[$name] = $field;
            }
        }

        // Build adjacency: dependency $dep must come before $name.
        $edges = [];      // name => [depName, ...]
        $inDegree = [];
        foreach ($byName as $name => $field) {
            $edges[$name] = [];
            $inDegree[$name] = $inDegree[$name] ?? 0;
        }

        foreach ($byName as $name => $field) {
            $adapter = $this->registry->for($field['field_type'] ?? '');
            $deps = $adapter->dependencies($field['settings'] ?? []);

            foreach ($deps as $dep) {
                if (strpos($dep, 'field:') !== 0) {
                    continue; // channel/category/etc. precede field creation already
                }

                $depName = substr($dep, strlen('field:'));
                if ($depName === $name || ! isset($byName[$depName])) {
                    continue; // self-ref or not in this batch → no ordering edge
                }

                $edges[$depName][] = $name;
                $inDegree[$name]++;
            }
        }

        // Kahn's algorithm. Seed with stable original order for determinism.
        $queue = [];
        foreach ($byName as $name => $field) {
            if (($inDegree[$name] ?? 0) === 0) {
                $queue[] = $name;
            }
        }

        $sorted = [];
        while (! empty($queue)) {
            $name = array_shift($queue);
            $sorted[] = $byName[$name];

            foreach ($edges[$name] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        if (count($sorted) !== count($byName)) {
            $cyclic = [];
            foreach ($inDegree as $name => $degree) {
                if ($degree > 0) {
                    $cyclic[] = $name;
                }
            }

            throw new \RuntimeException(sprintf(
                lang('field_manager_pro_dependency_cycle'),
                implode(', ', $cyclic)
            ));
        }

        return $sorted;
    }
}
