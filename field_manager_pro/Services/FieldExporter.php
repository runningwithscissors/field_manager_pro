<?php

namespace YourCompany\FieldManagerPro\Services;

use ExpressionEngine\Model\Channel\Channel;
use ExpressionEngine\Model\Channel\ChannelField;
use ExpressionEngine\Model\Channel\ChannelFieldGroup;

/**
 * Handles marshalling EE models to portable JSON structures.
 */
class FieldExporter
{
    protected ValidationService $validator;

    public function __construct()
    {
        $this->validator = new ValidationService();
    }

    public function getChannelSummaries(): array
    {
        $summaries = [];
        foreach (ee('Model')->get('Channel')->all() as $channel) {
            $summaries[] = [
                'channel_id' => $channel->channel_id,
                'channel_title' => $channel->channel_title,
                'site_id' => $channel->site_id,
                'field_count' => $channel->CustomFields ? $channel->CustomFields->count() : 0,
            ];
        }

        return $summaries;
    }

    public function getFieldGroupSummaries(): array
    {
        $summaries = [];
        foreach (ee('Model')->get('ChannelFieldGroup')->all() as $group) {
            $summaries[] = [
                'group_id' => $group->group_id,
                'group_name' => $group->group_name,
                'site_id' => $group->site_id,
            ];
        }

        return $summaries;
    }

    public function getFieldSummaries(): array
    {
        $summaries = [];
        foreach (ee('Model')->get('ChannelField')->all() as $field) {
            $summaries[$field->field_name] = $this->formatField($field);
        }

        return array_values($summaries);
    }

    public function exportComplete(array $scope = []): array
    {
        $type = $scope['data_type'] ?? 'complete';
        $channelIds = $scope['channels'] ?? [];
        $groupIds = $scope['field_groups'] ?? [];
        $fieldIds = $scope['fields'] ?? [];
        $siteId = $scope['site_id'] ?? ee()->config->item('site_id');
        $fieldType = $scope['field_type'] ?? null;

        $channels = $this->exportChannels($channelIds, $siteId);
        $fields = $this->exportFields($fieldIds, $siteId, $fieldType);

        if (empty($fieldIds) && empty($fieldType)) {
            $fields = $this->mergeChannelFields($channels, $fields);
        }

        $fieldGroups = $this->exportFieldGroups($groupIds, $siteId);
        if (empty($groupIds)) {
            $groupNames = $this->collectGroupNames($channels);
            if (! empty($groupNames)) {
                $fieldGroups = $this->exportFieldGroupsByNames($groupNames, $siteId);
            }
        }

        return array_merge(
            $this->metadata($type, $siteId),
            [
                'channels' => $channels,
                'field_groups' => array_values($fieldGroups),
                'fields' => array_values($fields),
                'log' => $this->validator->getErrors(),
            ]
        );
    }

    public function exportChannels(array $channelIds = [], ?int $siteId = null): array
    {
        $query = ee('Model')->get('Channel');
        if (! empty($channelIds)) {
            $query->filter('channel_id', 'IN', $channelIds);
        }
        if (! empty($siteId)) {
            $query->filter('site_id', $siteId);
        }

        $channels = [];

        foreach ($query->all() as $channel) {
            $channels[] = $this->formatChannel($channel);
        }

        return $channels;
    }

    public function exportFieldGroups(array $groupIds = [], ?int $siteId = null): array
    {
        $query = ee('Model')->get('ChannelFieldGroup');
        if (! empty($groupIds)) {
            $query->filter('group_id', 'IN', $groupIds);
        }
        if (! empty($siteId)) {
            $query->filter('site_id', $siteId);
        }

        $groups = [];
        foreach ($query->all() as $group) {
            $groupFields = [];
            foreach ($group->ChannelFields as $field) {
                $groupFields[] = $this->formatField($field);
            }

            $groups[$group->group_name] = [
                'group_id' => $group->group_id,
                'site_id' => $group->site_id,
                'group_name' => $group->group_name,
                'fields' => $groupFields,
            ];
        }

        return $groups;
    }

    public function exportFields(array $fieldIds = [], ?int $siteId = null, ?string $fieldType = null): array
    {
        $query = ee('Model')->get('ChannelField');
        if (! empty($fieldIds)) {
            $query->filter('field_id', 'IN', $fieldIds);
        }
        if (! empty($siteId)) {
            $query->filter('site_id', $siteId);
        }
        if (! empty($fieldType)) {
            $query->filter('field_type', $fieldType);
        }

        $fields = [];
        foreach ($query->all() as $field) {
            $fields[] = $this->formatField($field);
        }

        return $fields;
    }

    protected function formatChannel(Channel $channel): array
    {
        $channelFields = [];
        foreach ($channel->CustomFields as $field) {
            $channelFields[] = $this->formatField($field);
        }

        $categoryGroupIds = [];
        foreach ($channel->CategoryGroups as $group) {
            $categoryGroupIds[] = $group->group_id;
        }

        $fieldGroupNames = [];
        foreach ($channel->FieldGroups as $group) {
            $fieldGroupNames[] = $group->group_name;
        }

        return [
            'channel_id' => $channel->channel_id,
            'channel_name' => $channel->channel_name,
            'channel_title' => $channel->channel_title,
            'site_id' => $channel->site_id,
            'status_group' => null,
            'category_group_ids' => $categoryGroupIds,
            'category_groups' => $this->collectCategoryGroups($channel),
            'field_groups' => $fieldGroupNames,
            'field_group' => $fieldGroupNames[0] ?? null,
            'channel_fields' => $channelFields,
            'statuses' => $this->collectStatuses($channel),
            'categories' => $this->collectCategories($channel),
            'settings' => $channel->toArray(),
        ];
    }

