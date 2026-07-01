<?php

namespace YourCompany\FieldManagerPro\Services\Integrations;

class BloqsIntegration extends AbstractFieldtypeIntegration
{
    protected $adapter;

    protected ?bool $available = null;

    public function identifier(): string
    {
        return 'bloqs';
    }

    public function supportedFieldtypes(): array
    {
        return ['bloqs'];
    }

    public function export(array $fields, array $channels): array
    {
        $bloqsFields = $this->collectBloqsFields($fields, $channels);
        if (empty($bloqsFields) || ! $this->isAvailable()) {
            return [];
        }

        $adapter = $this->getAdapter();
        if (! $adapter) {
            return [];
        }

        $groups = $this->exportBlockGroups($adapter);
        $definitions = $this->exportBlockDefinitions($adapter, $groups['lookup']);
        $fieldUsage = $this->exportFieldUsage($bloqsFields, $definitions['lookup']);

        return [
            'groups' => $groups['payload'],
            'definitions' => $definitions['payload'],
            'field_usage' => $fieldUsage,
        ];
    }

    public function import(array $payload, array $fieldModels): void
    {
        if (empty($payload) || ! $this->isAvailable()) {
            return;
        }

        $adapter = $this->getAdapter();
        if (! $adapter) {
            return;
        }

        $groupMap = $this->syncGroups($adapter, $payload['groups'] ?? []);
        $definitionMap = $this->syncDefinitions($adapter, $payload['definitions'] ?? [], $groupMap);
        $this->syncComponents($payload['definitions'] ?? [], $definitionMap);
        $this->syncFieldUsage($adapter, $payload['field_usage'] ?? [], $fieldModels, $definitionMap);
    }

    protected function collectBloqsFields(array $fields, array $channels): array
    {
        $collection = [];

        foreach ($fields as $field) {
            if (($field['field_type'] ?? null) === 'bloqs' && ! empty($field['field_id'])) {
                $collection[$field['field_name']] = (int) $field['field_id'];
            }
        }

        foreach ($channels as $channel) {
            foreach ($channel['channel_fields'] ?? [] as $field) {
                if (($field['field_type'] ?? null) === 'bloqs' && ! empty($field['field_id'])) {
                    $collection[$field['field_name']] = (int) $field['field_id'];
                }
            }
        }

        return $collection;
    }

    protected function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        $fieldtype = ee('Model')->get('Fieldtype')
            ->filter('name', 'bloqs')
            ->first();

        $this->available = ($fieldtype !== null) && is_dir(PATH_THIRD . 'bloqs/');
        if (! $this->available) {
            $this->addError(lang('field_manager_pro_bloqs_missing'));
        }

