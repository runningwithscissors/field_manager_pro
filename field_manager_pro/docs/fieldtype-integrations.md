# Fieldtype Integration API

Field Manager Pro exposes an integration layer that lets third–party fieldtypes
export/import custom metadata without modifying the add-on core.

## Concepts

- **FieldtypeIntegrationInterface** – contract each adapter must implement.
  - `identifier()` – unique key used in exported JSON (e.g. `bloqs`).
  - `supportedFieldtypes()` – short names handled by the adapter.
  - `export($fields, $channels)` – return integration data to embed in the export payload.
  - `import($payload, $fieldModels)` – rebuild data when an import runs.
  - `getErrors()` – warning/error messages to surface in the UI.
- **FieldtypeIntegrationManager** – orchestrates adapters during export/import.
  - Registers Field Manager Pro’s built-in adapters and calls external adapters
    via the `field_manager_pro_register_integrations` extension hook.

## Registering an Adapter

1. Create a class that implements
   `YourCompany\FieldManagerPro\Services\Integrations\FieldtypeIntegrationInterface`.
2. Register it via the extension hook:

```php
public function field_manager_pro_register_integrations($manager)
{
    $manager->registerAdapter(new \Acme\Addons\MyField\MyFieldIntegration());
}
```

3. Ensure your add-on’s extension is enabled before triggering an export/import.

## Export Payload Shape

Each adapter’s `identifier()` value is used as the key inside the exported JSON:

```json
{
  "integrations": {
    "bloqs": { ... },
    "mason": { ... }
  }
}
```

Adapters are responsible for namespacing their payload structure. Keep a schema
version in the payload if you expect breaking changes in the future.

## Import Lifecycle

During import Field Manager Pro:

1. Restores channels, field groups, and fields.
2. Loads ChannelField models keyed by field name.
3. Invokes each integration adapter’s `import()` method (only when payload data
   is present).
4. Aggregates adapter errors so they display alongside the import summary.

Use the provided `ChannelField` models (passed as `$fieldModels`) to access
field IDs, settings, etc. when recreating custom tables or relationships.

## Built-in Adapters

Field Manager Pro ships with adapters for:

- **Bloqs** – block groups/definitions, atoms, component trees, and field usage.
- **Mason** – custom block types defined in `exp_mason_block_types`.

These adapters serve as working examples for third-party developers.
