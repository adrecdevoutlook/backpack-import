<?php

namespace Adrec\BackpackImport\Columns;

abstract class ImportColumn
{
    protected mixed $data;
    protected array $config;
    protected string $model;

    public function __construct(mixed $data, array $config, string $model)
    {
        $this->data = $data;
        $this->config = $config;
        $this->model = $model;
    }

    /**
     * Process and return the output value
     */
    abstract public function output(): mixed;

    /**
     * Get the display name for this column type
     */
    abstract public function getName(): string;

    /**
     * Get a config value by key
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get the model class
     */
    protected function getModel(): string
    {
        return $this->model;
    }
}
