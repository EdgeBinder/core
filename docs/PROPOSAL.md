# EdgeBinder Library Proposal

## Executive Summary

A proposal for creating an open-source PHP library called **EdgeBinder** that provides lightweight, storage-agnostic relationship management for clean domain architectures. This library would fill a significant gap in the PHP ecosystem between overly complex ORMs and overly simplistic pivot table solutions.

## Problem Statement

### Current Landscape
- **Doctrine ORM**: Too heavy, database-centric, invasive to domain entities
- **Laravel Eloquent**: Framework-tied, Active Record pattern conflicts with DDD
- **Basic Pivot Solutions**: Too simplistic, lack metadata support and flexibility
- **Custom Solutions**: Everyone reinvents the wheel

### The Gap
There is no lightweight, framework-agnostic library for managing entity relationships with rich metadata support that works with modern architectures (DDD, microservices, vector databases).

## Proposed Solution: EdgeBinder

### Core Value Proposition
*"Bind entities with rich, metadata-driven relationships using storage-agnostic flexibility"*

### Key Features

#### 1. Simple, Intuitive API
```php
// Bind entities with metadata
$binder->bind(
    from: $workspace,
    to: $codeRepository,
    type: 'has_access',
    metadata: [
        'access_level' => 'write',
        'granted_by' => $userId,
        'granted_at' => new DateTimeImmutable(),
        'expires_at' => null
    ]
);

// Query bindings
$repositories = $binder->query()
    ->from($workspace)
    ->type('has_access')
    ->where('access_level', 'write')
    ->get();

// Update metadata
$binder->updateMetadata($bindingId, [
    'access_level' => 'read',
    'modified_by' => $adminId
]);
```

#### 2. Storage Adapters (Separate Packages)

**Core Package** (`edgebinder/edgebinder`):
- **InMemoryAdapter** - Built-in for testing and development

**Official Adapter Packages**:
- **`edgebinder/pdo-adapter`** - SQL databases (MySQL, PostgreSQL, SQLite)
- **`edgebinder/mongodb-adapter`** - MongoDB with rich document metadata
- **`edgebinder/redis-adapter`** - Redis with graph modules and sorted sets
- **`edgebinder/weaviate-adapter`** - Weaviate vector database with metadata
- **`edgebinder/janusgraph-adapter`** - JanusGraph for large-scale graphs
- **`edgebinder/neo4j-adapter`** - Neo4j native graph relationships
- **`edgebinder/arangodb-adapter`** - ArangoDB multi-model support

**Community Adapter Packages**:
- **`edgebinder/pinecone-adapter`** - Pinecone vector relationships
- **`edgebinder/chroma-adapter`** - Chroma document embeddings
- **`edgebinder/qdrant-adapter`** - Qdrant payload-rich vectors
- **`edgebinder/orientdb-adapter`** - OrientDB document-graph hybrid

#### 3. Rich Edge Metadata (Graph/Vector Database Focus)
```php
// Complex relationship metadata for graph/vector scenarios
$relations->relate($codeRepo, $workspace, 'belongs_to', [
    // Vector/AI metadata
    'embedding_similarity' => 0.95,
    'semantic_distance' => 0.12,
    'vector_version' => 'v2.1',
    'confidence_score' => 0.87,

    // Graph metadata
    'weight' => 0.8,
    'direction' => 'bidirectional',
    'strength' => 'strong',

    // Business metadata
    'access_level' => 'read',
    'granted_by' => $userId,
    'granted_at' => new DateTimeImmutable(),
    'expires_at' => null,
    'tags' => ['production', 'critical'],

    // Custom properties
    'custom_properties' => [
        'department' => 'engineering',
        'project_phase' => 'development'
    ]
]);

// Query by metadata (crucial for vector/graph operations)
$similarRepos = $relations->query()
    ->from($workspace)
    ->type('belongs_to')
    ->where('embedding_similarity', '>', 0.9)
    ->where('access_level', 'write')
    ->orderBy('confidence_score', 'desc')
    ->limit(10)
    ->get();

// Update edge properties (common in graph databases)
$relations->updateMetadata($relationshipId, [
    'embedding_similarity' => 0.97,  // Updated after reprocessing
    'last_accessed' => new DateTimeImmutable(),
    'access_count' => $currentCount + 1
]);
```

