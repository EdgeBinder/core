# EdgeBinder Weaviate Adapter

[![Tests](https://github.com/edgebinder/weaviate-adapter/actions/workflows/tests.yml/badge.svg)](https://github.com/edgebinder/weaviate-adapter/actions/workflows/tests.yml)
[![Lint](https://github.com/edgebinder/weaviate-adapter/actions/workflows/lint.yml/badge.svg)](https://github.com/edgebinder/weaviate-adapter/actions/workflows/lint.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)](LICENSE)

A Weaviate adapter for EdgeBinder that provides vector database capabilities for relationship management with semantic search and AI-powered relationship discovery.

The Weaviate adapter leverages Weaviate's vector database capabilities to store and query entity relationships with rich metadata and semantic similarity features. This adapter is particularly powerful for AI/ML applications where relationships can be discovered through vector similarity.

## 🎯 Implementation Strategy: Phased Approach

### Phase 1: Basic Adapter (Current Implementation)
**Status: Ready to Implement**
- Uses current Zestic Weaviate PHP client capabilities
- Implements core `PersistenceAdapterInterface` methods
- Provides basic relationship storage and retrieval
- Supports rich metadata without vector features

### Phase 2: Vector Enhancement (Future)
**Status: Requires Client Enhancement**
- Contribute vector query support to Zestic client
- Add semantic similarity search capabilities
- Implement advanced GraphQL query features
- Enable AI/ML relationship discovery

## Features

- **Vector Database**: Leverage Weaviate's vector capabilities for semantic search
- **Rich Metadata**: Store complex relationship data with vector representations
- **Multi-Tenancy**: Full support for tenant-isolated relationship data
- **Schema Management**: Automatic Weaviate schema creation and management
- **Type Safe**: Full PHP 8.3+ type safety with comprehensive PHPStan analysis
- **Performance Focused**: Optimized for relationship queries and vector operations

## Requirements

- PHP 8.3 or higher
- Composer
- Docker and Docker Compose (for integration tests)
- Weaviate instance (local or cloud)

## Installation

```bash
composer require edgebinder/weaviate-adapter
```

## Quick Start

```php
use EdgeBinder\EdgeBinder;
use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use Weaviate\WeaviateClient;

// Create Weaviate client
$client = WeaviateClient::connectToLocal();

// Create binder with Weaviate adapter
$adapter = new WeaviateAdapter($client);
$binder = new EdgeBinder($adapter);

// Bind entities with rich metadata
$binder->bind(
    from: $workspace,
    to: $codeRepository,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_by' => $userId,
        'granted_at' => new DateTimeImmutable(),
        'semantic_context' => 'development workspace access',
    ]
);

// Query relationships
$repositories = $binder->query()
    ->from($workspace)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();
```

## Development Setup

### 1. Clone and Install Dependencies

```bash
git clone https://github.com/edgebinder/weaviate-adapter.git
cd weaviate-adapter
composer install
```

### 2. Start Weaviate for Testing

```bash
# Start Weaviate container
composer docker-start

# Or manually with docker compose
docker compose up -d weaviate
```

### 3. Run Tests

```bash
# Run all tests (starts/stops Weaviate automatically)
composer test-docker

# Run only unit tests (no Weaviate required)
composer test-unit

# Run only integration tests (requires running Weaviate)
composer test-integration

# Run tests with coverage
composer test-coverage
```

### 4. Code Quality

```bash
# Run static analysis
composer phpstan

# Check coding standards
composer cs-check

# Fix coding standards
composer cs-fix

# Run all linting
composer lint

# Security audit
composer security-audit
```

## Project Structure

```
src/
├── WeaviateAdapter.php              # Main adapter implementation
├── Schema/
│   ├── SchemaManager.php           # Manages Weaviate schema
│   └── BindingSchema.php           # Binding class schema definition
├── Query/
│   ├── WeaviateQueryBuilder.php    # Weaviate-specific query builder
│   └── VectorQueryBuilder.php     # Vector similarity queries (Phase 2)
├── Mapping/
│   ├── BindingMapper.php          # Maps EdgeBinder objects to Weaviate
│   └── MetadataMapper.php         # Handles metadata serialization
├── Vector/
│   ├── VectorGenerator.php        # Generates vectors from metadata (Phase 2)
│   └── SimilarityCalculator.php   # Calculates relationship similarities (Phase 2)
└── Exception/
    ├── WeaviateException.php      # Weaviate-specific exceptions
    └── SchemaException.php        # Schema-related exceptions

tests/
├── Unit/                          # Unit tests (no external dependencies)
└── Integration/                   # Integration tests (requires Weaviate)
```

## Docker Commands

```bash
# Start Weaviate
composer docker-start

# Stop Weaviate
composer docker-stop

# Reset Weaviate data
composer docker-reset

# Run tests with Docker
composer test-docker
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass: `composer test-docker`
5. Ensure code quality: `composer lint`
6. Submit a pull request

## Testing Philosophy

This project follows Test-Driven Development (TDD):
- Tests drive the implementation
- High test coverage is maintained
- Both unit and integration tests are included
- Integration tests use real Weaviate instances

## Related Projects

- [EdgeBinder Core](https://github.com/edgebinder/core) - The main EdgeBinder library
- [Zestic Weaviate PHP Client](https://github.com/zestic/weaviate-php-client) - The underlying Weaviate client

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/edgebinder/weaviate-adapter/issues).

## License

The Apache License (Apache-2.0). Please see [License File](LICENSE) for more information.