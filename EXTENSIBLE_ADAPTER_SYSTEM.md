# EdgeBinder Framework-Agnostic Extensible Adapter System

## Overview

This document outlines the implementation plan for adding a framework-agnostic extensible adapter system to EdgeBinder Core. This system will allow third-party developers to create custom adapters that work seamlessly across all PHP frameworks (Laminas, Symfony, Laravel, Slim, etc.) without requiring modifications to EdgeBinder Core or framework-specific components.

## Architecture Goals

### Primary Objectives
1. **Framework Agnostic**: Adapters work identically across all PHP frameworks
2. **No Core Modifications**: Third-party adapters don't require changes to EdgeBinder Core
3. **Universal Distribution**: One adapter package works with all frameworks
4. **Clean Architecture**: Clear separation between core logic and framework integration
5. **Type Safety**: Strong typing through well-defined interfaces
6. **Container Access**: Adapters can use dependency injection from any framework

### Design Principles
- **PSR-11 Based**: Use standard container interfaces for maximum compatibility
- **Static Registry**: Simple, framework-agnostic registration mechanism
- **Standardized Interface**: Consistent API for all adapter factories
- **Configuration Consistency**: Same configuration patterns across all adapters

## Core Components to Implement

### 1. AdapterFactoryInterface

**Location**: `src/Registry/AdapterFactoryInterface.php`

```php
<?php
declare(strict_types=1);

namespace EdgeBinder\Registry;

use EdgeBinder\Contracts\PersistenceAdapterInterface;

/**
 * Interface for third-party adapter factories.
 * 
 * This interface provides a framework-agnostic way for third-party
 * developers to create custom adapters that work across all PHP frameworks.
 */
interface AdapterFactoryInterface
{
    /**
     * Create adapter instance with configuration.
     *
     * @param array $config Configuration array containing:
     *                     - 'instance': instance-specific configuration
     *                     - 'global': global EdgeBinder configuration  
     *                     - 'container': PSR-11 container for dependency injection
     * 
     * @return PersistenceAdapterInterface The configured adapter instance
     * 
     * @throws \InvalidArgumentException If configuration is invalid
     * @throws \RuntimeException If adapter cannot be created
     */
    public function createAdapter(array $config): PersistenceAdapterInterface;
    
    /**
     * Get the adapter type this factory handles.
     * 
     * This should be a unique string identifier for the adapter type
     * (e.g., 'janus', 'neo4j', 'redis', 'mongodb').
     * 
     * @return string The adapter type identifier
     */
    public function getAdapterType(): string;
}
```

### 2. AdapterRegistry

**Location**: `src/Registry/AdapterRegistry.php`

```php
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
     * @throws \InvalidArgumentException If adapter type is already registered
     */
    public static function register(AdapterFactoryInterface $factory): void
    {
        $type = $factory->getAdapterType();
        
        if (isset(self::$factories[$type])) {
            throw new \InvalidArgumentException(
                "Adapter type '{$type}' is already registered"
            );
        }
        
        self::$factories[$type] = $factory;
    }
    
    /**
     * Create adapter instance.
     * 
     * @param string $type The adapter type to create
     * @param array $config Configuration for the adapter
     * 
     * @return PersistenceAdapterInterface The created adapter instance
     * 
     * @throws \InvalidArgumentException If adapter type is not registered
     */
    public static function create(string $type, array $config): PersistenceAdapterInterface
    {
        if (!isset(self::$factories[$type])) {
            throw new \InvalidArgumentException(
                "Adapter type '{$type}' is not registered. " .
                "Available types: " . implode(', ', array_keys(self::$factories))
            );
        }
        
        return self::$factories[$type]->createAdapter($config);
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
     * This method is primarily for testing purposes.
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
     * This method is primarily for testing purposes.
     */
    public static function clear(): void
    {
        self::$factories = [];
    }
}
```

### 3. AdapterException

