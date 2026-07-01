<?php

namespace YourCompany\FieldManagerPro\Commands;

use ExpressionEngine\Cli\Cli;

class CommandInspectFields extends Cli
{
    /**
     * name of command
     * @var string
     */
    public $name = 'InspectFields';

    /**
     * signature of command
     * @var string
     */
    public $signature = 'field_manager_pro:fmp:inspect';

    /**
     * Public description of command
     * @var string
     */
    public $description = 'Dump live field settings for adapter design';

    /**
     * Summary of command functionality
     * @var [type]
     */
    public $summary = 'Dump live field settings for adapter design';

    /**
     * How to use command
     * @var string
     */
    public $usage = 'php eecli.php field_manager_pro:fmp:inspect';

    /**
     * options available for use in command
     * @var array
     */
    public $commandOptions = [

    ];

    /**
     * Run the command
     * @return mixed
     */
    public function handle()
    {
        $types = ['relationship', 'fluid_field', 'fluid', 'grid', 'bloqs'];

        $fields = ee('Model')->get('ChannelField')
            ->filter('field_type', 'IN', $types)
            ->all();

        if ($fields->count() === 0) {
            $this->info('No relationship/fluid/grid/bloqs fields found on this install.');
            $this->info('All field types present:');
            $all = ee('Model')->get('ChannelField')->all();
            $seen = [];
            foreach ($all as $f) {
                $seen[$f->field_type] = ($seen[$f->field_type] ?? 0) + 1;
            }
            foreach ($seen as $type => $count) {
                $this->info("  - {$type}: {$count}");
            }
            return;
        }

        foreach ($fields as $field) {
            $this->info(str_repeat('=', 80));
            $this->info("field_id   : {$field->field_id}");
            $this->info("field_name : {$field->field_name}");
            $this->info("field_type : {$field->field_type}");

            $dump = function ($label, $value) {
                $this->info("--- {$label} ---");
                $this->info(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            };

            $dump("getProperty('field_settings')", $field->getProperty('field_settings'));
            $dump('getSettingsValues()', $field->getSettingsValues());
        }

        $this->info(str_repeat('=', 80));
    }
}
