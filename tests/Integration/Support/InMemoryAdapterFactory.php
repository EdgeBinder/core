<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration\Support;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Registry\AdapterFactoryInterface;

/**
 * InMemory adapter factory for integration testing.
 *
 * This factory creates InMemory adapters that can be used to test the
 * adapter registry integration with real storage functionality.
 */
final class InMemoryAdapterFactory implements AdapterFactoryInterface
{
    public function __construct(
        private readonly string $adapterType = 'inmemory'
    ) {
    }

    public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface
    {
        // The InMemory adapter doesn't need any configuration
        return new InMemoryAdapter();
    }

    public function getAdapterType(): string
    {
        return $this->adapterType;
    }
}
