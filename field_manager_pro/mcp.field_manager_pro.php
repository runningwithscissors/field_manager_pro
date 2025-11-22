<?php

namespace YourCompany\FieldManagerPro;

use ExpressionEngine\Service\Addon\Mcp;
use YourCompany\FieldManagerPro\Services\ValidationService;

/**
 * Control panel front controller delegates to route handlers.
 */
class Field_manager_pro_mcp extends Mcp
{
    /**
     * Addon short name for CP backend.
     *
     * @var string
     */
    protected $addon_name = 'field_manager_pro';

    /**
     * Shared validation helper.
     */
    protected ValidationService $validator;

    public function __construct()
    {
        $this->ensureProviderRegistered();
        $this->validator = new ValidationService();
    }

    /**
     * Default method redirects to Import route controller output.
     */
    public function index()
    {
        $url = ee('CP/URL')->make('addons/settings/' . $this->addon_name . '/import');
        ee()->functions->redirect((string) $url);
    }

    protected function ensureProviderRegistered(): void
    {
        if (function_exists('ee') && ee('App') && ! ee('App')->has($this->addon_name)) {
            ee('App')->addProvider(PATH_THIRD . $this->addon_name);
        }
    }
}

class_alias(__NAMESPACE__ . '\Field_manager_pro_mcp', 'Field_manager_pro_mcp');