#### 4. Architecture Benefits
- **Clean Entities**: No relationship pollution in domain objects
- **Storage Flexibility**: Switch backends without code changes
- **Framework Agnostic**: Works with any PHP project
- **Performance**: Optimized for relationship queries
- **Type Safety**: Strong typing with metadata validation
- **Event System**: Optional events for relationship changes
- **Graph-Native**: Designed for vector/graph database patterns

## Library Structure

### Core Library (`edgebinder/edgebinder`)
```
edgebinder/
├── src/
│   ├── EdgeBinder.php
│   ├── Contracts/
│   │   ├── EdgeBinderInterface.php
│   │   ├── PersistenceAdapterInterface.php
│   │   ├── BindingInterface.php
│   │   └── QueryBuilderInterface.php
│   ├── Storage/
│   │   └── InMemoryAdapter.php  // Only for testing/development
│   ├── Query/
│   │   └── BindingQueryBuilder.php
│   └── Events/
│       ├── BindingCreated.php
│       └── BindingDeleted.php
├── tests/
├── docs/
└── examples/
```

### Separate Adapter Repositories
- **`edgebinder/pdo-adapter`** - SQL databases (MySQL, PostgreSQL, SQLite)
- **`edgebinder/mongodb-adapter`** - MongoDB document storage
- **`edgebinder/redis-adapter`** - Redis key-value storage
- **`edgebinder/weaviate-adapter`** - Weaviate vector database
- **`edgebinder/janusgraph-adapter`** - JanusGraph graph database
- **`edgebinder/neo4j-adapter`** - Neo4j graph database
- **`edgebinder/arangodb-adapter`** - ArangoDB multi-model database

## Metadata-First Design for Graph/Vector Databases

### Edge Properties as First-Class Citizens

In graph and vector databases, the **relationships (edges) are as important as the entities (nodes)**. This library treats metadata not as an afterthought, but as the core of relationship management:

```php
// Metadata types for different use cases
interface MetadataInterface
{
    public function getVectorProperties(): array;    // Embeddings, similarities, distances
    public function getGraphProperties(): array;     // Weights, directions, strengths
    public function getBusinessProperties(): array;  // Access, permissions, timestamps
    public function getCustomProperties(): array;    // Domain-specific data
}

// Rich metadata support
class RelationshipMetadata implements MetadataInterface
{
    public function __construct(
        private array $vectorProperties = [],
        private array $graphProperties = [],
        private array $businessProperties = [],
        private array $customProperties = []
    ) {}

    // Fluent interface for building metadata
    public function withVector(string $key, mixed $value): self;
    public function withGraph(string $key, mixed $value): self;
    public function withBusiness(string $key, mixed $value): self;
    public function withCustom(string $key, mixed $value): self;
}
```

### Vector Database Integration Examples

```php
// Semantic similarity relationships
$relations->relate($document1, $document2, 'semantically_similar', [
    'cosine_similarity' => 0.94,
    'euclidean_distance' => 0.23,
    'embedding_model' => 'text-embedding-ada-002',
    'computed_at' => new DateTimeImmutable(),
    'confidence_threshold' => 0.9
]);

// Code repository relationships with vector metadata
$relations->relate($codeRepo, $workspace, 'belongs_to', [
    'code_similarity' => 0.87,
    'language_match' => 'php',
    'framework_compatibility' => ['symfony', 'laravel'],
    'complexity_score' => 7.2,
    'maintainability_index' => 85
]);

// Query by vector properties
$similarCode = $relations->query()
    ->type('semantically_similar')
    ->where('cosine_similarity', '>=', 0.9)
    ->where('language_match', 'php')
    ->orderBy('code_similarity', 'desc')
    ->get();
```

