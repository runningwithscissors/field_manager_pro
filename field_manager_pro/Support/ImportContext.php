<?php

namespace YourCompany\FieldManagerPro\Support;

/**
 * Import-side facade: translates portable natural keys back into target-install
 * ids. Every lookup consults the IdMap first (entities created earlier in this
 * run) and falls back to a live KeyResolver lookup (entities already present on
 * the target). Accumulates non-fatal warnings for unresolved references.
 */
class ImportContext
{
    protected KeyResolver $resolver;

    protected IdMap $idMap;

    protected array $warnings = [];

    protected string $currentField = '';

    public function __construct(KeyResolver $resolver, IdMap $idMap)
    {
        $this->resolver = $resolver;
        $this->idMap = $idMap;
    }

    public function idMap(): IdMap
    {
        return $this->idMap;
    }

    public function setCurrentField(string $fieldName): void
    {
        $this->currentField = $fieldName;
    }

    public function currentField(): string
    {
        return $this->currentField;
    }

    public function channelId(string $name): ?int
    {
        return $this->idMap->get("channel:{$name}") ?? $this->resolver->channelId($name);
    }

    public function fieldId(string $name): ?int
    {
        return $this->idMap->get("field:{$name}") ?? $this->resolver->fieldId($name);
    }

    public function categoryId(string $key): ?int
    {
        return $this->idMap->get("category:{$key}") ?? $this->resolver->categoryId($key);
    }

    public function memberId(string $identifier): ?int
    {
        return $this->resolver->memberId($identifier);
    }

    public function statusName(string $name): string
    {
        if (! $this->resolver->statusExists($name)) {
            $this->addWarning(sprintf(lang('field_manager_pro_unresolved_status'), $name));
        }

        return $name;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function getWarnings(): array
    {
        return array_values(array_unique($this->warnings));
    }
}
