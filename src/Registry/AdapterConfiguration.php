<?php

declare(strict_types=1);

namespace EdgeBinder\Registry;

use Psr\Container\ContainerInterface;

/**
 * Configuration object for adapter factories.
 *
 * This class provides a type-safe, immutable configuration object that replaces
 * the array-based configuration previously used by AdapterFactoryInterface.
 * It contains the three main sections needed for adapter creation:
 * - Instance-specific configuration
 * - Global EdgeBinder configuration
 * - PSR-11 container for dependency injection
 *
 * Example usage:
 * ```php
 * $config = new AdapterConfiguration(
 *     instance: [
 *         'adapter' => 'redis',
 *         'redis_client' => 'redis.client.cache',
 *         'ttl' => 3600,
 *         'prefix' => 'edgebinder:',
 *     ],
 *     global: [
 *         'default_metadata_validation' => true,
 *         'entity_extraction_strategy' => 'reflection',
 *     ],
 *     container: $psrContainer
 * );
 *
 * $adapter = $factory->createAdapter($config);
 * ```
 */
final class AdapterConfiguration
{
    /**
     * Create adapter configuration.
     *
     * @param array<string, mixed> $instance  Instance-specific configuration including adapter type and connection details
     * @param array<string, mixed> $global    Global EdgeBinder configuration for context
     * @param ContainerInterface   $container PSR-11 container for dependency injection
     */
    public function __construct(
        private readonly array $instance,
        private readonly array $global,
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Get instance-specific configuration.
     *
     * This contains adapter-specific settings including the adapter type,
     * service names, connection details, and other adapter-specific options.
     *
     * Example structure:
     * ```php
     * [
     *     'adapter' => 'redis',
     *     'redis_client' => 'redis.client.cache',
     *     'ttl' => 3600,
     *     'prefix' => 'edgebinder:',
     *     'host' => 'localhost',
     *     'port' => 6379,
     * ]
     * ```
     *
     * @return array<string, mixed> Instance configuration array
     */
    public function getInstanceConfig(): array
    {
        return $this->instance;
    }

    /**
     * Get global EdgeBinder configuration.
     *
     * This contains global EdgeBinder settings that provide context
     * for adapter behavior and system-wide configuration.
     *
     * Example structure:
     * ```php
     * [
     *     'default_metadata_validation' => true,
     *     'entity_extraction_strategy' => 'reflection',
     *     'max_binding_depth' => 10,
     * ]
     * ```
     *
     * @return array<string, mixed> Global configuration array
     */
    public function getGlobalSettings(): array
    {
        return $this->global;
    }

    /**
     * Get PSR-11 container for dependency injection.
     *
     * This container is used by adapter factories to retrieve services,
     * clients, and other dependencies needed for adapter creation.
     *
     * @return ContainerInterface PSR-11 container instance
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get a value from instance configuration with optional default.
     *
     * This is a convenience method for accessing instance configuration values
     * with type-safe defaults.
     *
     * @param string $key     Configuration key to retrieve
     * @param mixed  $default Default value if key is not found
     *
     * @return mixed Configuration value or default
     */
    public function getInstanceValue(string $key, mixed $default = null): mixed
    {
        return $this->instance[$key] ?? $default;
    }

    /**
     * Get a value from global settings with optional default.
     *
     * This is a convenience method for accessing global configuration values
     * with type-safe defaults.
     *
     * @param string $key     Configuration key to retrieve
     * @param mixed  $default Default value if key is not found
     *
     * @return mixed Configuration value or default
     */
    public function getGlobalValue(string $key, mixed $default = null): mixed
    {
        return $this->global[$key] ?? $default;
    }
}
