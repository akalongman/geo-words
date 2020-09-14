<?php

declare(strict_types=1);

namespace Longman\Crawler\Entities;

class Project
{
    private int $id;
    private string $name;
    private string $createdAt;

    public function __construct(int $id, string $name, string $createdAt)
    {
        $this->id = $id;
        $this->name = $name;
        $this->createdAt = $createdAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
