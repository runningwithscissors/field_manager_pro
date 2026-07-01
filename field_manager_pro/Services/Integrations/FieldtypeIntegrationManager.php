<?php

namespace YourCompany\FieldManagerPro\Services\Integrations;

class FieldtypeIntegrationManager
{
    /**
     * @var FieldtypeIntegrationInterface[]
     */
    protected array $adapters = [];

    public function registerAdapter(FieldtypeIntegrationInterface $adapter): void
    {
        $this->adapters[$adapter->identifier()] = $adapter;
    }

    /**
     * Allow third-party code to register adapters via Extension hook.
     */
    public function bootExternalAdapters(): void
    {
        if (! function_exists('ee') || ! isset(ee()->extensions)) {
            return;
        }

        if (ee()->extensions->active_hook('field_manager_pro_register_integrations') === true) {
            ee()->extensions->call('field_manager_pro_register_integrations', $this);
        }
    }

    public function export(array $fields, array $channels): array
    {
        $payload = [];

        foreach ($this->adapters as $adapter) {
            $data = $adapter->export($fields, $channels);
            if (! empty($data)) {
                $payload[$adapter->identifier()] = $data;
            }
        }

        return $payload;
    }

    public function import(array $integrations, array $fieldModels): array
    {
        foreach ($this->adapters as $identifier => $adapter) {
            if (! empty($integrations[$identifier])) {
                $adapter->import($integrations[$identifier], $fieldModels);
            }
        }

        return $this->getErrors();
    }

    public function getErrors(): array
    {
        $errors = [];

        foreach ($this->adapters as $adapter) {
            $errors = array_merge($errors, $adapter->getErrors());
        }

        return array_unique($errors);
    }

    /**
     * Convenience helper for tests or external code.
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }
}