**Location**: `src/Exception/AdapterException.php`

```php
<?php
declare(strict_types=1);

namespace EdgeBinder\Exception;

/**
 * Exception thrown when adapter operations fail.
 */
class AdapterException extends EdgeBinderException
{
    public static function factoryNotFound(string $adapterType, array $availableTypes = []): self
    {
        $message = "Adapter factory for type '{$adapterType}' not found.";
        
        if (!empty($availableTypes)) {
            $message .= " Available types: " . implode(', ', $availableTypes);
        }
        
        return new self($message);
    }
    
    public static function creationFailed(string $adapterType, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            "Failed to create adapter of type '{$adapterType}': {$reason}",
            0,
            $previous
        );
    }
    
    public static function alreadyRegistered(string $adapterType): self
    {
        return new self("Adapter type '{$adapterType}' is already registered");
    }
}
```

## Implementation Phases

### Phase 1: Core Registry Implementation (Week 1)

#### Acceptance Criteria
- [ ] `AdapterFactoryInterface` is implemented with proper PHPDoc
- [ ] `AdapterRegistry` is implemented with static methods
- [ ] `AdapterException` is implemented with factory methods
- [ ] All classes have 100% unit test coverage
- [ ] PHPStan level 8 passes without errors
- [ ] All methods are properly documented with examples

#### Tasks
1. **Create Interface** (`src/Registry/AdapterFactoryInterface.php`)
   - Define `createAdapter(array $config): PersistenceAdapterInterface`
   - Define `getAdapterType(): string`
   - Add comprehensive PHPDoc with parameter details
   - Include usage examples in docblocks

2. **Create Registry** (`src/Registry/AdapterRegistry.php`)
   - Implement static `register(AdapterFactoryInterface $factory): void`
   - Implement static `create(string $type, array $config): PersistenceAdapterInterface`
   - Implement static `hasAdapter(string $type): bool`
   - Implement static `getRegisteredTypes(): array`
   - Add testing methods: `unregister()`, `clear()`
   - Handle duplicate registration errors
   - Provide helpful error messages with available types

3. **Create Exception** (`src/Exception/AdapterException.php`)
   - Extend `EdgeBinderException`
   - Add factory methods for common error scenarios
   - Include context information in error messages

4. **Unit Tests**
   - Test successful adapter registration and creation
   - Test duplicate registration prevention
   - Test error handling for unknown adapter types
   - Test registry state management (clear, unregister)
   - Test exception factory methods
   - Mock adapter factories for testing

#### Deliverables
- All source files implemented
- Complete unit test suite (>95% coverage)
- Updated `composer.json` autoload paths
- PHPStan configuration updated if needed

### Phase 2: Integration with EdgeBinder Core (Week 2)

#### Acceptance Criteria
- [ ] `EdgeBinder` class can discover and use registered adapters
- [ ] Framework components can integrate with the registry
- [ ] Configuration format supports third-party adapters
- [ ] Backward compatibility is maintained for existing adapters
- [ ] Integration tests pass with mock third-party adapters
- [ ] Documentation includes integration examples

#### Tasks
1. **Update EdgeBinder Class** (`src/EdgeBinder.php`)
   - Add adapter discovery logic to constructor or factory methods
   - Support registry-based adapter creation
   - Maintain backward compatibility with existing adapters
   - Add configuration validation for third-party adapters

2. **Configuration Format Updates**
   - Define standard configuration structure for third-party adapters
   - Ensure configuration is framework-agnostic
   - Support both simple and complex adapter configurations
   - Add validation for required configuration keys

3. **Integration Points**
   - Define how framework components discover registered adapters
   - Create helper methods for building adapter configurations
   - Ensure PSR-11 container access is properly passed to adapters
   - Handle adapter creation errors gracefully

4. **Integration Tests**
   - Create mock third-party adapter for testing
   - Test adapter registration and discovery flow
   - Test configuration passing and validation
   - Test error handling in integration scenarios
   - Test multiple adapter types working together

