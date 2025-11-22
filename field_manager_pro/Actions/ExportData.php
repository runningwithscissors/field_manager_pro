<?php

namespace YourCompany\FieldManagerPro\Actions;

use ExpressionEngine\Service\Addon\Action;
use YourCompany\FieldManagerPro\Services\FieldExporter;

/**
 * Action for asynchronous export downloads.
 */
class ExportData extends Action
{
    protected $skip_authentication = false;

    public function handle()
    {
        if (! ee('Permission')->can('edit_channel_fields')) {
            show_error(lang('unauthorized_access'), 403);
        }

        ee()->output->send_ajax_response(
            (new FieldExporter())->exportComplete()
        );
    }
}
