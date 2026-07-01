<?php

namespace YourCompany\FieldManagerPro\FieldTypes;

use ExpressionEngine\Model\Channel\ChannelField;
use YourCompany\FieldManagerPro\Support\ExportContext;
use YourCompany\FieldManagerPro\Support\ImportContext;

/**
 * Per-field-type adapter contract. Each adapter owns the translation of one (or
 * more) EE field types between live EE settings (id-bearing) and a portable,
 * natural-keyed representation that survives moving between installs.
 */
interface FieldTypeAdapter
{
    /**
     * The EE field_type(s) this adapter owns.
     *
     * @return string|array
     */
    public function handles(): string|array;

    /**
     * Convert live field settings to a portable, natural-keyed array.
     */
    public function export(ChannelField $field, array $settings, ExportContext $ctx): array;

    /**
     * Convert a portable array back into EE field settings, resolving natural
     * keys to target-install ids.
     */
    public function import(array $portable, ImportContext $ctx): array;

    /**
     * Natural keys this field needs created first, e.g. "channel:blog",
     * "field:hero_image". Consumed by DependencySorter (field:* only).
     */
    public function dependencies(array $portable): array;

    /**
     * Phase-B hook for references that can only be resolved once every field in
     * the run exists. No-op for relationship/fluid this phase.
     */
    public function resolveDeferred(ChannelField $field, array $portable, ImportContext $ctx): void;
}