#### Deliverables
- Updated `EdgeBinder` class with adapter discovery
- Integration test suite with mock adapters
- Configuration format documentation
- Framework integration guidelines

### Phase 3: Documentation and Examples (Week 3)

#### Acceptance Criteria
- [ ] Complete developer documentation for creating third-party adapters
- [ ] Framework-specific integration examples (Laminas, Symfony, Laravel, Slim)
- [ ] Reference implementation of a third-party adapter
- [ ] Migration guide for existing custom adapters
- [ ] API documentation is updated
- [ ] README includes extensibility information

#### Tasks
1. **Developer Documentation**
   - Create comprehensive guide for third-party adapter development
   - Include step-by-step tutorial with complete example
   - Document configuration format and requirements
   - Explain framework integration patterns
   - Add troubleshooting section

2. **Framework Integration Examples**
   - **Laminas/Mezzio**: ConfigProvider and bootstrap registration
   - **Symfony**: Service configuration and compiler passes
   - **Laravel**: Service provider registration
   - **Slim**: Container configuration
   - **Generic PHP**: Direct registration examples

3. **Reference Implementation**
   - Create complete example adapter (e.g., Redis adapter)
   - Include proper error handling and validation
   - Demonstrate best practices for configuration
   - Show how to use container services
   - Include unit tests for the example adapter

4. **Migration Documentation**
   - Guide for converting existing custom adapters
   - Breaking changes and compatibility notes
   - Step-by-step migration process
   - Common pitfalls and solutions

#### Deliverables
- `docs/EXTENSIBLE_ADAPTERS.md` - Complete developer guide
- `docs/FRAMEWORK_INTEGRATION.md` - Framework-specific examples
- `examples/RedisAdapter/` - Reference implementation
- `docs/MIGRATION_GUIDE.md` - Migration documentation
- Updated `README.md` with extensibility overview

### Phase 4: Testing and Validation (Week 4)

#### Acceptance Criteria
- [ ] All tests pass across PHP 8.3+ versions
- [ ] Integration tests with real framework components
- [ ] Performance benchmarks show no significant overhead
- [ ] Security review completed for static registry approach
- [ ] Documentation review completed
- [ ] Community feedback incorporated

#### Tasks
1. **Comprehensive Testing**
   - Cross-PHP version testing (8.3, 8.4)
   - Integration tests with actual framework components
   - Performance testing with multiple registered adapters
   - Memory usage analysis for static registry
   - Concurrency testing for static registry safety

2. **Security Review**
   - Review static registry for potential security issues
   - Validate adapter factory interface for injection attacks
   - Ensure configuration validation prevents malicious input
   - Review error messages for information disclosure

3. **Performance Validation**
   - Benchmark adapter discovery performance
   - Compare performance with and without registered adapters
   - Memory usage analysis with many registered adapters
   - Optimization if performance issues are found

4. **Community Integration**
   - Create example adapters for popular databases
   - Test with real framework applications
   - Gather feedback from early adopters
   - Address any compatibility issues discovered

#### Deliverables
- Complete test suite with >95% coverage
- Performance benchmark results
- Security review report
- Community feedback integration
- Final documentation review

## Third-Party Adapter Development Guide

### Creating a Custom Adapter

#### 1. Implement AdapterFactoryInterface

```php
<?php
namespace MyVendor\JanusEdgeBinderAdapter;

use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;

class JanusAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(array $config): PersistenceAdapterInterface
    {
        $container = $config['container'];
        $instanceConfig = $config['instance'];
        $globalConfig = $config['global'];

        // Get configuration from flatter structure
        $janusClient = $container->get($instanceConfig['janus_client'] ?? 'janus.client.default');

        // Build adapter configuration from flatter structure
        $adapterConfig = [
            'graph_name' => $instanceConfig['graph_name'] ?? 'DefaultGraph',
            'consistency_level' => $instanceConfig['consistency_level'] ?? 'eventual',
            // Extract other config directly from instance config
        ];

        return new JanusAdapter($janusClient, $adapterConfig);
    }

    public function getAdapterType(): string
    {
        return 'janus';
    }
}
```