    protected function formatField(ChannelField $field): array
    {
        $settings = $field->getSettingsValues();

        return [
            'field_id' => $field->field_id,
            'site_id' => $field->site_id,
            'field_name' => $field->field_name,
            'field_label' => $field->field_label,
            'field_type' => $field->field_type,
            'field_order' => $field->field_order,
            'field_list_items' => $field->field_list_items,
            'field_instructions' => $field->field_instructions,
            'field_required' => $field->field_required,
            'field_search' => $field->field_search,
            'field_settings' => base64_encode(serialize($settings['field_settings'] ?? [])),
            'field_fmt' => $field->field_fmt,
            'field_show_fmt' => $field->field_show_fmt,
            'field_maxl' => $field->field_maxl,
            'field_ta_rows' => $field->field_ta_rows,
            'grid_config' => $settings['grid'] ?? [],
            'grid_columns' => $this->getGridColumns($field),
            'relationship_config' => $settings['rel'] ?? [],
            'file_config' => $settings['file'] ?? [],
            'list_config' => $this->normalizeListItems($field),
            'groups' => $this->collectGroups($field),
        ];
    }

    protected function normalizeListItems(ChannelField $field): array
    {
        $items = [];
        if (! empty($field->field_list_items)) {
            foreach (preg_split('/\r\n|\r|\n/', $field->field_list_items) as $line) {
                if (strpos($line, '=') !== false) {
                    [$value, $label] = explode('=', $line, 2);
                } else {
                    $value = $label = $line;
                }

                $items[] = [
                    'value' => $value,
                    'label' => $label,
                ];
            }
        }

        return $items;
    }

    protected function metadata(string $type, ?int $siteId = null): array
    {
        return [
            'export_version' => '1.0.0',
            'export_date' => gmdate('c'),
            'ee_version' => defined('APP_VER') ? APP_VER : '7.x.x',
            'site_id' => (int) ($siteId ?: ee()->config->item('site_id')),
            'data_type' => $type,
        ];
    }

    protected function collectGroups(ChannelField $field): array
    {
        $groups = [];
        foreach ($field->ChannelFieldGroups as $group) {
            $groups[] = $group->group_name;
        }

        return $groups;
    }

    protected function collectStatuses(Channel $channel): array
    {
        $statuses = [];
        foreach ($channel->Statuses as $status) {
            $statuses[] = [
                'status' => $status->status,
                'status_order' => $status->status_order,
                'highlight' => $status->highlight,
            ];
        }

        return $statuses;
    }

    protected function collectCategories(Channel $channel): array
    {
        $categories = [];
        foreach ($channel->CategoryGroups as $group) {
            foreach ($group->Categories as $category) {
                $categories[] = [
                    'group_name' => $group->group_name,
                    'cat_name' => $category->cat_name,
                    'cat_url_title' => $category->cat_url_title,
                ];
            }
        }

        return $categories;
    }

    protected function collectCategoryGroups(Channel $channel): array
    {
        $groups = [];
        foreach ($channel->CategoryGroups as $group) {
            $groups[] = [
                'group_id' => $group->group_id,
                'group_name' => $group->group_name,
            ];
        }

        return $groups;
    }

    protected function mergeChannelFields(array $channels, array $fields): array
    {
        $merged = [];
        foreach ($fields as $field) {
            $merged[$field['field_name']] = $field;
        }

        foreach ($channels as $channel) {
            foreach ($channel['channel_fields'] as $field) {
                $merged[$field['field_name']] = $field;
            }
        }

        return $merged;
    }

    protected function collectGroupNames(array $channels): array
    {
        $names = [];
        foreach ($channels as $channel) {
            foreach ($channel['field_groups'] ?? [] as $groupName) {
                $names[] = $groupName;
            }

            foreach ($channel['channel_fields'] as $field) {
                foreach ($field['groups'] ?? [] as $groupName) {
                    $names[] = $groupName;
                }
            }
        }

        return array_unique(array_map('trim', array_filter($names)));
    }

    protected function exportFieldGroupsByNames(array $names, ?int $siteId = null): array
    {
        if (empty($names)) {
            return [];
        }

        $query = ee('Model')->get('ChannelFieldGroup')
            ->filter('group_name', 'IN', array_unique($names));

        if (! empty($siteId)) {
            $query->filter('site_id', $siteId);
        }

        $groups = [];
        foreach ($query->all() as $group) {
            $groupFields = [];
            foreach ($group->ChannelFields as $field) {
                $groupFields[] = $this->formatField($field);
            }

            $groups[$group->group_name] = [
                'group_id' => $group->group_id,
                'site_id' => $group->site_id,
                'group_name' => $group->group_name,
                'fields' => $groupFields,
            ];
        }

        return $groups;
    }

    protected function getGridColumns(ChannelField $field): array
    {
        if ($field->field_type !== 'grid') {
            return [];
        }

        ee()->load->model('grid_model');
        $columns = ee()->grid_model->get_columns_for_field($field->field_id, 'channel', false);
        $export = [];

        foreach ($columns as $column) {
            $export[] = [
                'col_type' => $column['col_type'],
                'col_label' => $column['col_label'],
                'col_name' => $column['col_name'],
                'col_instructions' => $column['col_instructions'],
                'col_required' => $column['col_required'],
                'col_search' => $column['col_search'],
                'col_width' => $column['col_width'],
                'col_settings' => $column['col_settings'],
            ];
        }

        return $export;
    }
}
