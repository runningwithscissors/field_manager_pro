<?php

namespace YourCompany\FieldManagerPro\FieldTypes;

use YourCompany\FieldManagerPro\Support\ImportContext;

/**
 * Default adapter for self-contained field types. Settings round-trip as a
 * plain array (no base64, no serialize) plus the inherited conditional-logic
 * remap. Returned by AdapterRegistry for any field_type without a dedicated
 * adapter.
 *
 * An instance is bound to the specific field_type it was created for so that,
 * on import, a referential/composite type whose references this phase does NOT
 * remap (e.g. grid) raises a visible warning instead of silently importing
 * stale ids. Types handled by a registered integration (bloqs) are constructed
 * with $integrationHandled = true, which suppresses the warning.
 */
class ScalarAdapter extends AbstractFieldTypeAdapter
{
    /** Composite/referential types whose references this phase does NOT remap. */
    protected array $complexTypes = ['grid', 'file_grid', 'simple_grid', 'bloqs'];

    protected ?string $boundType;

    protected bool $integrationHandled;

    public function __construct(?string $boundType = null, bool $integrationHandled = false)
    {
        $this->boundType = $boundType;
        $this->integrationHandled = $integrationHandled;
    }

    public function handles(): string|array
    {
        return '*';
    }

    protected function importTypeSettings(array $portable, ImportContext $ctx): array
    {
        if (! $this->integrationHandled
            && $this->boundType !== null
            && in_array($this->boundType, $this->complexTypes, true)
        ) {
            $ctx->addWarning(sprintf(
                lang('field_manager_pro_unremapped_complex'),
                $this->boundType
            ));
        }

        return $portable;
    }
}
