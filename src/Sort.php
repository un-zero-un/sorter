<?php

namespace UnZeroUn\Sorter;

final class Sort
{
    private array $fields = [];

    public function add(string $field, string $direction): void
    {
        $this->fields[$field] = $direction;
    }

    public function getFields(): array
    {
        return array_keys($this->fields);
    }

    public function getDirection(string $field): mixed
    {
        return $this->fields[$field];
    }

    public function has(string $field): bool
    {
        return isset($this->fields[$field]);
    }
}
