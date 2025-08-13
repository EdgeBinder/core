<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Session;

use EdgeBinder\Contracts\EntityInterface;

/**
 * Test entity implementation for session tests.
 */
class TestEntity implements EntityInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $type
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
