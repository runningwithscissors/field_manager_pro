<?php

namespace YourCompany\FieldManagerPro\FieldTypes;

use ExpressionEngine\Model\Channel\ChannelField;
use YourCompany\FieldManagerPro\Support\ExportContext;
use YourCompany\FieldManagerPro\Support\ImportContext;

/**
 * Relationship fieldtype adapter.
 *
 * Verified EE7 settings shape (see [[ee7-field-settings-shapes]]):
 *   channels   = channel_id[]            -> channel_name[]
 *   authors    = member_id[]             -> username[] (email fallback on import)
 *   categories = individual cat_id[]     -> "group_name/cat_url_title"[]
 *   statuses   = status name[]           (already portable; existence-checked)
 * Every other key (limit, expired, future, order_field, order_dir,
 * allow_multiple, display_*, deferred_loading, rel_min, rel_max, …) passes
 * through verbatim — enumerated from the live field, never hardcoded.
 */
class RelationshipAdapter extends AbstractFieldTypeAdapter
{
    public function handles(): string|array
    {
        return 'relationship';
    }

    protected function exportTypeSettings(ChannelField $field, array $settings, ExportContext $ctx): array
    {
        $portable = $settings;

        $portable['channels'] = array_values(array_filter(array_map(
            fn ($id) => $ctx->channelKey((int) $id),
            (array) ($settings['channels'] ?? [])
        ), fn ($v) => $v !== null));

        $portable['categories'] = array_values(array_filter(array_map(
            fn ($id) => $ctx->categoryKey((int) $id),
            (array) ($settings['categories'] ?? [])
        ), fn ($v) => $v !== null));

        $portable['authors'] = [];
        foreach ((array) ($settings['authors'] ?? []) as $memberId) {
            $username = $ctx->memberKey((int) $memberId);
            if ($username === null) {
                $ctx->addWarning(sprintf(
                    lang('field_manager_pro_unresolved_author_export'),
                    $field->field_name,
                    $memberId
                ));
                continue;
            }
            $portable['authors'][] = $username;
        }

        // statuses already names; leave verbatim.

        return $portable;
    }

    protected function importTypeSettings(array $portable, ImportContext $ctx): array
    {
        $settings = $portable;
        $fieldName = $ctx->currentField();

        $settings['channels'] = array_values(array_filter(array_map(
            fn ($name) => $ctx->channelId((string) $name),
            (array) ($portable['channels'] ?? [])
        ), fn ($v) => $v !== null));

        $settings['categories'] = array_values(array_filter(array_map(
            fn ($key) => $ctx->categoryId((string) $key),
            (array) ($portable['categories'] ?? [])
        ), fn ($v) => $v !== null));

        $settings['authors'] = [];
        foreach ((array) ($portable['authors'] ?? []) as $identifier) {
            $memberId = $ctx->memberId((string) $identifier);
            if ($memberId === null) {
                $ctx->addWarning(sprintf(
                    lang('field_manager_pro_unresolved_author_import'),
                    $fieldName,
                    $identifier
                ));
                continue;
            }
            $settings['authors'][] = $memberId;
        }

        $settings['statuses'] = array_values(array_map(
            fn ($name) => $ctx->statusName((string) $name),
            (array) ($portable['statuses'] ?? [])
        ));

        return $settings;
    }

    protected function typeDependencies(array $portable): array
    {
        return array_map(
            fn ($name) => 'channel:' . $name,
            (array) ($portable['channels'] ?? [])
        );
    }
}
