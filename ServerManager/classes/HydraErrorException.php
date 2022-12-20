<?php

namespace ServerManager;

use Exception;
use Throwable;

class HydraErrorException extends Exception
{
    public string $context;
    public string $title;
    public string $description;

    public function __construct(array $params = [], ?Throwable $previous = null)
    {
        $this->context = $params['@context'] ?? '';
        $this->title = $params['hydra:title'] ?? '';
        $this->description = $params['hydra:description'] ?? '';
        parent::__construct(implode(', ', array_filter(get_object_vars($this))), 0, $previous);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}
