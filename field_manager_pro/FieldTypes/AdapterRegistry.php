<?php

namespace YourCompany\FieldManagerPro\FieldTypes;

/**
 * Resolves a field_type to its adapter. Dedicated adapters (relationship,
 * fluid) are registered up front; everything else falls back to a ScalarAdapter
 * bound to that type.
 *
 * Types already handled by a registered fieldtype integration (Bloqs, via
 * Services/Integrations/) are passed to ScalarAdapter as $integrationHandled so
 * the "references not remapped" warning is suppressed — the integration remaps
 * them after field creation. This is the coexistence seam with the existing
 * integration layer; see [[fmp-v2-adapter-direction]].
 */
class AdapterRegistry
{
    /** @var FieldTypeAdapter[] keyed by field_type */
    protected array $adapters = [];

    /** Field types whose references a registered integration already remaps. */
    protected array $integrationHandled;

    public function __construct(array $integrationHandled = ['bloqs'])
    {
        $this->integrationHandled = $integrationHandled;

        $this->register(new RelationshipAdapter());
        $this->register(new FluidAdapter());
    }

    public function register(FieldTypeAdapter $adapter): void
    {
        foreach ((array) $adapter->handles() as $type) {
            if ($type !== '*') {
                $this->adapters[$type] = $adapter;
            }
        }
    }

    public function for(string $fieldType): FieldTypeAdapter
    {
        if (isset($this->adapters[$fieldType])) {
            return $this->adapters[$fieldType];
        }

        return new ScalarAdapter(
            $fieldType,
            in_array($fieldType, $this->integrationHandled, true)
        );
    }
}
