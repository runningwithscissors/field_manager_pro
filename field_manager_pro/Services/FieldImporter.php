<?php

namespace YourCompany\FieldManagerPro\Services;

use ExpressionEngine\Model\Channel\Channel;
use ExpressionEngine\Model\Channel\ChannelField;
use ExpressionEngine\Model\Category\Category;
use ExpressionEngine\Model\Status\Status;

/**
 * Handles JSON parsing plus model creation during imports.
 */
class FieldImporter
{
    protected ValidationService $validator;

    protected array $errors = [];

    public function __construct()
    {
        $this->validator = new ValidationService();
    }

    public function preview(string $payload): array
    {
        $decoded = $this->validator->validateJsonStructure($payload);
        if (empty($decoded)) {
            $this->errors = $this->validator->getErrors();

            return [];
        }

        return [
            'channels' => count($decoded['channels'] ?? []),
            'field_groups' => count($decoded['field_groups'] ?? []),
            'fields' => count($decoded['fields'] ?? []),
            'data_type' => $decoded['data_type'] ?? 'complete',
        ];
    }

    public function import(string $payload, string $strategy = 'prompt'): array
    {
        $decoded = $this->validator->validateJsonStructure($payload);
        if (empty($decoded)) {
            $this->errors = $this->validator->getErrors();

            return ['success' => false, 'errors' => $this->errors];
        }

        ee()->db->trans_start();

        $fields = $this->normalizeFieldPayloads($decoded);
        $groupPayloads = $decoded['field_groups'] ?? [];
        if (empty($groupPayloads)) {
            $groupPayloads = $this->collectGroupsFromData($decoded['channels'] ?? [], $fields);
        }
        $result = [
            'field_groups' => $this->importFieldGroups($groupPayloads),
            'fields' => $this->importFields($fields, $strategy),
            'channels' => $this->importChannels($decoded['channels'] ?? []),
        ];

        ee()->db->trans_complete();

        if (ee()->db->trans_status() === false) {
            $this->errors[] = lang('field_manager_pro_import_transaction_failed');
        }

        $result['success'] = empty($this->errors);
        $result['errors'] = $this->getLastErrors();

        return $result;
    }

    protected function normalizeFieldPayloads(array $decoded): array
    {
        $fields = [];
        if (! empty($decoded['fields']) && is_array($decoded['fields'])) {
            foreach ($decoded['fields'] as $field) {
                if (! empty($field['field_name'])) {
                    $fields[$field['field_name']] = $field;
                }
            }
        }

        if (empty($fields) && ! empty($decoded['channels'])) {
            foreach ($decoded['channels'] as $channel) {
                foreach ($channel['channel_fields'] ?? [] as $field) {
                    if (! empty($field['field_name'])) {
                        $fields[$field['field_name']] = $field;
                    }
                }
            }
        }

        return array_values($fields);
    }

    protected function collectGroupsFromData(array $channels, array $fields): array
    {
        $groups = [];
        $defaultSite = (int) ee()->config->item('site_id');

        foreach ($channels as $channel) {
            $channelSite = (int) ($channel['site_id'] ?? $defaultSite);
            if ($channelSite < 1) {
                $channelSite = $defaultSite;
            }

            $channelGroups = $channel['field_groups'] ?? [];
            if (! empty($channel['field_group'])) {
                $channelGroups[] = $channel['field_group'];
            }

            foreach ($channelGroups as $groupName) {
                $groups[$groupName] = [
                    'group_name' => $groupName,
                    'site_id' => $channelSite,
                ];
            }
        }

        foreach ($fields as $field) {
            $fieldSite = (int) ($field['site_id'] ?? $defaultSite);
            if ($fieldSite < 1) {
                $fieldSite = $defaultSite;
            }

            foreach ($field['groups'] ?? [] as $groupName) {
                $groups[$groupName] = [
                    'group_name' => $groupName,
                    'site_id' => $fieldSite,
                ];
            }
        }

        return array_values($groups);
    }

    public function getLastErrors(): array
    {
        return array_unique(array_merge($this->errors, $this->validator->getErrors()));
    }

    protected function importFields(array $fields, string $strategy): array
    {
        $imported = [];

        foreach ($fields as $payload) {
            $siteId = (int) ($payload['site_id'] ?? ee()->config->item('site_id'));
            if ($siteId < 1) {
                $siteId = (int) ee()->config->item('site_id');
            }
            $fieldName = $payload['field_name'] ?? '';

            $existing = ee('Model')->get('ChannelField')
                ->filter('site_id', $siteId)
                ->filter('field_name', $fieldName)
                ->first();

            if ($existing) {
                $resolvedName = $this->resolveConflict($fieldName, $strategy);
                if ($resolvedName === null) {
                    $this->errors[] = sprintf(lang('field_manager_pro_conflict_requires_action'), $fieldName);
                    $imported[] = ['field_name' => $fieldName, 'status' => 'skipped'];
                    continue;
                }

                if ($strategy === 'overwrite') {
                    $existing->delete();
                }

                $payload['field_name'] = $resolvedName;
            }

            if (! $this->validator->validateFieldName($payload['field_name'], $siteId)) {
                $this->errors[] = sprintf(lang('field_manager_pro_cannot_create_field'), $fieldName);
                continue;
            }

            if (! empty($payload['field_type']) && ! $this->fieldtypeAvailable($payload['field_type'])) {
                $this->errors[] = sprintf(lang('field_manager_pro_missing_fieldtype'), $payload['field_type']);
                continue;
            }

            $field = $this->makeField($payload, $siteId);

            $validation = $field->validate();
            if ($validation->isValid()) {
                $field->save();
                $this->assignGroups($field, $payload['groups'] ?? []);
                $this->syncGridColumns($field, $payload['grid_columns'] ?? []);
                $imported[] = ['field_name' => $field->field_name, 'status' => 'imported'];
            } else {
                $this->errors = array_merge($this->errors, $validation->getAllErrors());
            }
        }

        return $imported;
    }

