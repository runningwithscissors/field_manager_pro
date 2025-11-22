<?php

namespace YourCompany\FieldManagerPro;

use ExpressionEngine\Service\Addon\Module;
use YourCompany\FieldManagerPro\Services\FieldExporter;

/**
 * Module entry point for template tags.
 */
class Field_manager_pro extends Module
{
    /**
     * Addon short name for Module service.
     *
     * @var string
     */
    protected $addon_name = 'field_manager_pro';

    public function __construct()
    {
        $this->ensureProviderRegistered();
    }

    /**
     * Simple tag example to trigger an export via template.
     */
    public function export()
    {
        $exporter = new FieldExporter();
        $payload = $exporter->exportComplete();

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    protected function ensureProviderRegistered(): void
    {
        if (function_exists('ee') && ee('App') && ! ee('App')->has($this->addon_name)) {
            ee('App')->addProvider(PATH_THIRD . $this->addon_name);
        }
    }
}

class_alias(__NAMESPACE__ . '\Field_manager_pro', 'Field_manager_pro');
