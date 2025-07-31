<?php

declare(strict_types=1);

namespace EdgeBinder\Registry;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Exception\AdapterException;

/**
 * Static registry for managing third-party adapter factories.
 *
 * This registry provides a framework-agnostic way to register and
 * create adapter instances. It uses a static approach to ensure
 * adapters work consistently across all PHP frameworks.
 *
 * Example usage:
 * ```php
 * // Register an adapter factory
 * AdapterRegistry::register(new JanusAdapterFactory());
 *
 * // Check if adapter is available
 * if (AdapterRegistry::hasAdapter('janus')) {
 *     // Create adapter instance
 *     $adapter = AdapterRegistry::create('janus', $config);
 * }
 *
 * // Get all registered types
 * $types = AdapterRegistry::getRegisteredTypes();
 * ```
 *
 * Framework integration examples:
 *
 * Laminas/Mezzio:
 * ```php
 * // In Module.php or application bootstrap
 * AdapterRegistry::register(new JanusAdapterFactory());
 * ```
 *
 * Symfony:
 * ```php
 * // In bundle boot method or compiler pass
 * AdapterRegistry::register(new JanusAdapterFactory());
 * ```
 *
 * Laravel:
 * ```php
 * // In service provider boot method
 * AdapterRegistry::register(new JanusAdapterFactory());
 * ```
 */
final class AdapterRegistry
{
    /** @var array<string, AdapterFactoryInterface> */
    private static array $factories = [];

    /**
     * Register an adapter factory.
     *
     * @param AdapterFactoryInterface $factory The adapter factory to register
     *
     * @throws AdapterException If adapter type is already registered
     */
    public static function register(AdapterFactoryInterface $factory): void
    {
        $type = $factory->getAdapterType();

        if (isset(self::$factories[$type])) {
            throw AdapterException::alreadyRegistered($type);
        }

        self::$factories[$type] = $factory;
    }
    
    /**
     * Create adapter instance.
     *
     * @param string               $type   The adapter type to create
     * @param array<string, mixed> $config Configuration for the adapter
     *
     * @return PersistenceAdapterInterface The created adapter instance
     *
     * @throws AdapterException If adapter type is not registered or creation fails
     */
    public static function create(string $type, array $config): PersistenceAdapterInterface
    {
        if (!isset(self::$factories[$type])) {
            throw AdapterException::factoryNotFound($type, array_keys(self::$factories));
        }

        try {
            return self::$factories[$type]->createAdapter($config);
        } catch (\Throwable $e) {
            // Wrap all exceptions in AdapterException for consistent error handling
            if ($e instanceof AdapterException) {
                throw $e;
            }

            throw AdapterException::creationFailed($type, $e->getMessage(), $e);
        }
    }
    
    /**
     * Check if adapter type is registered.
     *
     * @param string $type The adapter type to check
     *
     * @return bool True if the adapter type is registered
     */
    public static function hasAdapter(string $type): bool
    {
        return isset(self::$factories[$type]);
    }

    /**
     * Get all registered adapter types.
     *
     * @return string[] Array of registered adapter type identifiers
     */
    public static function getRegisteredTypes(): array
    {
        return array_keys(self::$factories);
    }
    
    /**
     * Unregister an adapter type.
     *
     * This method is primarily for testing purposes to allow
     * clean test isolation.
     *
     * @param string $type The adapter type to unregister
     *
     * @return bool True if the adapter was unregistered, false if it wasn't registered
     */
    public static function unregister(string $type): bool
    {
        if (isset(self::$factories[$type])) {
            unset(self::$factories[$type]);

            return true;
        }

        return false;
    }

    /**
     * Clear all registered adapters.
     *
     * This method is primarily for testing purposes to ensure
     * clean test isolation between test cases.
     */
    public static function clear(): void
    {
        self::$factories = [];
    }

    /**
     * Get the factory instance for a specific adapter type.
     *
     * This method is primarily for testing and debugging purposes.
     *
     * @param string $type The adapter type
     *
     * @return AdapterFactoryInterface|null The factory instance or null if not registered
     */
    public static function getFactory(string $type): ?AdapterFactoryInterface
    {
        return self::$factories[$type] ?? null;
    }
}