    protected function importFieldGroups(array $groups): array
    {
        $result = [];
        foreach ($groups as $groupPayload) {
            $group = ee('Model')->get('ChannelFieldGroup')
                ->filter('group_name', $groupPayload['group_name'])
                ->filter('site_id', $groupPayload['site_id'] ?? ee()->config->item('site_id'))
                ->first();

            if (! $group) {
                $group = ee('Model')->make('ChannelFieldGroup');
                $group->group_name = $groupPayload['group_name'];
                $group->site_id = $groupPayload['site_id'] ?? ee()->config->item('site_id');
                $group->short_name = $groupPayload['short_name'] ?? $this->generateGroupShortName($groupPayload['group_name']);
            } else {
                if (! empty($groupPayload['short_name'])) {
                    $group->short_name = $groupPayload['short_name'];
                }
            }

            $group->save();
            $result[] = ['group_name' => $group->group_name];
        }

        return $result;
    }

    protected function importChannels(array $channels): array
    {
        $result = [];
        foreach ($channels as $channelPayload) {
            $channel = ee('Model')->get('Channel')
                ->filter('channel_name', $channelPayload['channel_name'])
                ->first();

            if (! $channel) {
                $channel = ee('Model')->make('Channel');
            }

            foreach ($channelPayload['settings'] ?? [] as $key => $value) {
                if ($channel->hasProperty($key)) {
                    $channel->setProperty($key, $value);
                }
            }

            $channel->save();
            $channelGroups = $channelPayload['field_groups'] ?? [];
            if (! empty($channelPayload['field_group'])) {
                $channelGroups[] = $channelPayload['field_group'];
            }
            $this->attachFieldGroups($channel, $channelGroups);
            $this->attachCategoryGroups($channel, $channelPayload['category_groups'] ?? []);
            $this->syncChannelStatuses($channel, $channelPayload['statuses'] ?? []);
            $this->syncChannelCategories($channel, $channelPayload['categories'] ?? []);
            $result[] = ['channel_name' => $channel->channel_name];
        }

        return $result;
    }

    protected function resolveConflict(string $name, string $strategy): ?string
    {
        return match ($strategy) {
            'skip' => null,
            'prompt' => null,
            'rename' => $name . '_' . ee()->localize->format_date('%Y%m%d%H%M%S'),
            'overwrite' => $name,
            default => $name,
        };
    }

    protected function makeField(array $payload, int $siteId): ChannelField
    {
        /** @var ChannelField $field */
        $field = ee('Model')->make('ChannelField');
        $field->site_id = $siteId;
        $field->field_name = $payload['field_name'];
        $field->field_label = $payload['field_label'] ?? $payload['field_name'];
        $field->field_type = $payload['field_type'] ?? 'text';
        $field->field_list_items = $payload['field_list_items'] ?? '';
        $field->field_order = $payload['field_order'] ?? 0;
        $field->field_instructions = $payload['field_instructions'] ?? '';
        $field->field_required = $payload['field_required'] ?? 'n';
        $field->field_search = $payload['field_search'] ?? 'n';
        $field->field_fmt = $payload['field_fmt'] ?? 'none';
        $field->field_show_fmt = $payload['field_show_fmt'] ?? 'n';
        $field->field_maxl = $payload['field_maxl'] ?? 0;
        $field->field_ta_rows = $payload['field_ta_rows'] ?? 0;

        $settings = $this->unpackSettings($payload['field_settings'] ?? '');
        $field->setProperty('field_settings', $settings);

        return $field;
    }

    protected function assignGroups(ChannelField $field, array $groups): void
    {
        if (empty($groups)) {
            return;
        }

        $groupModels = ee('Model')->get('ChannelFieldGroup')
            ->filter('group_name', 'IN', $groups)
            ->all();

        if ($groupModels->count() > 0) {
            $field->ChannelFieldGroups = $groupModels;
            $field->save();
        }
    }