### Graph Traversal with Metadata Filtering

```php
// Find paths with specific edge properties
$path = $relations->traverse()
    ->from($startNode)
    ->to($endNode)
    ->through('belongs_to', 'has_access')
    ->where('access_level', 'write')
    ->where('weight', '>', 0.5)
    ->maxDepth(3)
    ->findPath();

// Aggregate metadata across relationships
$stats = $relations->aggregate()
    ->from($workspace)
    ->type('has_access')
    ->sum('access_count')
    ->avg('confidence_score')
    ->max('last_accessed')
    ->get();
```

## Core Interfaces

### EdgeBinderInterface
```php
interface EdgeBinderInterface
{
    public function bind(
        EntityInterface $from,
        EntityInterface $to,
        string $type,
        array $metadata = []
    ): BindingInterface;

    public function unbind(string $bindingId): void;

    public function query(): QueryBuilderInterface;

    public function updateMetadata(string $bindingId, array $metadata): void;

    public function getMetadata(string $bindingId): array;
}
```

### PersistenceAdapterInterface
```php
interface PersistenceAdapterInterface
{
    public function store(BindingInterface $binding): void;

    public function find(string $bindingId): ?BindingInterface;

    public function findByEntity(string $entityType, string $entityId): array;

    public function delete(string $bindingId): void;

    public function updateMetadata(string $bindingId, array $metadata): void;
}
```

## Market Analysis

### Why This Would Succeed

1. **Real Need**: Many developers struggle with this exact problem
2. **Clean Solution**: Fills gap between "too simple" and "too complex"
3. **Modern Architecture**: Supports current PHP best practices (DDD, clean architecture)
4. **Vector DB Ready**: Perfect timing with AI/vector database adoption
5. **Framework Neutral**: Appeals to broader PHP community
6. **No Competition**: No existing standalone solution

### Target Audience

- **Domain-Driven Design practitioners**
- **Microservice architects**
- **Developers using vector databases**
- **Teams wanting clean, testable code**
- **Framework-agnostic PHP developers**

## Use Cases

### 1. Multi-Tenant Applications
```php
// Organization has many Workspaces with different access levels
$relations->relate($organization, $workspace, 'owns', [
    'access_level' => 'admin',
    'created_by' => $userId
]);
```

### 2. Content Management
```php
// User has access to Documents with permissions
$relations->relate($user, $document, 'can_access', [
    'permissions' => ['read', 'write'],
    'granted_at' => new DateTimeImmutable()
]);
```

### 3. Vector Database Integration
```php
// Code Repository belongs to multiple Workspaces (stored in vector DB)
$relations->relate($codeRepo, $workspace, 'belongs_to', [
    'vector_similarity' => 0.95,
    'embedding_version' => 'v2.1',
    'semantic_distance' => 0.12,
    'confidence_score' => 0.87
]);
```

### 4. AI/ML Relationship Discovery
```php
// Automatically discovered relationships from embeddings
$relations->relate($document1, $document2, 'semantically_related', [
    'discovery_method' => 'cosine_similarity',
    'similarity_score' => 0.92,
    'embedding_model' => 'text-embedding-ada-002',
    'discovered_at' => new DateTimeImmutable(),
    'human_verified' => false,
    'topics' => ['machine_learning', 'php', 'databases']
]);

// Query for AI-discovered relationships
$relatedDocs = $relations->query()
    ->from($currentDocument)
    ->type('semantically_related')
    ->where('similarity_score', '>', 0.9)
    ->where('human_verified', true)
    ->orderBy('similarity_score', 'desc')
    ->limit(5)
    ->get();
```

### 5. Graph Analytics with Metadata
```php
// Complex graph queries with metadata filtering
$influentialNodes = $relations->query()
    ->type('influences')
    ->where('influence_strength', '>', 0.8)
    ->where('verified', true)
    ->groupBy('from_entity')
    ->having('count', '>', 10)
    ->orderBy('avg_influence_strength', 'desc')
    ->get();

// Shortest path with metadata constraints
$path = $relations->findPath($startNode, $endNode)
    ->through(['collaborates_with', 'works_on'])
    ->where('relationship_strength', '>', 0.5)
    ->where('active', true)
    ->maxDepth(4)
    ->get();
```

