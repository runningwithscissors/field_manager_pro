<?php

namespace YourCompany\FieldManagerPro\FieldTypes;

use ExpressionEngine\Model\Channel\ChannelField;
use YourCompany\FieldManagerPro\Support\ExportContext;
use YourCompany\FieldManagerPro\Support\ImportContext;

/**
 * Base adapter implementing the public contract as a template method:
 *
 *   export() = exportTypeSettings()  then  shared conditional-logic remap
 *   import() = importTypeSettings()  then  shared conditional-logic remap
 *
 * Concrete adapters override only the protected type hooks; the conditional
 * pass (which rewrites condition_field_id between source ids and field names)
 * runs for EVERY field type, including plain scalar fields, so conditional
 * visibility survives an install-to-install move. See
 * [[ee7-field-settings-shapes]] for the verified condition structure.
 */
abstract class AbstractFieldTypeAdapter implements FieldTypeAdapter
{
    final public function export(ChannelField $field, array $settings, ExportContext $ctx): array
    {
        $portable = $this->exportTypeSettings($field, $settings, $ctx);

        return $this->exportConditions($portable, $ctx);
    }

    final public function import(array $portable, ImportContext $ctx): array
    {
        $settings = $this->importTypeSettings($portable, $ctx);

        return $this->importConditions($settings, $ctx);
    }

    public function dependencies(array $portable): array
    {
        return array_values(array_unique(array_merge(
            $this->typeDependencies($portable),
            $this->conditionDependencies($portable)
        )));
    }

    public function resolveDeferred(ChannelField $field, array $portable, ImportContext $ctx): void
    {
        // No-op by default; dependency ordering guarantees siblings already
        // exist after phase A for relationship/fluid.
    }

    // ----- Type hooks (override in concrete adapters) ------------------------

    protected function exportTypeSettings(ChannelField $field, array $settings, ExportContext $ctx): array
    {
        return $settings;
    }

    protected function importTypeSettings(array $portable, ImportContext $ctx): array
    {
        return $portable;
    }

    protected function typeDependencies(array $portable): array
    {
        return [];
    }

    // ----- Shared conditional-logic remap ------------------------------------

    /**
     * Replace each rule's condition_field_id (a source field id) with the
     * referenced field's name. Drops and warns on an unresolvable reference.
     */
    protected function exportConditions(array $settings, ExportContext $ctx): array
    {
        if (! $this->isConditional($settings)) {
            return $settings;
        }

        foreach ($settings['condition'] as $setId => $rules) {
            if (! is_array($rules)) {
                continue;
            }

            foreach ($rules as $ruleId => $rule) {
                if (! is_array($rule) || ! isset($rule['condition_field_id'])) {
                    continue;
                }

                $name = $ctx->fieldKey((int) $rule['condition_field_id']);
                if ($name === null) {
                    $ctx->addWarning(sprintf(
                        lang('field_manager_pro_unresolved_condition_field'),
                        $rule['condition_field_id']
                    ));
                    unset($settings['condition'][$setId][$ruleId]);
                    continue;
                }

                $settings['condition'][$setId][$ruleId]['condition_field_id'] = $name;
            }
        }

        return $settings;
    }

    /**
     * Reverse: resolve each rule's condition_field_id (now a field name) back to
     * a target field id. Drops and warns on a miss.
     */
    protected function importConditions(array $settings, ImportContext $ctx): array
    {
        if (! $this->isConditional($settings)) {
            return $settings;
        }

        foreach ($settings['condition'] as $setId => $rules) {
            if (! is_array($rules)) {
                continue;
            }

            foreach ($rules as $ruleId => $rule) {
                if (! is_array($rule) || ! isset($rule['condition_field_id'])) {
                    continue;
                }

                $id = $ctx->fieldId((string) $rule['condition_field_id']);
                if ($id === null) {
                    $ctx->addWarning(sprintf(
                        lang('field_manager_pro_unresolved_condition_field'),
                        $rule['condition_field_id']
                    ));
                    unset($settings['condition'][$setId][$ruleId]);
                    continue;
                }

                $settings['condition'][$setId][$ruleId]['condition_field_id'] = (string) $id;
            }
        }

        return $settings;
    }

    /**
     * Field names referenced by conditional rules (post-export the value is a
     * name), so the sorter creates those fields first when they are in-batch.
     */
    protected function conditionDependencies(array $portable): array
    {
        if (! $this->isConditional($portable)) {
            return [];
        }

        $deps = [];
        foreach ($portable['condition'] as $rules) {
            if (! is_array($rules)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (is_array($rule) && isset($rule['condition_field_id']) && ! is_numeric($rule['condition_field_id'])) {
                    $deps[] = 'field:' . $rule['condition_field_id'];
                }
            }
        }

        return $deps;
    }

    protected function isConditional(array $settings): bool
    {
        return ($settings['field_is_conditional'] ?? 'n') === 'y'
            && ! empty($settings['condition'])
            && is_array($settings['condition']);
    }
}
