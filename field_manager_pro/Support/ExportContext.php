<?php

namespace YourCompany\FieldManagerPro\Support;

/**
 * Export-side facade: translates source-install ids into portable natural keys.
 * Wraps KeyResolver and collects non-fatal export warnings (e.g. a referenced
 * member or field that no longer resolves to a key).
 */
class ExportContext
{
    protected KeyResolver $resolver;

    protected array $warnings = [];

    public function __construct(KeyResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function channelKey(int $id): ?string
    {
        return $this->resolver->channelKey($id);
    }

    public function fieldKey(int $id): ?string
    {
        return $this->resolver->fieldKey($id);
    }

    public function categoryKey(int $id): ?string
    {
        return $this->resolver->categoryKey($id);
    }

    public function memberKey(int $id): ?string
    {
        return $this->resolver->memberKey($id);
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
