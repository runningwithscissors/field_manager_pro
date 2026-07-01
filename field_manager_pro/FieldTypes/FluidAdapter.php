<?php

namespace YourCompany\FieldManagerPro\FieldTypes;

use ExpressionEngine\Model\Channel\ChannelField;
use YourCompany\FieldManagerPro\Support\ExportContext;
use YourCompany\FieldManagerPro\Support\ImportContext;

/**
 * Fluid fieldtype adapter.
 *
 * Verified EE7 settings shape (see [[ee7-field-settings-shapes]]):
 *   field_channel_fields = field_id[]  -> field_name[]
 *
 * Each allowed child is declared as a "field:<name>" dependency so the sorter
 * creates those fields before this one, even when a child is defined later in
 * the same export. resolveDeferred therefore stays a no-op.
 */
class FluidAdapter extends AbstractFieldTypeAdapter
{
    public function handles(): string|array
    {
        return 'fluid_field';
    }

    protected function exportTypeSettings(ChannelField $field, array $settings, ExportContext $ctx): array
    {
        $portable = $settings;

        $portable['field_channel_fields'] = array_values(array_filter(array_map(
            fn ($id) => $ctx->fieldKey((int) $id),
            (array) ($settings['field_channel_fields'] ?? [])
        ), fn ($v) => $v !== null));

        return $portable;
    }

    protected function importTypeSettings(array $portable, ImportContext $ctx): array
    {
        $settings = $portable;

        $settings['field_channel_fields'] = [];
        foreach ((array) ($portable['field_channel_fields'] ?? []) as $name) {
            $id = $ctx->fieldId((string) $name);
            if ($id === null) {
                $ctx->addWarning(sprintf(
                    lang('field_manager_pro_unresolved_fluid_child'),
                    $ctx->currentField(),
                    $name
                ));
                continue;
            }
            $settings['field_channel_fields'][] = $id;
        }

        return $settings;
    }

    protected function typeDependencies(array $portable): array
    {
        return array_map(
            fn ($name) => 'field:' . $name,
            (array) ($portable['field_channel_fields'] ?? [])
        );
    }
}
