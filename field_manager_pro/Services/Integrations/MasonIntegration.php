<?php

namespace YourCompany\FieldManagerPro\Services\Integrations;

class MasonIntegration extends AbstractFieldtypeIntegration
{
    protected ?bool $available = null;

    public function identifier(): string
    {
        return 'mason';
    }

    public function supportedFieldtypes(): array
    {
        return ['mason'];
    }

    public function export(array $fields, array $channels): array
    {
        if (! $this->shouldProcess($fields, $channels) || ! $this->isAvailable()) {
            return [];
        }

        $table = $this->table();
        if (! ee()->db->table_exists($table)) {
            $this->addError(lang('field_manager_pro_mason_table_missing'));

            return [];
        }

        $query = ee()->db->order_by('name', 'asc')->get($table);
        $blockTypes = [];

        foreach ($query->result_array() as $row) {
            $blockTypes[] = [
                'name' => $row['name'],
                'label' => $row['label'],
                'icon' => $row['icon'],
                'description' => $row['description'],
                'fields' => $this->decodeFields($row['fields_data'] ?? ''),
                'created' => (int) $row['created'],
                'modified' => (int) $row['modified'],
            ];
        }

        return ['block_types' => $blockTypes];
    }

    public function import(array $payload, array $fieldModels): void
    {
        if (empty($payload['block_types']) || ! $this->isAvailable()) {
            return;
        }

        $table = $this->table();
        if (! ee()->db->table_exists($table)) {
            $this->addError(lang('field_manager_pro_mason_table_missing'));

            return;
        }

        foreach ($payload['block_types'] as $blockType) {
            $this->upsertBlockType($blockType);
        }
    }

    protected function shouldProcess(array $fields, array $channels): bool
    {
        foreach ($fields as $field) {
            if (($field['field_type'] ?? null) === 'mason') {
                return true;
            }
        }

        foreach ($channels as $channel) {
            foreach ($channel['channel_fields'] ?? [] as $field) {
                if (($field['field_type'] ?? null) === 'mason') {
                    return true;
                }
            }
        }

        return false;
    }

    protected function table(): string
    {
        return ee()->db->dbprefix . 'mason_block_types';
    }

    protected function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $fieldtype = ee('Model')->get('Fieldtype')
            ->filter('name', 'mason')
            ->first();

        $this->available = ($fieldtype !== null);
        if (! $this->available) {
            $this->addError(lang('field_manager_pro_mason_missing'));
        }

        return $this->available;
    }

    protected function decodeFields(?string $json): array
    {
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function upsertBlockType(array $blockType): void
    {
        $name = trim((string) ($blockType['name'] ?? ''));
        if ($name === '') {
            return;
        }

        $table = $this->table();
        $fields = $blockType['fields'] ?? [];
        $payload = [
            'name' => $name,
            'label' => $blockType['label'] ?? $name,
            'icon' => $blockType['icon'] ?? '',
            'description' => $blockType['description'] ?? '',
            'fields_data' => json_encode($fields ?? []),
        ];

        $existing = ee()->db->select('id, created')
            ->where('name', $name)
            ->get($table)
            ->row_array();

        $now = time();

        if ($existing) {
            $payload['modified'] = $now;

            ee()->db->where('id', $existing['id'])
                ->update($table, $payload);
        } else {
            $payload['created'] = $blockType['created'] ?? $now;
            $payload['modified'] = $blockType['modified'] ?? $now;

            if (empty($payload['created'])) {
                $payload['created'] = $now;
            }
            if (empty($payload['modified'])) {
                $payload['modified'] = $now;
            }

            ee()->db->insert($table, $payload);
        }
    }
}
