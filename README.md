# EdgeBinder

[![Tests](https://github.com/EdgeBinder/edgebinder/actions/workflows/test.yaml/badge.svg)](https://github.com/EdgeBinder/edgebinder/actions/workflows/test.yaml)
[![Lint](https://github.com/EdgeBinder/edgebinder/actions/workflows/lint.yaml/badge.svg)](https://github.com/EdgeBinder/edgebinder/actions/workflows/lint.yaml)
[![codecov](https://codecov.io/gh/EdgeBinder/edgebinder/graph/badge.svg)](https://codecov.io/gh/EdgeBinder/edgebinder)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)](LICENSE)

Lightweight, storage-agnostic relationship management for clean domain architectures. EdgeBinder provides a simple, elegant way to manage entity relationships without the complexity of full ORMs or the limitations of basic pivot tables.

EdgeBinder follows Domain-Driven Design principles and provides a clean abstraction layer over various storage backends through pluggable adapters. Whether you need simple SQL storage, document databases, or advanced vector databases, EdgeBinder adapts to your needs.

## Features

- **Storage Agnostic**: Use any storage backend through pluggable adapters
- **Rich Metadata**: Store complex relationship data with full metadata support
- **Type Safe**: Full PHP 8.3+ type safety with comprehensive PHPStan analysis
- **Domain-Driven Design**: Clean abstraction that doesn't pollute your domain entities
- **Query Builder**: Fluent, expressive query interface for relationship discovery
- **Performance Focused**: Optimized for relationship queries and bulk operations
- **Framework Agnostic**: Works seamlessly with Laminas, Symfony, Laravel, Slim, and any PSR-11 framework
- **Extensible**: Third-party adapters work across all frameworks without modifications

## Requirements

- PHP 8.3 or higher
- Composer

## Installation

```bash
composer require edgebinder/edgebinder
```

## Quick Start

### Basic Usage

```php
use EdgeBinder\EdgeBinder;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;

// Option 1: Direct adapter usage (for testing/development)
$adapter = new InMemoryAdapter();
$binder = new EdgeBinder($adapter);

// Option 2: Configuration-based approach (recommended)
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Persistence\InMemory\InMemoryAdapterFactory;

AdapterRegistry::register(new InMemoryAdapterFactory());
$binder = EdgeBinder::fromConfiguration(['adapter' => 'inmemory'], $container);

// Bind entities with rich metadata
$binder->bind(
    from: $user,
    to: $project,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_by' => $adminUserId,
        'granted_at' => new DateTimeImmutable(),
        'expires_at' => new DateTimeImmutable('+1 year'),
    ]
);

// Query relationships
$projects = $binder->query()
    ->from($user)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();
```

### Using Third-Party Adapters

EdgeBinder supports both automatic and manual adapter registration:

EdgeBinder adapters automatically register when their packages are loaded:

```php
use EdgeBinder\EdgeBinder;

// No registration needed - adapters auto-register when packages are loaded
$config = [
    'adapter' => 'redis',
    'redis_client' => 'redis.client.cache',
    'ttl' => 3600,
    'prefix' => 'edgebinder:',
];

$binder = EdgeBinder::fromConfiguration($config, $container);

// Use exactly the same API regardless of adapter
$binder->bind($user, $project, 'has_access', ['level' => 'admin']);
```

> **Note**: For creating custom adapters, see the [Extensible Adapters Guide](docs/EXTENSIBLE_ADAPTERS.md) for implementation details.

## Development Setup

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/EdgeBinder/edgebinder.git
cd edgebinder
composer install
```

### 2. Run Tests

```bash
# Run all tests
composer test

# Run only unit tests
vendor/bin/phpunit tests/Unit

# Run only integration tests
vendor/bin/phpunit tests/Integration

# Run tests with coverage
composer test-coverage
```

### 3. Code Quality

```bash
# Run static analysis
composer phpstan

# Check coding standards
composer cs-check

# Fix coding standards
composer cs-fix

# Check composer.json normalization
composer composer-normalize

# Fix composer.json normalization
composer composer-normalize-fix

# Security audit
composer security-audit
```

## Project Structure

```
src/
├── EdgeBinder.php                 # Main EdgeBinder class with factory methods
├── Binding.php                    # Binding entity representation
├── Contracts/
│   ├── PersistenceAdapterInterface.php  # Adapter interface
│   ├── EdgeBinderInterface.php    # Main service interface
│   └── EntityInterface.php       # Optional entity interface
├── Query/
│   └── BindingQueryBuilder.php    # Query builder for relationships
├── Registry/                      # Extensible adapter system
│   ├── AdapterFactoryInterface.php # Factory interface for third-party adapters
│   └── AdapterRegistry.php       # Static registry for adapter discovery
├── Storage/
│   └── InMemory/
│       └── InMemoryAdapter.php    # Built-in in-memory adapter
└── Exception/
    ├── AdapterException.php       # Adapter-related exceptions
    ├── BindingNotFoundException.php
    ├── EntityExtractionException.php
    ├── InvalidMetadataException.php
    └── PersistenceException.php

docs/                              # Comprehensive documentation
├── EXTENSIBLE_ADAPTERS.md         # Third-party adapter development guide
├── FRAMEWORK_INTEGRATION.md       # Framework-specific integration examples
└── MIGRATION_GUIDE.md            # Migration guide for existing adapters

examples/                          # Reference implementations
└── RedisAdapter/                  # Complete Redis adapter example
    ├── src/
    │   ├── RedisAdapter.php
    │   └── RedisAdapterFactory.php
    └── tests/

tests/
├── Unit/                          # Unit tests (196 tests)
│   ├── Contracts/                 # Interface contract tests
│   ├── Exception/                 # Exception hierarchy tests
│   ├── Persistence/InMemory/      # InMemory adapter tests
│   ├── Query/                     # Query builder tests
│   └── Registry/                  # Registry system tests
└── Integration/                   # Integration tests (14 tests)
    └── ... (end-to-end workflow tests)
```



## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass: `composer test`
5. Ensure code quality: `composer phpstan && composer cs-check && composer composer-normalize && composer security-audit`
6. Submit a pull request

## Testing Philosophy

This project follows Test-Driven Development (TDD) with a clear separation of test types:

### Unit Tests (`tests/Unit/`)
- **196 tests** focusing on individual component behavior
- Fast, isolated tests with mocked dependencies
- Test individual methods, edge cases, and error conditions
- Organized by source code structure

### Integration Tests (`tests/Integration/`)
- **14 tests** focusing on cross-component workflows
- Test real interactions between components
- End-to-end scenarios with actual dependencies
- Framework integration and adapter lifecycle testing

### **AbstractAdapterTestSuite** (`src/Testing/`)
- **57 comprehensive compliance tests** for all adapters
- **REQUIRED for all adapter implementations**
- Tests real EdgeBinder integration scenarios
- Covers all query patterns, metadata handling, and edge cases
- **84.71% line coverage** of reference implementation
- **Proven to catch production bugs** (found 5+ critical issues)

**Total Coverage**: 267+ tests with 97%+ line coverage

## Extensible Adapter System

EdgeBinder features a powerful extensible adapter system that allows third-party developers to create custom adapters that work seamlessly across all PHP frameworks.

### Creating Third-Party Adapters

```php
// 1. Implement the adapter
class MyCustomAdapter implements PersistenceAdapterInterface { /* ... */ }

// 2. Create a factory
class MyCustomAdapterFactory implements AdapterFactoryInterface
{
    public function createAdapter(AdapterConfiguration $config): PersistenceAdapterInterface
    {
        $container = $config->getContainer();
        $instanceConfig = $config->getInstanceConfig();

        $client = $container->get($instanceConfig['my_client']);
        return new MyCustomAdapter($client, $instanceConfig);
    }

    public function getAdapterType(): string { return 'mycustom'; }
}

// 3. REQUIRED: Create compliance tests
use EdgeBinder\Testing\AbstractAdapterTestSuite;

class MyCustomAdapterTest extends AbstractAdapterTestSuite
{
    protected function createAdapter(): PersistenceAdapterInterface
    {
        return new MyCustomAdapter($this->setupClient());
    }

    protected function cleanupAdapter(): void
    {
        $this->teardownClient();
    }

    // 57+ comprehensive tests inherited automatically
}

// 4. Register in any framework
AdapterRegistry::register(new MyCustomAdapterFactory());

// 5. Use with consistent configuration
$edgeBinder = EdgeBinder::fromConfiguration([
    'adapter' => 'mycustom',
    'my_client' => 'my.service.client',
    'custom_option' => 'value',
], $container);
```

> **⚠️ CRITICAL**: All adapters must extend `AbstractAdapterTestSuite` to ensure 100% compliance. See the [Extensible Adapters Guide](docs/EXTENSIBLE_ADAPTERS.md) for details.

### Framework Integration

The same adapter works identically across all frameworks:

- **Laminas/Mezzio**: Register in `Module.php` or `ConfigProvider`
- **Symfony**: Register in bundle boot or compiler pass
- **Laravel**: Register in service provider boot method
- **Slim**: Register in application bootstrap
- **Generic PHP**: Register anywhere in application setup

### Documentation

- **[Extensible Adapters Guide](docs/EXTENSIBLE_ADAPTERS.md)** - Complete development guide
- **[Adapter Testing Standard](docs/ADAPTER_TESTING_STANDARD.md)** - **REQUIRED** compliance testing guide
- **[Framework Integration](docs/FRAMEWORK_INTEGRATION.md)** - Framework-specific examples
- **[Migration Guide](docs/MIGRATION_GUIDE.md)** - Converting existing adapters
- **[Future Development Plans](docs/FUTURE_PLANS.md)** - Roadmap and integration patterns
- **[Redis Adapter Example](examples/RedisAdapter/)** - Complete reference implementation

## Related Projects

- [EdgeBinder PDO Adapter](https://github.com/edgebinder/pdo-adapter) - SQL database adapter
- [EdgeBinder MongoDB Adapter](https://github.com/edgebinder/mongodb-adapter) - MongoDB adapter
- [EdgeBinder Weaviate Adapter](https://github.com/edgebinder/weaviate-adapter) - Vector database adapter

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/EdgeBinder/edgebinder/issues).

## License

The Apache License (Apache-2.0). Please see [License File](LICENSE) for more information.