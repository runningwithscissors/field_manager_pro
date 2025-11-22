<?php

namespace YourCompany\FieldManagerPro\Services;

use ExpressionEngine\Library\Data\Collection;

/**
 * Shared validation helpers.
 */
class ValidationService
{
    protected array $errors = [];

    public function validateJsonStructure(string $payload): array
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = lang('field_manager_pro_invalid_json');

            return [];
        }

        $required = ['export_version', 'data_type'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $data)) {
                $this->errors[] = sprintf(lang('field_manager_pro_missing_key'), $key);
            }
        }

        return $data;
    }

    public function validateFieldName(string $name, int $siteId): bool
    {
        if (empty($name)) {
            $this->errors[] = lang('field_manager_pro_missing_field_name');

            return false;
        }

        if (! preg_match('/^[a-zA-Z0-9_]{1,32}$/', $name)) {
            $this->errors[] = sprintf(lang('field_manager_pro_invalid_field_name'), $name);

            return false;
        }

        ee()->load->library('form_validation');
        if (method_exists(ee()->form_validation, 'validateNameIsNotReserved')) {
            $reservedCheck = ee()->form_validation->validateNameIsNotReserved($name);
            if ($reservedCheck !== true) {
                $this->errors[] = sprintf(lang('field_manager_pro_reserved_field_name'), $name);

                return false;
            }
        }

        $existing = ee('Model')->get('ChannelField')
            ->filter('site_id', $siteId)
            ->filter('field_name', $name)
            ->first();

        if ($existing) {
            $this->errors[] = sprintf(lang('field_manager_pro_field_exists'), $name);

            return false;
        }

        return true;
    }

    public function validateExportFilters(array $filters): void
    {
        if (! empty($filters['field_type'])) {
            $validTypes = [];
            foreach (ee('Model')->get('Fieldtype')->all() as $fieldtype) {
                $validTypes[] = $fieldtype->name;
            }

            if (! in_array($filters['field_type'], $validTypes)) {
                $this->errors[] = sprintf(lang('field_manager_pro_invalid_field_type'), $filters['field_type']);
            }
        }
    }

    public function mergeErrors(Collection|array $errors): void
    {
        foreach ($errors as $error) {
            $this->errors[] = $error;
        }
    }

    public function getErrors(): array
    {
        return array_unique($this->errors);
    }

    public function reset(): void
    {
        $this->errors = [];
    }
}
