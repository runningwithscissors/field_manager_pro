<?php

namespace YourCompany\FieldManagerPro\Commands;

use ExpressionEngine\Cli\Cli;

class CommandSeedFields extends Cli
{
    public $name = 'SeedFields';
    public $signature = 'field_manager_pro:fmp:seed';
    public $description = 'Inject test relationship/fluid/conditional fields for adapter design';
    public $summary = 'Inject test relationship/fluid/conditional fields for adapter design';
    public $usage = 'php eecli.php field_manager_pro:fmp:seed';
    public $commandOptions = [];

    public function handle()
    {
        $siteId = 1;

        // Clean slate so the command is repeatable.
        $existing = ee('Model')->get('ChannelField')
            ->filter('field_name', 'IN', ['fmp_test_rel', 'fmp_test_fluid', 'fmp_test_cond'])
            ->all();
        foreach ($existing as $f) {
            $this->info("Removing existing {$f->field_name} (id {$f->field_id})");
            $f->delete();
        }

        $base = [
            'field_list_items' => '',
            'field_order' => 50,
            'field_instructions' => '',
            'field_required' => 'n',
            'field_search' => 'n',
            'field_fmt' => 'none',
            'field_show_fmt' => 'n',
            'field_maxl' => 0,
            'field_ta_rows' => 0,
        ];

        // 1) Relationship field: 2 channels, author, status, categories, plus non-referential keys.
        $relSettings = [
            'channels' => ['2', '4'],          // blog, treatments
            'authors' => ['1'],                // me@jontoney.com
            'statuses' => ['open'],            // status NAME in EE7
            'categories' => ['1', '3'],        // Agency, Marketing (cat_ids in group 1)
            'limit' => '50',
            'expired' => 0,
            'future' => 0,
            'order_field' => 'title',
            'order_dir' => 'asc',
            'allow_multiple' => 1,
            'display_entry_id' => 0,
            'display_status' => 0,
            'deferred_loading' => 0,
            'rel_min' => 0,
            'rel_max' => '',
        ];
        $this->createField('fmp_test_rel', 'FMP Test Relationship', 'relationship', $siteId, $base, $relSettings);

        // 2) Fluid field: allow two existing scalar children.
        $fluidSettings = [
            'field_channel_fields' => [4, 12], // blog_excerpt, test_text
        ];
        $this->createField('fmp_test_fluid', 'FMP Test Fluid', 'fluid_field', $siteId, $base, $fluidSettings);

        // 3) Conditional textarea: condition references field_id 6 (content_type) — exercises condition_field_id remap.
        $condSettings = [
            'field_is_conditional' => 'y',
            'condition_set' => ['1' => ['match' => 'all']],
            'condition' => [
                '1' => [
                    '1' => [
                        'condition_field_id' => '6',
                        'evaluation_rule' => 'matches',
                        'value' => 'blog',
                    ],
                ],
            ],
        ];
        $this->createField('fmp_test_cond', 'FMP Test Conditional', 'textarea', $siteId, $base, $condSettings);

        $this->info('Done. Run field_manager_pro:fmp:inspect to view stored shapes.');
    }

    protected function createField($name, $label, $type, $siteId, array $base, array $settings)
    {
        try {
            $field = ee('Model')->make('ChannelField');
            $field->site_id = $siteId;
            $field->field_name = $name;
            $field->field_label = $label;
            $field->field_type = $type;
            foreach ($base as $k => $v) {
                $field->setProperty($k, $v);
            }
            $field->setProperty('field_settings', $settings);

            $validation = $field->validate();
            if (! $validation->isValid()) {
                foreach ($validation->getAllErrors() as $field_errs) {
                    foreach ((array) $field_errs as $err) {
                        $this->error("  {$name}: {$err}");
                    }
                }
                return;
            }

            $field->save();
            $this->info("Created {$name} ({$type}) id {$field->field_id}");
        } catch (\Throwable $e) {
            $this->error("Failed to create {$name}: " . $e->getMessage());
        }
    }
}
