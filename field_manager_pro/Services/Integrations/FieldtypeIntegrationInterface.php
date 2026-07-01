<?php

namespace YourCompany\FieldManagerPro\Services\Integrations;

interface FieldtypeIntegrationInterface
{
    /**
     * Unique key that will be used in the export payload.
     */
    public function identifier(): string;

    /**
     * List of EE fieldtype short names handled by this integration.
     */
    public function supportedFieldtypes(): array;

    /**
     * Return adapter specific data to be merged into the export payload.
     */
    public function export(array $fields, array $channels): array;

    /**
     * Restore adapter specific data during import.
     *
     * @param array $payload Data previously exported via export()
     * @param array $fieldModels map of field_name => ChannelField model
     */
    public function import(array $payload, array $fieldModels): void;

    /**
     * Return warning/error messages generated during export/import.
     */
    public function getErrors(): array;
}
