<?php

namespace YourCompany\FieldManagerPro\Services\Integrations;

abstract class AbstractFieldtypeIntegration implements FieldtypeIntegrationInterface
{
    protected array $errors = [];

    public function getErrors(): array
    {
        return array_unique($this->errors);
    }

    protected function addError(string $message): void
    {
        if ($message !== '') {
            $this->errors[] = $message;
        }
    }
}
