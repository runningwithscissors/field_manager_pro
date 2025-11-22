<?php

namespace YourCompany\FieldManagerPro\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;
use YourCompany\FieldManagerPro\Services\FieldImporter;
use YourCompany\FieldManagerPro\Services\ValidationService;

/**
 * Control panel route that handles import configuration and preview.
 */
class Import extends AbstractRoute
{
    protected FieldImporter $importer;

    protected ValidationService $validator;

    protected $route_path = 'import';

    public function __construct()
    {
        $this->importer = new FieldImporter();
        $this->validator = new ValidationService();
    }

    public function process($id = false)
    {
        $this->authorize();
        $preview = [];
        $errors = [];
        $result = null;
        $selectedStrategy = ee()->input->post('strategy') ?: (ee()->config->item('field_manager_pro')['conflict_strategy'] ?? 'prompt');
        $action = ee()->input->post('action');

        if (! empty($_FILES['import_file']['tmp_name'])) {
            $payload = file_get_contents($_FILES['import_file']['tmp_name']);
            if ($action === 'import') {
                $result = $this->importer->import($payload, $selectedStrategy);
                $errors = $result['errors'] ?? [];
            } else {
                $preview = $this->importer->preview($payload);
                $errors = $this->importer->getLastErrors();
            }
        }

        $vars = [
            'import_action' => ee('CP/URL')->make('addons/settings/field_manager_pro/import'),
            'preview' => $preview,
            'errors' => $errors,
            'result' => $result,
            'strategies' => [
                'prompt' => lang('field_manager_pro_conflict_prompt'),
                'skip' => lang('field_manager_pro_conflict_skip'),
                'overwrite' => lang('field_manager_pro_conflict_overwrite'),
                'rename' => lang('field_manager_pro_conflict_rename'),
            ],
            'selected_strategy' => $selectedStrategy,
        ];

        $this->setHeading(lang('field_manager_pro_import_heading'));
        $this->setBody('import', $vars);

        return $this;
    }

    protected function authorize(): void
    {
        if (! ee('Permission')->can('edit_channel_fields')) {
            show_error(lang('unauthorized_access'), 403);
        }
    }
}
