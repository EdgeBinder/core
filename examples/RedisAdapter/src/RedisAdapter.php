<?php
declare(strict_types=1);

namespace MyVendor\RedisAdapter;

use EdgeBinder\Contracts\{PersistenceAdapterInterface, QueryResultInterface};
use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Query\{QueryCriteria, QueryResult};
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Exception\PersistenceException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Binding;

/**
 * Redis adapter for EdgeBinder.
 * 
 * This adapter stores bindings in Redis with configurable TTL and key prefixing.
 * It's designed for caching scenarios and simple relationship storage.
 * 
 * Features:
 * - Configurable TTL for automatic expiration
 * - Key prefixing for namespace isolation
 * - JSON serialization for metadata support
 * - Basic query support with pattern matching
 * 
 * Limitations:
 * - Complex queries load data into memory for filtering
 * - Not suitable for large-scale relationship graphs
 * - No ACID transaction support
 */
class RedisAdapter implements PersistenceAdapterInterface
{
    private \Redis $redis;
    private RedisTransformer $transformer;
    private array $config;

    /**
     * @param \Redis $redis Redis client instance
     * @param array<string, mixed> $config Configuration options
     */
    public function __construct(\Redis $redis, array $config = [])
    {
        $this->redis = $redis;
        $this->transformer = new RedisTransformer();  // NEW: Include transformer
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->validateConfiguration();
    }

    public function store(BindingInterface $binding): void
    {
        try {
            $key = $this->buildKey($binding->getId());
            $data = json_encode($binding->toArray(), JSON_THROW_ON_ERROR);
            
            $result = $this->redis->setex(
                $key,
                $this->config['ttl'],
                $data
            );
            
            if (!$result) {
                throw PersistenceException::operationFailed('store', 'Redis setex returned false');
            }
        } catch (\JsonException $e) {
            throw PersistenceException::operationFailed('store', 'JSON encoding failed: ' . $e->getMessage(), $e);
        } catch (\RedisException $e) {
            throw PersistenceException::serverError('store', 'Redis error: ' . $e->getMessage(), $e);
        } catch (\Exception $e) {
            if ($e instanceof PersistenceException) {
                throw $e;
            }
            throw PersistenceException::serverError('store', $e->getMessage(), $e);
        }
    }

    public function find(string $bindingId): ?BindingInterface
    {
        try {
            $key = $this->buildKey($bindingId);
            $data = $this->redis->get($key);
            
            if ($data === false) {
                return null;
            }
            
            $array = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            return Binding::fromArray($array);
        } catch (\JsonException $e) {
            throw PersistenceException::operationFailed('find', 'JSON decoding failed: ' . $e->getMessage(), $e);
        } catch (\RedisException $e) {
            throw PersistenceException::serverError('find', 'Redis error: ' . $e->getMessage(), $e);
        } catch (\Exception $e) {
            if ($e instanceof PersistenceException) {
                throw $e;
            }
            throw PersistenceException::serverError('find', $e->getMessage(), $e);
        }
    }

    public function delete(string $bindingId): void
    {
        try {
            $key = $this->buildKey($bindingId);
            $result = $this->redis->del($key);
            
            // Redis del returns the number of keys deleted
            // 0 means the key didn't exist, which is fine for delete operations
        } catch (\RedisException $e) {
            throw PersistenceException::serverError('delete', 'Redis error: ' . $e->getMessage(), $e);
        } catch (\Exception $e) {
            throw PersistenceException::serverError('delete', $e->getMessage(), $e);
        }
    }

    // 🚀 v0.6.0 LIGHT ADAPTER PATTERN - Just 3 lines!
    public function executeQuery(QueryCriteria $criteria): QueryResultInterface
    {
        $query = $criteria->transform($this->transformer);  // 1 line transformation!
        $results = $this->executeRedisQuery($query);        // Execute with Redis
        return new QueryResult($results);                   // Return QueryResult object
    }

    public function count(QueryCriteria $criteria): int
    {
        $query = $criteria->transform($this->transformer);
        return $this->executeRedisCount($query);
    }

    /**
     * Execute the transformed query with Redis
     */
    private function executeRedisQuery(array $query): array
    {
        try {
            $results = [];

            // Get all keys matching our prefix pattern
            $pattern = $this->config['prefix'] . '*';
            $keys = $this->redis->keys($pattern);

            if (empty($keys)) {
                return [];
            }

            // Load all bindings and filter in memory
            // Note: This is not efficient for large datasets
            $bindings = [];
            foreach ($keys as $key) {
                $data = $this->redis->get($key);
                if ($data !== false) {
                    try {
                        $array = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                        $bindings[] = \EdgeBinder\Binding::fromArray($array);
                    } catch (\JsonException $e) {
                        // Skip invalid bindings
                        continue;
                    }
                }
            }

            // Apply filters using transformed query
            foreach ($bindings as $binding) {
                if ($this->matchesTransformedCriteria($binding, $query)) {
                    $results[] = $binding;
                }
            }
            
            return $results;
        } catch (\RedisException $e) {
            throw PersistenceException::serverError('executeQuery', 'Redis error: ' . $e->getMessage(), $e);
        } catch (\Exception $e) {
            if ($e instanceof PersistenceException) {
                throw $e;
            }
            throw PersistenceException::serverError('executeQuery', $e->getMessage(), $e);
        }
    }

