<?php

namespace YourCompany\FieldManagerPro\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

/**
 * Simple settings route allowing defaults to be configured.
 */
class Settings extends AbstractRoute
{
    protected $route_path = 'settings';

    public function process($id = false)
    {
        $this->authorize();
        $settings = ee()->config->item('field_manager_pro') ?? [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = [
                'default_scope' => ee()->input->post('default_scope') ?: 'complete',
                'conflict_strategy' => ee()->input->post('conflict_strategy') ?: 'prompt',
                'backup_before_import' => (bool) ee()->input->post('backup_before_import'),
            ];

            ee()->config->set_item('field_manager_pro', $settings);
            ee('CP/Alert')->makeInline('field-manager-pro-settings')
                ->asSuccess()
                ->withTitle(lang('field_manager_pro_settings_saved'))
                ->defer();
        }

        $vars = [
            'action_url' => ee('CP/URL')->make('addons/settings/field_manager_pro/settings'),
            'settings' => $settings,
        ];

        $this->setHeading(lang('field_manager_pro_settings_heading'));
        $this->setBody('settings', $vars);

        return $this;
    }

    protected function authorize(): void
    {
        if (! ee('Permission')->can('edit_channel_fields')) {
            show_error(lang('unauthorized_access'), 403);
        }
    }
}
