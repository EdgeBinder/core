<?php

declare(strict_types=1);

namespace EdgeBinder\Persistence\InMemory;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Registry\AdapterFactoryInterface;

/**
 * Factory for creating InMemory adapter instances.
 *
 * This factory implements the EdgeBinder extensible adapter system,
 * allowing InMemory adapters to be created consistently across all PHP frameworks.
 * The InMemory adapter is primarily intended for testing, development, and
 * small-scale applications where persistence across requests is not required.
 *
 * Example usage:
 * ```php
 * // Register the factory
 * AdapterRegistry::register(new InMemoryAdapterFactory());
 *
 * // Create adapter through EdgeBinder
 * $config = [
 *     'adapter' => 'inmemory',
 * ];
 *
 * $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
 * ```
 *
 * The InMemory adapter requires no external dependencies or configuration,
 * making it ideal for:
 * - Unit testing and integration testing
 * - Development environments
 * - Prototyping and proof-of-concept applications
 * - Small applications with minimal persistence needs
 */
final class InMemoryAdapterFactory implements AdapterFactoryInterface
{
    /**
     * Create InMemory adapter instance.
     *
     * The InMemory adapter requires no configuration as it stores all data
     * in PHP memory arrays. All configuration parameters are ignored.
     *
     * Example usage:
     * ```php
     * $config = new AdapterConfiguration(
     *     instance: ['adapter' => 'inmemory'],
     *     global: [], // Ignored by InMemory adapter
     *     container: $psrContainer // Not used by InMemory adapter
     * );
     *
     * $adapter = $factory->createAdapter($config);
     * ```
     *
     * @param AdapterConfiguration $config Configuration object (ignored)
     *
     * @return PersistenceAdapterInterface Configured InMemory adapter instance
     */
    public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface
    {
        // InMemory adapter requires no configuration or dependencies
        return new InMemoryAdapter();
    }

    /**
     * Get the adapter type this factory handles.
     *
     * @return string The adapter type identifier
     */
    public function getAdapterType(): string
    {
        return 'inmemory';
    }
}