    /**
     * Execute count query with Redis
     */
    private function executeRedisCount(array $query): int
    {
        $results = $this->executeRedisQuery($query);
        return count($results);
    }

    /**
     * Check if binding matches the transformed criteria
     */
    private function matchesTransformedCriteria(\EdgeBinder\Contracts\BindingInterface $binding, array $query): bool
    {
        // This is a simplified implementation for the example
        // A real implementation would handle the transformed query format properly

        // For now, just return true to show all bindings
        // In a real implementation, you would:
        // 1. Check entity filters from $query['and'] and $query['or']
        // 2. Apply where conditions
        // 3. Handle ordering and pagination

        return true;
    }

    public function extractEntityId(object $entity): string
    {
        if ($entity instanceof EntityInterface) {
            return $entity->getId();
        }

        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if (is_string($id) && !empty($id)) {
                return $id;
            }
        }

        if (property_exists($entity, 'id')) {
            $id = $entity->id;
            if (is_string($id) && !empty($id)) {
                return $id;
            }
        }

        throw new EntityExtractionException('Cannot extract entity ID', $entity);
    }

    public function extractEntityType(object $entity): string
    {
        if ($entity instanceof EntityInterface) {
            return $entity->getType();
        }

        if (method_exists($entity, 'getType')) {
            $type = $entity->getType();
            if (is_string($type) && !empty($type)) {
                return $type;
            }
        }

        // Fall back to class name
        return basename(str_replace('\\', '/', get_class($entity)));
    }

    public function validateAndNormalizeMetadata(array $metadata): array
    {
        $normalized = [];
        
        foreach ($metadata as $key => $value) {
            if (!is_string($key) || empty($key)) {
                throw new InvalidMetadataException('Metadata keys must be non-empty strings');
            }
            
            // Validate value types
            if (is_resource($value)) {
                throw new InvalidMetadataException("Metadata value for key '{$key}' cannot be a resource");
            }
            
            if (is_object($value)) {
                if ($value instanceof \DateTimeInterface) {
                    // Convert DateTime objects to ISO 8601 strings
                    $normalized[$key] = $value->format(\DateTimeInterface::ATOM);
                } elseif (method_exists($value, '__toString')) {
                    $normalized[$key] = (string) $value;
                } else {
                    throw new InvalidMetadataException("Metadata value for key '{$key}' must be serializable");
                }
            } else {
                $normalized[$key] = $value;
            }
        }
        
        // Check size limit (Redis has a 512MB value limit, but we'll be more conservative)
        $encoded = json_encode($normalized);
        if (strlen($encoded) > $this->config['max_metadata_size']) {
            throw new InvalidMetadataException('Metadata size exceeds maximum allowed size');
        }
        
        return $normalized;
    }

    /**
     * Get default configuration values.
     * 
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'ttl' => 3600,                    // 1 hour default TTL
            'prefix' => 'edgebinder:',        // Key prefix
            'timeout' => 30,                  // Connection timeout
            'max_metadata_size' => 1048576,   // 1MB metadata limit
        ];
    }

    /**
     * Validate adapter configuration.
     * 
     * @throws \InvalidArgumentException If configuration is invalid
     */
    private function validateConfiguration(): void
    {
        if (!is_int($this->config['ttl']) || $this->config['ttl'] <= 0) {
            throw new \InvalidArgumentException('TTL must be a positive integer');
        }
        
        if (!is_string($this->config['prefix'])) {
            throw new \InvalidArgumentException('Prefix must be a string');
        }
        
        if (!is_int($this->config['timeout']) || $this->config['timeout'] <= 0) {
            throw new \InvalidArgumentException('Timeout must be a positive integer');
        }
    }

    /**
     * Build Redis key for a binding ID.
     */
    private function buildKey(string $bindingId): string
    {
        return $this->config['prefix'] . $bindingId;
    }

    /**
     * Check if a binding matches query criteria.
     * 
     * @param BindingInterface $binding
     * @param array<string, mixed> $criteria
     */
    private function matchesCriteria(BindingInterface $binding, array $criteria): bool
    {
        // This is a simplified implementation
        // A real implementation would handle complex query logic
        
        foreach ($criteria as $field => $value) {
            switch ($field) {
                case 'type':
                    if ($binding->getType() !== $value) {
                        return false;
                    }
                    break;
                case 'fromType':
                    if ($binding->getFromType() !== $value) {
                        return false;
                    }
                    break;
                case 'fromId':
                    if ($binding->getFromId() !== $value) {
                        return false;
                    }
                    break;
                case 'toType':
                    if ($binding->getToType() !== $value) {
                        return false;
                    }
                    break;
                case 'toId':
                    if ($binding->getToId() !== $value) {
                        return false;
                    }
                    break;
                default:
                    // Check metadata
                    $metadata = $binding->getMetadata();
                    if (!isset($metadata[$field]) || $metadata[$field] !== $value) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }
}
