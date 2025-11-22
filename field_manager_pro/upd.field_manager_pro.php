<?php

namespace YourCompany\FieldManagerPro;

use ExpressionEngine\Service\Addon\Installer;

/**
 * Installer handles module install/update/uninstall lifecycle.
 */
class Field_manager_pro_upd extends Installer
{
    /**
     * Addon short name for parent services.
     *
     * @var string
     */
    protected $addon_name = 'field_manager_pro';

    /**
     * Ensure Installer uses the correct short name when namespaced.
     *
     * @var string
     */
    public $shortname = 'field_manager_pro';

    /**
     * Expose CP backend.
     *
     * @var string
     */
    public $has_cp_backend = 'y';

    /**
     * Publish fields not provided by module.
     *
     * @var string
     */
    public $has_publish_fields = 'n';

    public function __construct($settings = [])
    {
        $this->settings = $settings;
        $this->registerProvider();
        $this->addon = ee('Addon')->get($this->shortname);

        if (! $this->addon) {
            $setup = require PATH_THIRD . $this->addon_name . '/addon.setup.php';
            $this->version = $setup['version'] ?? '1.0.0';

            return;
        }

        $this->version = $this->addon->getVersion();
    }

    /**
     * Run install steps. Allows future schema prep or default settings.
     */
    public function install()
    {
        parent::install();
        $this->normalizeModuleRecord($this->addon->getModuleClass(), $this->shortname);

        return true;
    }

    /**
     * Cleanup when uninstalling.
     */
    public function uninstall()
    {
        $this->normalizeModuleRecord($this->shortname, $this->addon->getModuleClass());
        parent::uninstall();

        return true;
    }

    /**
     * Handle updates between versions.
     */
    public function update($current = '')
    {
        return parent::update($current);
    }

    protected function registerProvider(): void
    {
        if (! function_exists('ee')) {
            return;
        }

        $app = ee('App');
        if ($app && ! $app->has($this->addon_name)) {
            $app->addProvider(PATH_THIRD . $this->addon_name);
        }
    }

    protected function normalizeModuleRecord(string $from, string $to): void
    {
        ee()->db->where('module_name', $from)->update('modules', ['module_name' => $to]);
    }
}

class_alias(__NAMESPACE__ . '\Field_manager_pro_upd', 'Field_manager_pro_upd');
