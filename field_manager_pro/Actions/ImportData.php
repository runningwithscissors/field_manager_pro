<?php

namespace YourCompany\FieldManagerPro\Actions;

use ExpressionEngine\Service\Addon\Action;
use YourCompany\FieldManagerPro\Services\FieldImporter;

/**
 * Action invoked for asynchronous imports via CP or CLI.
 */
class ImportData extends Action
{
    protected $skip_authentication = false;

    public function handle()
    {
        if (! ee('Permission')->can('edit_channel_fields')) {
            show_error(lang('unauthorized_access'), 403);
        }

        $payload = ee()->input->post('payload');
        $strategy = ee()->input->post('strategy') ?: 'prompt';

        $importer = new FieldImporter();
        $result = $importer->import($payload, $strategy);

        ee()->output->send_ajax_response($result);
    }
}
