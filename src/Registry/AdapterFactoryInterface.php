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
 *     public function createAdapter(array $config): PersistenceAdapterInterface
 *     {
 *         $container = $config['container'];
 *         $instanceConfig = $config['instance'];
 *         $globalConfig = $config['global'];
 *
 *         // Get configuration from flatter structure
 *         $janusClient = $container->get($instanceConfig['janus_client'] ?? 'janus.client.default');
 *
 *         // Build adapter configuration from flatter structure
 *         $adapterConfig = [
 *             'graph_name' => $instanceConfig['graph_name'] ?? 'DefaultGraph',
 *             'consistency_level' => $instanceConfig['consistency_level'] ?? 'eventual',
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
     * The configuration array contains three main sections:
     * - 'instance': Instance-specific configuration including adapter type and connection details
     * - 'global': Global EdgeBinder configuration for context
     * - 'container': PSR-11 container for dependency injection
     *
     * Example configuration structure:
     * ```php
     * [
     *     'instance' => [
     *         'adapter' => 'janus',
     *         'janus_client' => 'janus.client.social',
     *         'graph_name' => 'SocialNetwork',
     *         'consistency_level' => 'eventual',
     *         'host' => 'localhost',
     *         'port' => 8182,
     *     ],
     *     'global' => [
     *         // Full global EdgeBinder configuration
     *         'default_metadata_validation' => true,
     *         'entity_extraction_strategy' => 'reflection',
     *     ],
     *     'container' => $psrContainer, // PSR-11 ContainerInterface instance
     * ]
     * ```
     *
     * @param array<string, mixed> $config Configuration array containing:
     *                                     - 'instance': instance-specific configuration
     *                                     - 'global': global EdgeBinder configuration
     *                                     - 'container': PSR-11 container for dependency injection
     *
     * @return PersistenceAdapterInterface The configured adapter instance
     *
     * @throws \InvalidArgumentException If configuration is invalid or missing required keys
     * @throws \RuntimeException         If adapter cannot be created (e.g., connection failure)
     */
    public function createAdapter(array $config): PersistenceAdapterInterface;

    /**
     * Get the adapter type this factory handles.
     *
     * This should be a unique string identifier for the adapter type
     * (e.g., 'janus', 'neo4j', 'redis', 'mongodb', 'weaviate').
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
