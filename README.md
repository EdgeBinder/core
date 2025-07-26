# EdgeBinder Core

[![Tests](https://github.com/edgebinder/core/workflows/Tests/badge.svg)](https://github.com/edgebinder/core/actions)
[![Lint](https://github.com/edgebinder/core/workflows/Lint/badge.svg)](https://github.com/edgebinder/core/actions)
[![Latest Stable Version](https://poser.pugx.org/edgebinder/core/v/stable)](https://packagist.org/packages/edgebinder/core)
[![License](https://poser.pugx.org/edgebinder/core/license)](https://packagist.org/packages/edgebinder/core)

Lightweight, storage-agnostic relationship management for clean domain architectures.

EdgeBinder provides a simple yet powerful way to manage entity relationships with rich metadata support, designed for modern architectures including Domain-Driven Design (DDD), microservices, and vector databases.

## Features

- **Clean Architecture**: Keep relationships separate from domain entities
- **Storage Agnostic**: Switch between SQL, NoSQL, graph, and vector databases
- **Rich Metadata**: Store complex relationship data including embeddings and vectors
- **Framework Independent**: Works with any PHP project
- **Type Safe**: Full PHP 8.3+ type safety
- **Performance Focused**: Optimized for relationship queries

## Installation

```bash
composer require edgebinder/core
```

## Quick Start

```php
use EdgeBinder\EdgeBinder;
use EdgeBinder\Storage\InMemoryAdapter;

// Create binder with storage adapter
$binder = new EdgeBinder(new InMemoryAdapter());

// Bind entities with metadata
$binder->bind(
    from: $workspace,
    to: $codeRepository,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_by' => $userId,
        'granted_at' => new DateTimeImmutable(),
    ]
);

// Query relationships
$repositories = $binder->query()
    ->from($workspace)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();
```

## Storage Adapters

EdgeBinder supports multiple storage backends through separate adapter packages:

- **`edgebinder/pdo-adapter`** - SQL databases (MySQL, PostgreSQL, SQLite)
- **`edgebinder/mongodb-adapter`** - MongoDB document storage
- **`edgebinder/redis-adapter`** - Redis key-value storage
- **`edgebinder/weaviate-adapter`** - Weaviate vector database
- **`edgebinder/neo4j-adapter`** - Neo4j graph database

## Documentation

- [Getting Started](docs/getting-started.md)
- [Storage Adapters](docs/storage-adapters.md)
- [API Reference](docs/api-reference.md)
- [Examples](examples/)

## Requirements

- PHP 8.3 or higher

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The Apache License (Apache-2.0). Please see [License File](LICENSE) for more information.