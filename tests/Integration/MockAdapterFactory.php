<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Integration;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Registry\AdapterFactoryInterface;

/**
 * Mock adapter factory for integration testing.
 *
 * This factory creates InMemory adapters that can be used to test the
 * adapter registry integration with real storage functionality.
 */
final class MockAdapterFactory implements AdapterFactoryInterface
{
    public function __construct(
        private readonly string $adapterType = 'mock',
        private readonly ?PersistenceAdapterInterface $adapter = null
    ) {
    }

    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        // Return pre-configured adapter if provided, otherwise create a new InMemory adapter
        if (null !== $this->adapter) {
            return $this->adapter;
        }

        return new InMemoryAdapter();
    }

    public function getAdapterType(): string
    {
        return $this->adapterType;
    }
}
