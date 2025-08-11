<?php
declare(strict_types=1);

namespace MyVendor\RedisAdapter;

use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Registry\AdapterConfiguration;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Exception\AdapterException;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating Redis adapter instances.
 * 
 * This factory implements the EdgeBinder extensible adapter system,
 * allowing Redis adapters to be created consistently across all PHP frameworks.
 * 
 * Example usage:
 * ```php
 * // Register the factory
 * AdapterRegistry::register(new RedisAdapterFactory());
 * 
 * // Create adapter through EdgeBinder
 * $config = [
 *     'adapter' => 'redis',
 *     'redis_client' => 'redis.client.cache',
 *     'ttl' => 3600,
 *     'prefix' => 'edgebinder:',
 * ];
 * 
 * $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
 * ```
 */
class RedisAdapterFactory implements AdapterFactoryInterface
{
    /**
     * Create Redis adapter instance with configuration.
     * 
     * The configuration array contains:
     * - 'instance': instance-specific configuration
     * - 'global': global EdgeBinder configuration
     * - 'container': PSR-11 container for dependency injection
     * 
     * Instance configuration supports:
     * - 'redis_client': Container service name for Redis client (required)
     * - 'ttl': Time-to-live for cached bindings in seconds (default: 3600)
     * - 'prefix': Key prefix for Redis keys (default: 'edgebinder:')
     * - 'timeout': Connection timeout in seconds (default: 30)
     * - 'max_metadata_size': Maximum metadata size in bytes (default: 1048576)
     *
     * @param AdapterConfiguration $config Configuration object
     *
     * @return PersistenceAdapterInterface Configured Redis adapter instance
     *
     * @throws AdapterException If configuration is invalid or adapter creation fails
     */
    public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface
    {
        try {
            $container = $config->getContainer();
            $instanceConfig = $config->getInstanceConfig();
            $globalConfig = $config->getGlobalSettings();

            // Get Redis client from container
            $redisClientService = $config->getInstanceValue('redis_client', 'redis.client.default');
            $redisClient = $this->getRedisClient($container, $redisClientService);

            // Build adapter configuration from instance config
            $adapterConfig = $this->buildAdapterConfig($instanceConfig, $globalConfig);
            
            return new RedisAdapter($redisClient, $adapterConfig);
        } catch (\Throwable $e) {
            if ($e instanceof AdapterException) {
                throw $e;
            }
            
            throw AdapterException::creationFailed(
                $this->getAdapterType(),
                $e->getMessage(),
                $e
            );
        }
    }
    
    /**
     * Get the adapter type identifier.
     * 
     * @return string The adapter type 'redis'
     */
    public function getAdapterType(): string
    {
        return 'redis';
    }
    

    
    /**
     * Get Redis client from container.
     * 
     * @param ContainerInterface $container PSR-11 container
     * @param string $serviceName Service name for Redis client
     * 
     * @return \Redis Redis client instance
     * 
     * @throws AdapterException If Redis client cannot be retrieved or is invalid
     */
    private function getRedisClient(ContainerInterface $container, string $serviceName): \Redis
    {
        try {
            if (!$container->has($serviceName)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    "Redis client service '{$serviceName}' not found in container"
                );
            }
            
            $client = $container->get($serviceName);
            
            if (!$client instanceof \Redis) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    "Service '{$serviceName}' must return a Redis instance, got " . get_class($client)
                );
            }
            
            // Test Redis connection
            if (!$client->ping()) {
                throw AdapterException::creationFailed(
                    $this->getAdapterType(),
                    'Redis client is not connected'
                );
            }
            
            return $client;
        } catch (\Throwable $e) {
            if ($e instanceof AdapterException) {
                throw $e;
            }
            
            throw AdapterException::creationFailed(
                $this->getAdapterType(),
                "Failed to get Redis client '{$serviceName}': " . $e->getMessage(),
                $e
            );
        }
    }
    
    /**
     * Build adapter configuration from instance and global config.
     * 
     * @param array<string, mixed> $instanceConfig Instance-specific configuration
     * @param array<string, mixed> $globalConfig Global EdgeBinder configuration
     * 
     * @return array<string, mixed> Adapter configuration
     */
    private function buildAdapterConfig(array $instanceConfig, array $globalConfig): array
    {
        $config = [];
        
        // Extract TTL configuration
        if (isset($instanceConfig['ttl'])) {
            $ttl = $instanceConfig['ttl'];
            if (!is_int($ttl) || $ttl <= 0) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'TTL must be a positive integer'
                );
            }
            $config['ttl'] = $ttl;
        }
        
        // Extract prefix configuration
        if (isset($instanceConfig['prefix'])) {
            $prefix = $instanceConfig['prefix'];
            if (!is_string($prefix)) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Prefix must be a string'
                );
            }
            $config['prefix'] = $prefix;
        }
        
        // Extract timeout configuration
        if (isset($instanceConfig['timeout'])) {
            $timeout = $instanceConfig['timeout'];
            if (!is_int($timeout) || $timeout <= 0) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Timeout must be a positive integer'
                );
            }
            $config['timeout'] = $timeout;
        }
        
        // Extract max metadata size configuration
        if (isset($instanceConfig['max_metadata_size'])) {
            $size = $instanceConfig['max_metadata_size'];
            if (!is_int($size) || $size <= 0) {
                throw AdapterException::invalidConfiguration(
                    $this->getAdapterType(),
                    'Max metadata size must be a positive integer'
                );
            }
            $config['max_metadata_size'] = $size;
        }
        
        // You could also use global configuration here if needed
        // For example, global timeout settings, debug flags, etc.
        if (isset($globalConfig['debug']) && $globalConfig['debug']) {
            $config['debug'] = true;
        }
        
        return $config;
    }
}
