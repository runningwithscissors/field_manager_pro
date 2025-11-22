<?php

namespace YourCompany\FieldManagerPro\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;
use YourCompany\FieldManagerPro\Services\FieldExporter;
use YourCompany\FieldManagerPro\Services\ValidationService;

/**
 * Control panel route that lets users configure what to export.
 */
class Export extends AbstractRoute
{
    protected FieldExporter $exporter;

    protected ValidationService $validator;

    protected $route_path = 'export';

    public function __construct()
    {
        $this->exporter = new FieldExporter();
        $this->validator = new ValidationService();
    }

    public function process($id = false)
    {
        $this->authorize();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $filters = $this->requestFilters();
            $scope = [
                'data_type' => 'custom',
                'site_id' => $filters['site_id'],
                'channels' => (array) ee()->input->post('channels') ?: [],
                'field_groups' => (array) ee()->input->post('field_groups') ?: [],
                'fields' => (array) ee()->input->post('fields') ?: [],
                'field_type' => $filters['field_type'] ?? null,
            ];

            $this->downloadExport($scope);
        }

        $filters = $this->requestFilters();

        $vars = [
            'channels' => $this->exporter->getChannelSummaries(),
            'field_groups' => $this->exporter->getFieldGroupSummaries(),
            'fields' => $this->exporter->getFieldSummaries(),
            'filters' => $filters,
            'export_action' => ee('CP/URL')->make('addons/settings/field_manager_pro/export'),
        ];

        $this->setHeading(lang('field_manager_pro_export_heading'));
        $this->setBody('export', $vars);

        return $this;
    }

    /**
     * Process filters submitted from the export form.
     */
    protected function requestFilters(): array
    {
        $fieldtypes = [];
        foreach (ee('Model')->get('Fieldtype')->all() as $type) {
            $fieldtypes[$type->name] = $type->name;
        }

        $siteOptions = [];
        foreach (ee('Model')->get('Site')->all() as $site) {
            $siteOptions[$site->site_id] = $site->site_label;
        }

        $filters = [
            'site_id' => (int) ee()->input->get_post('site_id') ?: ee()->config->item('site_id'),
            'field_type' => ee()->input->get_post('field_type'),
            'field_type_options' => $fieldtypes,
            'site_options' => $siteOptions,
        ];

        $this->validator->validateExportFilters($filters);

        return $filters;
    }

    protected function downloadExport(array $scope)
    {
        $payload = $this->exporter->exportComplete($scope);
        $filename = 'field-manager-pro-' . date('YmdHis') . '.json';

        ee()->output->set_header('Content-Type: application/json');
        ee()->output->set_header('Content-Disposition: attachment; filename="' . $filename . '"');
        ee()->output->set_output(json_encode($payload, JSON_PRETTY_PRINT));
        ee()->output->_display();
        exit;
    }

    protected function authorize(): void
    {
        if (! ee('Permission')->can('edit_channel_fields')) {
            show_error(lang('unauthorized_access'), 403);
        }
    }
}