#### 2. Register Across Frameworks

**Laminas/Mezzio:**
```php
// In Module.php or application bootstrap
\EdgeBinder\Registry\AdapterRegistry::register(new JanusAdapterFactory());
```

**Symfony:**
```php
// In bundle boot method or compiler pass
\EdgeBinder\Registry\AdapterRegistry::register(new JanusAdapterFactory());
```

**Laravel:**
```php
// In service provider boot method
\EdgeBinder\Registry\AdapterRegistry::register(new JanusAdapterFactory());
```

**Slim:**
```php
// In application bootstrap
\EdgeBinder\Registry\AdapterRegistry::register(new JanusAdapterFactory());
```

#### 3. Universal Configuration

```php
// Works identically across all frameworks
return [
    'edgebinder' => [
        'social' => [
            'adapter' => 'janus',  // Custom adapter
            'connection' => 'social_graph',
        ],
        'janus' => [
            'connections' => [
                'social_graph' => [
                    'client' => 'janus.client.social',
                    'config' => [
                        'graph_name' => 'SocialNetwork',
                        'consistency_level' => 'eventual',
                    ],
                ],
            ],
        ],
    ],
];
```

## Configuration Format Specification

### Standard Configuration Structure (Flatter Approach)

```php
[
    'instance' => [
        'adapter' => 'adapter_type',
        'adapter_type_client' => 'service_name',
        // adapter-specific config directly here
        'host' => 'localhost',
        'port' => 1234,
        'graph_name' => 'MyGraph',
    ],
    'global' => $globalEdgeBinderConfig, // Full global config for context
    'container' => $psrContainer, // PSR-11 container instance
]
```

### Configuration Validation Requirements

1. **Required Keys**: `instance`, `global`, `container`
2. **Instance Config**: Must contain `adapter` key
3. **Container**: Must implement `Psr\Container\ContainerInterface`
4. **Adapter-Specific**: Defined by each adapter factory

## Testing Strategy

### Unit Tests
- Test `AdapterRegistry` registration and creation
- Test `AdapterFactoryInterface` implementations
- Test error handling and edge cases
- Test configuration validation

### Integration Tests
- Test with mock framework components
- Test adapter discovery flow
- Test configuration passing
- Test multiple adapters working together

### Framework Integration Tests
- Test registration in actual framework applications
- Test container integration
- Test configuration loading
- Test error handling in framework context

## Success Metrics

### Technical Metrics
- **Test Coverage**: >95% for all new code
- **Performance**: <5ms overhead for adapter discovery
- **Memory**: <1MB additional memory usage for registry
- **Compatibility**: Works with PHP 8.3+ and all major frameworks

### Adoption Metrics
- **Third-Party Adapters**: At least 3 community adapters created within 6 months
- **Framework Support**: Integration examples for 5+ frameworks
- **Documentation**: Complete developer guide with examples
- **Community Feedback**: Positive feedback from early adopters

## Risk Mitigation

### Static Registry Concerns
- **Thread Safety**: PHP's single-threaded nature makes static registry safe
- **Memory Leaks**: Registry cleanup methods for testing environments
- **Global State**: Isolated to adapter registration only

### Backward Compatibility
- **Existing Adapters**: Continue to work without changes
- **Configuration**: Maintain existing configuration formats
- **API**: No breaking changes to public interfaces

### Security Considerations
- **Input Validation**: Validate all configuration inputs
- **Container Access**: Limit container access to registered services only
- **Error Messages**: Avoid exposing sensitive information in errors

This extensible adapter system will make EdgeBinder truly framework-agnostic while maintaining clean architecture and excellent developer experience across all PHP frameworks.