    protected function unpackSettings(string $serialized): array
    {
        if (empty($serialized)) {
            return [];
        }

        $decoded = base64_decode($serialized, true);
        if ($decoded === false) {
            $this->errors[] = lang('field_manager_pro_settings_decode_failed');

            return [];
        }

        $settings = @unserialize($decoded);
        if (! is_array($settings)) {
            $this->errors[] = lang('field_manager_pro_settings_unserialize_failed');

            return [];
        }

        return $settings;
    }

    protected function attachFieldGroups(Channel $channel, array $groups): void
    {
        if (empty($groups)) {
            return;
        }

        $groupModels = ee('Model')->get('ee:ChannelFieldGroup')
            ->filter('group_name', 'IN', $groups)
            ->all();

        if ($groupModels->count() > 0) {
            $channel->FieldGroups = $groupModels;
            $channel->save();
        }
    }

    protected function attachCategoryGroups(Channel $channel, array $groups): void
    {
        if (empty($groups)) {
            return;
        }

        $groupIds = [];
        foreach ($groups as $groupData) {
        $group = ee('Model')->get('ee:CategoryGroup')
                ->filter('group_name', $groupData['group_name'])
                ->first();
            if ($group) {
                $groupIds[] = $group->group_id;
            }
        }

        if (! empty($groupIds)) {
            $channel->CategoryGroups = ee('Model')->get('ee:CategoryGroup')
                ->filter('group_id', 'IN', $groupIds)
                ->all();
            $channel->save();
        }
    }

    protected function syncChannelStatuses(Channel $channel, array $statuses): void
    {
        if (empty($statuses)) {
            return;
        }

        $statusNames = [];
        foreach ($statuses as $statusData) {
            $status = ee('Model')->get('ee:Status')
                ->filter('status', $statusData['status'])
                ->first();

            if (! $status) {
                $status = ee('Model')->make('ee:Status');
                $status->site_id = $channel->site_id;
            }

            $status->status = $statusData['status'];
            $status->status_order = $statusData['status_order'] ?? 0;
            $status->highlight = $statusData['highlight'] ?? '';
            $status->save();
            $statusNames[] = $status->status;
        }

        if (! empty($statusNames)) {
            $channel->Statuses = ee('Model')->get('ee:Status')
                ->filter('status', 'IN', $statusNames)
                ->all();
            $channel->save();
        }
    }

    protected function syncChannelCategories(Channel $channel, array $categories): void
    {
        if (empty($categories)) {
            return;
        }

        foreach ($categories as $categoryData) {
            if (empty($categoryData['group_name'])) {
                continue;
            }

            $group = ee('Model')->get('ee:CategoryGroup')
                ->filter('group_name', $categoryData['group_name'])
                ->first();

            if (! $group) {
                continue;
            }

            $category = ee('Model')->get('ee:Category')
                ->filter('group_id', $group->group_id)
                ->filter('cat_url_title', $categoryData['cat_url_title'])
                ->first();

            if (! $category) {
                $category = ee('Model')->make('ee:Category');
                $category->group_id = $group->group_id;
            }

            $category->cat_name = $categoryData['cat_name'];
            $category->cat_url_title = $categoryData['cat_url_title'];
            $category->save();
        }
    }

    protected function fieldtypeAvailable(string $type): bool
    {
        return (bool) ee('Model')->get('Fieldtype')
            ->filter('name', $type)
            ->first();
    }

    protected function syncGridColumns(ChannelField $field, array $columns): void
    {
        if ($field->field_type !== 'grid' || empty($columns)) {
            return;
        }

        ee()->load->model('grid_model');
        ee()->grid_model->create_field($field->field_id, 'channel');
        $existing = ee()->grid_model->get_columns_for_field($field->field_id, 'channel', false);

        if (! empty($existing)) {
            $existingTypes = [];
            foreach ($existing as $column) {
                $existingTypes[$column['col_id']] = $column['col_type'];
            }

            if (! empty($existingTypes)) {
                ee()->grid_model->delete_columns(
                    array_keys($existingTypes),
                    $existingTypes,
                    $field->field_id,
                    'channel'
                );
            }
        }

        $order = 0;
        foreach ($columns as $column) {
            $required = ($column['col_required'] ?? 'n');
            $searchable = ($column['col_search'] ?? 'n');

            $columnData = [
                'field_id' => $field->field_id,
                'content_type' => 'channel',
                'col_order' => $order++,
                'col_type' => $column['col_type'],
                'col_label' => $column['col_label'],
                'col_name' => $column['col_name'],
                'col_instructions' => $column['col_instructions'] ?? '',
                'col_required' => ($required === 'y' || $required === true) ? 'y' : 'n',
                'col_search' => ($searchable === 'y' || $searchable === true) ? 'y' : 'n',
                'col_width' => $column['col_width'] ?? 0,
                'col_settings' => json_encode($column['col_settings'] ?? []),
            ];

            ee()->grid_model->save_col_settings($columnData, false, 'channel');
        }
    }

    protected function generateGroupShortName(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9\-_]+/', '_', strtolower($name));
        $slug = trim($slug, '_');
        if ($slug === '') {
            $slug = 'field_group';
        }

        return substr('field_group_' . $slug, 0, 50);
    }
}
