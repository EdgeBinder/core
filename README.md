# EdgeBinder Core

[![Tests](https://github.com/edgebinder/core/actions/workflows/test.yaml/badge.svg)](https://github.com/edgebinder/core/actions/workflows/test.yaml)
[![Lint](https://github.com/edgebinder/core/actions/workflows/lint.yaml/badge.svg)](https://github.com/edgebinder/core/actions/workflows/lint.yaml)
[![codecov](https://codecov.io/gh/edgebinder/core/graph/badge.svg)](https://codecov.io/gh/edgebinder/core)
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

## Requirements

- PHP 8.3 or higher
- Composer

## Installation

```bash
composer require edgebinder/core
```

## Quick Start

```php
use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\InMemory\InMemoryAdapter;

// Create binder with in-memory adapter (for testing/development)
$adapter = new InMemoryAdapter();
$binder = new EdgeBinder($adapter);

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

## Development Setup

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/edgebinder/core.git
cd core
composer install
```

### 2. Run Tests

```bash
# Run all tests
composer test

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
├── EdgeBinder.php                 # Main EdgeBinder class
├── Binding.php                    # Binding entity representation
├── Contracts/
│   └── PersistenceAdapterInterface.php  # Adapter interface
├── Query/
│   └── BindingQueryBuilder.php    # Query builder for relationships
├── Adapter/
│   └── InMemory/
│       └── InMemoryAdapter.php    # Built-in in-memory adapter
└── Exception/
    ├── BindingNotFoundException.php
    ├── EntityExtractionException.php
    ├── InvalidMetadataException.php
    └── PersistenceException.php

tests/
├── BindingTest.php                # Binding entity tests
├── EdgeBinderTest.php             # Main class tests
├── Query/
│   └── BindingQueryBuilderTest.php
├── Contracts/
│   └── InterfaceContractTest.php
└── Exception/
    └── ExceptionTest.php
```



## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass: `composer test`
5. Ensure code quality: `composer phpstan && composer cs-check && composer composer-normalize && composer security-audit`
6. Submit a pull request

## Testing Philosophy

This project follows Test-Driven Development (TDD):
- Tests drive the implementation
- High test coverage is maintained (97%+ line coverage)
- Comprehensive unit tests for all components
- Clean, maintainable test code

## Related Projects

- [EdgeBinder PDO Adapter](https://github.com/edgebinder/pdo-adapter) - SQL database adapter
- [EdgeBinder MongoDB Adapter](https://github.com/edgebinder/mongodb-adapter) - MongoDB adapter
- [EdgeBinder Weaviate Adapter](https://github.com/edgebinder/weaviate-adapter) - Vector database adapter

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/edgebinder/core/issues).

## License

The Apache License (Apache-2.0). Please see [License File](LICENSE) for more information.