        return $this->available;
    }

    protected function getAdapter()
    {
        if ($this->adapter) {
            return $this->adapter;
        }

        try {
            if (isset(ee()->di) && method_exists(ee()->di, 'has') && ee()->di->has('bloqs:Adapter')) {
                $this->adapter = ee('bloqs:Adapter');

                return $this->adapter;
            }

            $adapterClass = '\\BoldMinded\\Bloqs\\Database\\Adapter';
            $adapterPath = PATH_THIRD . 'bloqs/Database/Adapter.php';

            if (! class_exists($adapterClass) && file_exists($adapterPath)) {
                require_once $adapterPath;
            }

            if (class_exists($adapterClass)) {
                $this->adapter = new $adapterClass(ee());
            }
        } catch (\Throwable $exception) {
            $this->addError($exception->getMessage());
            $this->adapter = null;
        }

        if (! $this->adapter) {
            $this->addError(lang('field_manager_pro_bloqs_adapter_missing'));
        }

        return $this->adapter;
    }

    protected function exportBlockGroups($adapter): array
    {
        $payload = [];
        $lookup = [];

        if (! method_exists($adapter, 'getBlockGroups')) {
            return ['payload' => $payload, 'lookup' => $lookup];
        }

        foreach ($adapter->getBlockGroups() as $group) {
            $payload[] = [
                'name' => $group->getName(),
                'order' => (int) $group->getOrder(),
            ];
            $lookup[$group->getId()] = $group->getName();
        }

        return ['payload' => $payload, 'lookup' => $lookup];
    }

    protected function exportBlockDefinitions($adapter, array $groupLookup): array
    {
        if (! method_exists($adapter, 'getBlockDefinitions')) {
            return ['payload' => [], 'lookup' => []];
        }

        $definitions = $adapter->getBlockDefinitions();
        $idLookup = [];

        foreach ($definitions as $definition) {
            $idLookup[$definition->getId()] = $definition->getShortName();
        }

        $payload = [];
        foreach ($definitions as $definition) {
            $payload[] = $this->formatDefinition($definition, $groupLookup, $idLookup);
        }

        return ['payload' => $payload, 'lookup' => $idLookup];
    }

    protected function formatDefinition($definition, array $groupLookup, array $definitionLookup): array
    {
        $atoms = [];
        foreach ($definition->getAtomDefinitions() as $atom) {
            $atoms[] = [
                'short_name' => $atom->getShortName(),
                'name' => $atom->getName(),
                'instructions' => $atom->getInstructions(),
                'order' => (int) $atom->getOrder(),
                'type' => $atom->getType(),
                'settings' => $this->normalizeSettings($atom->getSettings()),
            ];
        }

        $componentRows = $definition->isComponent()
            ? $this->exportComponentRows($definition->getId(), $definitionLookup)
            : [];

        return [
            'short_name' => $definition->getShortName(),
            'name' => $definition->getName(),
            'instructions' => $definition->getInstructions(),
            'group' => $groupLookup[$definition->getGroupId()] ?? null,
            'deprecated' => $definition->isDeprecated(),
            'deprecated_note' => $definition->getDeprecatedNote(),
            'preview_image' => $definition->getPreviewImage(),
            'preview_icon' => $definition->getPreviewIcon(),
            'settings' => $this->normalizeSettings($definition->getSettings()),
            'is_component' => $definition->isComponent(),
            'is_editable' => $definition->isEditable(),
            'atoms' => $atoms,
            'component_rows' => $componentRows,
        ];
    }

    protected function normalizeSettings($settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (is_object($settings)) {
            $encoded = json_encode($settings);
            if ($encoded === false) {
                return [];
            }

            $decoded = json_decode($encoded, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function exportComponentRows(int $definitionId, array $definitionLookup): array
    {
        $table = ee()->db->dbprefix . 'blocks_components';
        $query = ee()->db->select('id, blockdefinition_id, parent_id, depth, lft, rgt, `order` AS sort_order, cloneable')
            ->from($table)
            ->where('componentdefinition_id', $definitionId)
            ->order_by('lft', 'asc')
            ->get();

        $rows = [];
        foreach ($query->result_array() as $row) {
            $blockShortName = $definitionLookup[(int) $row['blockdefinition_id']] ?? null;
            if ($blockShortName === null) {
                continue;
            }

            $rows[] = [
                'row_id' => (int) $row['id'],
                'block_short_name' => $blockShortName,
                'parent_row_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'order' => (int) $row['sort_order'],
                'depth' => (int) $row['depth'],
                'lft' => (int) $row['lft'],
                'rgt' => (int) $row['rgt'],
                'cloneable' => (int) $row['cloneable'],
            ];
        }

        return $rows;
    }

    protected function exportFieldUsage(array $fields, array $definitions): array
    {
        $table = ee()->db->dbprefix . 'blocks_blockfieldusage';
        $fieldUsage = [];

        foreach ($fields as $fieldName => $fieldId) {
            $query = ee()->db->select('blockdefinition_id, `order` AS sort_order')
                ->from($table)
                ->where('field_id', $fieldId)
                ->order_by('sort_order', 'asc')
                ->get();

            $assignments = [];
            foreach ($query->result_array() as $row) {
                $shortName = $definitions[(int) $row['blockdefinition_id']] ?? null;
                if ($shortName === null) {
                    continue;
                }

                $assignments[] = [
                    'definition' => $shortName,
                    'order' => (int) $row['sort_order'],
                ];
            }

            if (! empty($assignments)) {
                $fieldUsage[$fieldName] = $assignments;
            }
        }

        return $fieldUsage;
    }

    protected function syncGroups($adapter, array $groups): array
    {
        $this->ensureBloqsClass('\\BoldMinded\\Bloqs\\Entity\\BlockGroup', 'Entity/BlockGroup.php');

        $existing = [];
        if (method_exists($adapter, 'getBlockGroups')) {
            foreach ($adapter->getBlockGroups() as $group) {
                $existing[strtolower($group->getName())] = $group;
            }
        }

        $groupMap = [];
        foreach ($groups as $groupPayload) {
            $name = trim((string) ($groupPayload['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = strtolower($name);
            if (isset($existing[$key])) {
                $group = $existing[$key];
                $group->setOrder((int) ($groupPayload['order'] ?? 0));
                $adapter->updateBlockGroup($group);
            } else {
                $groupClass = '\\BoldMinded\\Bloqs\\Entity\\BlockGroup';
                if (! class_exists($groupClass)) {
                    $this->addError(lang('field_manager_pro_bloqs_group_class_missing'));
                    break;
                }

                $group = new $groupClass();
                $group->setName($name);
                $group->setOrder((int) ($groupPayload['order'] ?? 0));
                $adapter->createBlockGroup($group);
                $existing[$key] = $group;
            }

            $groupMap[$name] = $group->getId();
        }

        return $groupMap;
    }

    protected function syncDefinitions($adapter, array $definitions, array $groupMap): array
    {
        $definitionClass = '\\BoldMinded\\Bloqs\\Entity\\BlockDefinition';
        $atomClass = '\\BoldMinded\\Bloqs\\Entity\\AtomDefinition';

        $this->ensureBloqsClass($definitionClass, 'Entity/BlockDefinition.php');
        $this->ensureBloqsClass($atomClass, 'Entity/AtomDefinition.php');

        if (! class_exists($definitionClass) || ! class_exists($atomClass)) {
            $this->addError(lang('field_manager_pro_bloqs_class_missing'));

            return [];
        }

        $definitionMap = [];

        foreach ($definitions as $definitionPayload) {
            $shortName = trim((string) ($definitionPayload['short_name'] ?? ''));
            if ($shortName === '') {
                continue;
            }

            $existing = $adapter->getBlockDefinitionByShortname($shortName);
            if ($existing) {
                $adapter->deleteBlockDefinition($existing->getId());
            }

            $definition = new $definitionClass();
            $definition->setShortName($shortName);
            $definition->setName($definitionPayload['name'] ?? $shortName);
            $definition->setInstructions($definitionPayload['instructions'] ?? '');
            $definition->setGroupId($groupMap[$definitionPayload['group'] ?? ''] ?? 0);
            $definition->setDeprecated(! empty($definitionPayload['deprecated']));
            $definition->setDeprecatedNote($definitionPayload['deprecated_note'] ?? '');
            $definition->setPreviewImage($definitionPayload['preview_image'] ?? '');
            $definition->setPreviewIcon($definitionPayload['preview_icon'] ?? '');
            $definition->setSettings($definitionPayload['settings'] ?? []);
            $definition->setIsComponent(! empty($definitionPayload['is_component']) ? 1 : 0);
            $definition->setIsEditable(! empty($definitionPayload['is_editable']) ? 1 : 0);

            $adapter->createBlockDefinition($definition);

            foreach ($definitionPayload['atoms'] ?? [] as $atomPayload) {
                $atomShortName = trim((string) ($atomPayload['short_name'] ?? ''));
                if ($atomShortName === '') {
                    continue;
                }

                $atom = new $atomClass();
                $atom->setShortName($atomShortName);
                $atom->setName($atomPayload['name'] ?? $atomShortName);
                $atom->setInstructions($atomPayload['instructions'] ?? '');
                $atom->setOrder((int) ($atomPayload['order'] ?? 0));
                $atom->setType($atomPayload['type'] ?? 'text');
                $atom->setSettings($atomPayload['settings'] ?? []);

                $adapter->createAtomDefinition($definition->getId(), $atom);
            }

            $definitionMap[$shortName] = $definition->getId();
        }

        return $definitionMap;
    }

    protected function syncComponents(array $definitions, array $definitionIds): void
    {
        if (empty($definitions) || empty($definitionIds)) {
            return;
        }

        $table = ee()->db->dbprefix . 'blocks_components';

        foreach ($definitions as $definitionPayload) {
            $shortName = $definitionPayload['short_name'] ?? '';
            $componentRows = $definitionPayload['component_rows'] ?? [];
            if ($shortName === '' || empty($componentRows)) {
                continue;
            }

            $componentId = $definitionIds[$shortName] ?? null;
            if (! $componentId) {
                continue;
            }

            ee()->db->where('componentdefinition_id', $componentId)->delete($table);

            usort($componentRows, function ($a, $b) {
                $depthA = $a['depth'] ?? 0;
                $depthB = $b['depth'] ?? 0;

                if ($depthA === $depthB) {
                    return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
                }

                return $depthA <=> $depthB;
            });

            $rowMap = [];
            foreach ($componentRows as $row) {
                $blockShortName = $row['block_short_name'] ?? '';
                $blockDefinitionId = $definitionIds[$blockShortName] ?? null;
                if (! $blockDefinitionId) {
                    $this->addError(sprintf(lang('field_manager_pro_bloqs_definition_missing'), $blockShortName));
                    continue;
                }

                $parentRowId = $row['parent_row_id'] ?? null;
                $parentId = 0;
                if ($parentRowId !== null) {
                    $parentId = $rowMap[$parentRowId] ?? 0;
                }

                $data = [
                    'blockdefinition_id' => $blockDefinitionId,
                    'componentdefinition_id' => $componentId,
                    'order' => (int) ($row['order'] ?? 0),
                    'parent_id' => $parentId,
                    'depth' => (int) ($row['depth'] ?? 0),
                    'lft' => (int) ($row['lft'] ?? 0),
                    'rgt' => (int) ($row['rgt'] ?? 0),
                    'cloneable' => (int) ($row['cloneable'] ?? 0),
                ];

                ee()->db->insert($table, $data);
                if (isset($row['row_id'])) {
                    $rowMap[$row['row_id']] = ee()->db->insert_id();
                }
            }
        }
    }

    protected function syncFieldUsage($adapter, array $fieldUsage, array $fieldModels, array $definitionIds): void
    {
        if (empty($fieldUsage) || empty($definitionIds)) {
            return;
        }

        $table = ee()->db->dbprefix . 'blocks_blockfieldusage';

        foreach ($fieldUsage as $fieldName => $assignments) {
            if (! isset($fieldModels[$fieldName])) {
                $this->addError(sprintf(lang('field_manager_pro_bloqs_field_missing'), $fieldName));
                continue;
            }

            $field = $fieldModels[$fieldName];
            $fieldId = (int) $field->field_id;

            ee()->db->where('field_id', $fieldId)->delete($table);

            $definitionOrder = [];
            foreach ($assignments as $index => $assignment) {
                $shortName = $assignment['definition'] ?? '';
                $definitionId = $definitionIds[$shortName] ?? null;
                if (! $definitionId) {
                    $this->addError(sprintf(lang('field_manager_pro_bloqs_definition_missing'), $shortName));
                    continue;
                }

                $order = (int) ($assignment['order'] ?? $index);
                $adapter->associateBlockDefinitionWithField($fieldId, $definitionId, $order);
                $definitionOrder[] = $definitionId;
            }

            if (! empty($definitionOrder)) {
                $settings = $field->getProperty('field_settings');
                if (! is_array($settings)) {
                    $settings = [];
                }

                $settings['blockDefinitions'] = $definitionOrder;
                $field->setProperty('field_settings', $settings);
                $field->save();
            }
        }
    }

    protected function ensureBloqsClass(string $class, string $relativePath): void
    {
        if (class_exists($class)) {
            return;
        }

        $path = PATH_THIRD . 'bloqs/' . ltrim($relativePath, '/');
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
