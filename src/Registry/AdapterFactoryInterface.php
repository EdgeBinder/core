<?php

declare(strict_types=1);

namespace EdgeBinder\Registry;

use EdgeBinder\Contracts\PersistenceAdapterInterface;

/**
 * Interface for third-party adapter factories.
 *
 * This interface provides a framework-agnostic way for third-party
 * developers to create custom adapters that work across all PHP frameworks.
 *
 * Example implementation:
 * ```php
 * class JanusAdapterFactory implements AdapterFactoryInterface
 * {
 *     public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface
 *     {
 *         $container = $config->getContainer();
 *
 *         // Get configuration using convenience methods with defaults
 *         $janusClient = $container->get($config->getInstanceValue('janus_client', 'janus.client.default'));
 *
 *         // Build adapter configuration from instance config
 *         $adapterConfig = [
 *             'graph_name' => $config->getInstanceValue('graph_name', 'DefaultGraph'),
 *             'consistency_level' => $config->getInstanceValue('consistency_level', 'eventual'),
 *             'host' => $config->getInstanceValue('host', 'localhost'),
 *             'port' => $config->getInstanceValue('port', 8182),
 *         ];
 *
 *         return new JanusAdapter($janusClient, $adapterConfig);
 *     }
 *
 *     public function getAdapterType(): string
 *     {
 *         return 'janus';
 *     }
 * }
 * ```
 *
 * Registration across frameworks:
 * ```php
 * // Works identically in Laminas, Symfony, Laravel, Slim, etc.
 * \EdgeBinder\Registry\AdapterRegistry::register(new JanusAdapterFactory());
 * ```
 */
interface AdapterFactoryInterface
{
    /**
     * Create adapter instance with configuration.
     *
     * The configuration object contains three main sections:
     * - Instance-specific configuration including adapter type and connection details
     * - Global EdgeBinder configuration for context
     * - PSR-11 container for dependency injection
     *
     * Example usage:
     * ```php
     * $config = new AdapterConfiguration(
     *     instance: [
     *         'adapter' => 'janus',
     *         'janus_client' => 'janus.client.social',
     *         'graph_name' => 'SocialNetwork',
     *         'consistency_level' => 'eventual',
     *         'host' => 'localhost',
     *         'port' => 8182,
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
     *
     * @param AdapterConfiguration $config Configuration object containing instance, global, and container
     *
     * @return PersistenceAdapterInterface The configured adapter instance
     *
     * @throws \InvalidArgumentException If configuration is invalid or missing required values
     * @throws \RuntimeException         If adapter cannot be created (e.g., connection failure)
     */
    public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface;

    /**
     * Get the adapter type this factory handles.
     *
     * This should be a unique string identifier for the adapter type
     * (e.g., 'janus', 'neo4j', 'redis', 'mongodb', 'inmemory').
     *
     * The adapter type is used for:
     * - Registry lookup and registration
     * - Configuration mapping
     * - Error reporting and debugging
     * - Framework integration
     *
     * @return string The adapter type identifier (lowercase, alphanumeric with underscores)
     */
    public function getAdapterType(): string;
}
