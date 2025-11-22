# Field Manager Pro

Field Manager Pro is an ExpressionEngine 7+ addon that exports and imports channels, field groups, and individual custom fields while retaining their settings and relationships.

## Installation

1. Copy the `field_manager_pro` folder into `system/user/addons`.
2. Enable the addon from **Developer > Add-Ons** inside the ExpressionEngine Control Panel.
3. Optionally publish the Control Panel navigation links by pinning the addon sidebar.

## Usage

### Export

1. Navigate to **Add-Ons > Field Manager Pro > Export**.
2. Select the channels, field groups, or individual fields you want to include.
3. Apply optional site or field-type filters.
4. Click **Download export file** to receive a JSON package that contains:
   - Metadata (EE version, site id, export timestamp)
   - Channels with settings, statuses, categories, and fields
   - Field groups with their relationships
   - Individual fields with serialized settings and field-type specific configuration

### Import

1. Navigate to **Add-Ons > Field Manager Pro > Import**.
2. Upload a JSON export from Field Manager Pro.
3. Review the preview counts and validation messages.
4. Resolve any conflicts (skip, overwrite, rename) based on the settings page.
5. Run the import to create/update fields, groups, and channels.

### Settings

Configure defaults for export scope, conflict handling, and automatic backups from **Add-Ons > Field Manager Pro > Settings**.

## File Format

Exports follow the structure below:

```json
{
  "export_version": "1.0.0",
  "export_date": "2024-11-19T12:00:00Z",
  "ee_version": "7.x.x",
  "site_id": 1,
  "data_type": "complete",
  "channels": [],
  "field_groups": [],
  "fields": []
}
```

Each field entry contains every property from the ExpressionEngine Channel Field model, including base64 encoded `field_settings`. Channels retain associations to field groups, statuses, categories, and individual field assignments.

## Troubleshooting

- **Invalid JSON**: Confirm the uploaded file is the unmodified export file.
- **Field conflicts**: Adjust conflict handling in the Settings page or rename the incoming fields.
- **Permissions**: Ensure the user has channel field management privileges.
- **Relationship references**: Import dependent channels/fields in the same package to preserve relationships.

## Programmatic API

- `YourCompany\FieldManagerPro\Services\FieldExporter` – call `exportComplete()` to obtain the JSON payload server-side.
- `YourCompany\FieldManagerPro\Services\FieldImporter` – call `import($json, $strategy)` to process imports in CLI tooling.

## Suggested Enhancements

- CLI commands to schedule periodic exports.
- Template tags that expose export data inside templates for auditing.
- Extension hooks before/after imports to notify other services.

Screenshots and further examples can be added to this README once the Control Panel styling is finalized.
