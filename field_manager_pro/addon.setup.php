<?php

return [
    'author' => 'Your Company',
    'author_url' => 'https://example.com',
    'name' => 'Field Manager Pro',
    'description' => 'Import and export channels, field groups, and custom fields with full settings preservation',
    'version' => '1.0.0',
    'namespace' => 'YourCompany\\FieldManagerPro',
    'settings_exist' => true,
    'commands' => [
        // TODO: remove these dev-only commands before shipping.
        'field_manager_pro:fmp:inspect' => YourCompany\FieldManagerPro\Commands\CommandInspectFields::class,
        'field_manager_pro:fmp:seed' => YourCompany\FieldManagerPro\Commands\CommandSeedFields::class,
        'field_manager_pro:fmp:diag' => YourCompany\FieldManagerPro\Commands\CommandDiag::class,
    ],
];