## Implementation Roadmap

### Phase 1: Core Foundation (4-6 weeks)
- [ ] Design core interfaces (`EdgeBinderInterface`, `PersistenceAdapterInterface`, etc.)
- [ ] Implement core `EdgeBinder` class
- [ ] Create `InMemoryAdapter` for testing
- [ ] Basic query builder (`BindingQueryBuilder`)
- [ ] Comprehensive test suite for core
- [ ] Publish `edgebinder/edgebinder` package

### Phase 2: Essential Adapters (4-6 weeks)
- [ ] `edgebinder/pdo-adapter` - SQL database support
- [ ] `edgebinder/mongodb-adapter` - Document storage
- [ ] Event system in core
- [ ] Performance optimizations
- [ ] Bulk operations support

### Phase 3: Specialized Adapters (4-6 weeks)
- [ ] `edgebinder/weaviate-adapter` - Vector database support
- [ ] `edgebinder/janusgraph-adapter` - Graph database support
- [ ] `edgebinder/redis-adapter` - Key-value storage
- [ ] Documentation website
- [ ] Example applications

### Phase 4: Ecosystem Growth (Ongoing)
- [ ] `edgebinder/neo4j-adapter` - Additional graph support
- [ ] `edgebinder/arangodb-adapter` - Multi-model support
- [ ] Community adapter contributions
- [ ] Framework integration packages
- [ ] Performance benchmarks

## Technical Considerations

### Dependencies
- **Minimal**: Only PSR interfaces (PSR-3 Logger, PSR-14 Events)
- **PHP 8.3+**: Latest PHP features (typed class constants, readonly classes, etc.)
- **No Framework Dependencies**: Truly standalone

### Performance
- **Lazy Loading**: Relationships loaded on demand
- **Bulk Operations**: Efficient batch processing
- **Caching**: Built-in caching support
- **Indexing**: Optimized storage patterns

### Testing
- **100% Code Coverage**: Comprehensive test suite
- **PHP 8.3+ Only**: Focus on latest stable PHP versions
- **Multiple Storage Backends**: All adapters tested
- **Performance Tests**: Benchmarking suite

## Package Ecosystem

### Core Package
- **`edgebinder/edgebinder`** - Main EdgeBinder library with interfaces and InMemoryAdapter

### Official Adapter Packages
- **`edgebinder/pdo-adapter`** - SQL database support
- **`edgebinder/mongodb-adapter`** - MongoDB document storage
- **`edgebinder/redis-adapter`** - Redis key-value storage
- **`edgebinder/weaviate-adapter`** - Weaviate vector database
- **`edgebinder/janusgraph-adapter`** - JanusGraph graph database
- **`edgebinder/neo4j-adapter`** - Neo4j graph database
- **`edgebinder/arangodb-adapter`** - ArangoDB multi-model

### Installation Examples
```bash
# Core library only (with InMemoryAdapter)
composer require edgebinder/edgebinder

# With SQL support
composer require edgebinder/edgebinder edgebinder/pdo-adapter

# With vector database support
composer require edgebinder/edgebinder edgebinder/weaviate-adapter

# With graph database support
composer require edgebinder/edgebinder edgebinder/janusgraph-adapter
```

## Next Steps

1. **Validate Concept**: Build proof of concept with basic features
2. **Community Feedback**: Share with PHP community for input
3. **Core Development**: Implement Phase 1 features
4. **Documentation**: Create comprehensive docs and examples
5. **Launch**: Publish to Packagist and promote

## Conclusion

This library addresses a real need in the PHP ecosystem and has the potential to become a standard tool for developers building clean, maintainable applications. The timing is perfect with the growing adoption of DDD, microservices, and modern data architectures.

The combination of simplicity, flexibility, and power would make this an invaluable tool for the PHP community.
