<?php

namespace YourCompany\FieldManagerPro\ControlPanel;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractSidebar;

/**
 * Sidebar builder for Field Manager Pro CP screens.
 */
class Sidebar extends AbstractSidebar
{
    public function process()
    {
        $sidebar = $this->getSidebar();

        $section = $sidebar->addHeader(lang('field_manager_pro_module_name'));
        $list = $section->addBasicList();

        $list->addItem(
            lang('field_manager_pro_export_nav'),
            ee('CP/URL')->make('addons/settings/field_manager_pro/export')
        );

        $list->addItem(
            lang('field_manager_pro_import_nav'),
            ee('CP/URL')->make('addons/settings/field_manager_pro/import')
        );

        $list->addItem(
            lang('settings'),
            ee('CP/URL')->make('addons/settings/field_manager_pro/settings')
        );
    }
}
