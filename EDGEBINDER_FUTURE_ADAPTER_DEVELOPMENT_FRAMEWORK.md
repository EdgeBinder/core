# EdgeBinder Future Adapter Development Framework - Phase 3

## Executive Summary

This document outlines the future development framework for EdgeBinder adapters, including project templates, CLI tooling, and standardized development processes. This phase should be implemented when we begin work on the next adapter to ensure consistent, high-quality development patterns.

## Prerequisites

**This phase depends on completion of Phase 1 & 2:**
- ✅ `AbstractAdapterTestSuite` extracted from InMemoryAdapter
- ✅ WeaviateAdapter fixed to pass all standard tests
- ✅ Proven testing standard established and validated

## Phase 3: Development Framework (Future Implementation)

### **When to Implement**

- **Trigger**: When we start development of the next adapter (Redis, MongoDB, etc.)
- **Timeline**: 2-3 weeks before new adapter development begins
- **Goal**: Make new adapter development fast, reliable, and consistent

### **Components to Develop**

#### **3.1: Adapter Project Template**

**Location**: `edgebinder/adapter-template/`

```
edgebinder-{storage}-adapter/
├── src/
│   ├── {Storage}Adapter.php                    # Main adapter implementation
│   ├── {Storage}AdapterFactory.php             # Factory for dependency injection
│   ├── Exception/
│   │   └── {Storage}Exception.php              # Storage-specific exceptions
│   └── Query/
│       └── {Storage}QueryBuilder.php           # Optional: storage-specific query builder
├── tests/
│   ├── Integration/
│   │   └── {Storage}AdapterIntegrationTest.php # Extends AbstractAdapterTestSuite
│   └── Unit/
│       ├── {Storage}AdapterTest.php            # Unit tests for adapter
│       └── {Storage}AdapterFactoryTest.php     # Unit tests for factory
├── config/
│   └── services.yaml                           # Service configuration
├── docs/
│   ├── README.md                               # Usage documentation
│   ├── INSTALLATION.md                         # Installation guide
│   └── CONFIGURATION.md                        # Configuration options
├── .github/
│   └── workflows/
│       └── test.yml                            # CI/CD configuration
├── composer.json                               # Dependencies and autoloading
├── phpunit.xml                                 # Test configuration
├── .php-cs-fixer.dist.php                     # Code style configuration
├── phpstan.neon                                # Static analysis configuration
└── LICENSE                                     # License file
```

#### **3.2: CLI Scaffolding Tool**

**Location**: `edgebinder/edgebinder/bin/create-adapter`

```bash
#!/usr/bin/env php
<?php

/**
 * EdgeBinder Adapter Generator
 * 
 * Usage: ./bin/create-adapter <storage-type> <AdapterClass>
 * Example: ./bin/create-adapter redis RedisAdapter
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EdgeBinder\Tools\AdapterGenerator;

$storageType = $argv[1] ?? null;
$adapterClass = $argv[2] ?? null;

if (!$storageType || !$adapterClass) {
    echo "Usage: create-adapter <storage-type> <AdapterClass>\n";
    echo "Example: create-adapter redis RedisAdapter\n";
    echo "\n";
    echo "This will create:\n";
    echo "  - Complete adapter project structure\n";
    echo "  - Integration tests extending AbstractAdapterTestSuite\n";
    echo "  - CI/CD configuration\n";
    echo "  - Documentation templates\n";
    exit(1);
}

$generator = new AdapterGenerator($storageType, $adapterClass);
$projectPath = $generator->generate();

echo "✅ Adapter project created: {$projectPath}\n";
echo "\n";
echo "📝 Next steps:\n";
echo "   1. cd {$projectPath}\n";
echo "   2. composer install\n";
echo "   3. Implement {$adapterClass}::store(), find(), delete(), executeQuery()\n";
echo "   4. Implement {$adapterClass}IntegrationTest::createAdapter()\n";
echo "   5. Run: composer test\n";
echo "   6. All AbstractAdapterTestSuite tests must pass!\n";
echo "\n";
echo "📋 Quality gates:\n";
echo "   - All 29 integration tests must pass\n";
echo "   - Code coverage > 90%\n";
echo "   - PHPStan level 8 clean\n";
echo "   - PHP-CS-Fixer compliant\n";
```

#### **3.3: Integration Test Template**

**Location**: `edgebinder/adapter-template/tests/Integration/{Storage}AdapterIntegrationTest.php`

```php
<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\{Storage}\Tests\Integration;

use EdgeBinder\Adapter\{Storage}\{Storage}Adapter;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Testing\AbstractAdapterTestSuite;

/**
 * Integration tests for {Storage}Adapter.
 * 
 * This class extends AbstractAdapterTestSuite to ensure consistent
 * behavior with all other EdgeBinder adapters. All 29 comprehensive
 * tests from InMemoryAdapter are automatically included.
 */
class {Storage}AdapterIntegrationTest extends AbstractAdapterTestSuite
{
    private {StorageClient} $client;
    private string $testDatabase;
    
    protected function createAdapter(): PersistenceAdapterInterface
    {
        // TODO: Implement adapter creation for {Storage}
        // 
        // Example implementation:
        // $this->client = new {StorageClient}([
        //     'host' => 'localhost',
        //     'port' => 6379,
        //     'database' => $this->testDatabase = 'test_' . uniqid()
        // ]);
        // 
        // return new {Storage}Adapter($this->client, [
        //     'prefix' => 'edgebinder_test_',
        //     'ttl' => 3600
        // ]);
        
        throw new \RuntimeException('TODO: Implement createAdapter() method');
    }
    
    protected function cleanupAdapter(): void
    {
        // TODO: Implement cleanup for {Storage}
        // 
        // Example implementation:
        // if (isset($this->client) && isset($this->testDatabase)) {
        //     try {
        //         $this->client->flushdb($this->testDatabase);
        //     } catch (\Exception $e) {
        //         // Ignore cleanup errors
        //     }
        // }
    }
    
    // Optional: Add storage-specific tests
    public function test{Storage}SpecificFeature(): void
    {
        // Test features specific to this storage backend
        // that are not covered by AbstractAdapterTestSuite
        
        $this->markTestIncomplete('Implement storage-specific tests if needed');
    }
}
```

#### **3.4: CI/CD Template**

**Location**: `edgebinder/adapter-template/.github/workflows/test.yml`

```yaml
name: EdgeBinder {Storage} Adapter Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      {storage}:
        image: {storage}:latest
        ports:
          - {port}:{port}
        options: >-
          --health-cmd="{health-check-command}"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
    
    strategy:
      matrix:
        php-version: ['8.3', '8.4']
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mbstring, xml, ctype, json, {storage-extension}
          coverage: xdebug
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Wait for {Storage}
        run: |
          timeout 30 bash -c 'until nc -z localhost {port}; do sleep 1; done'
        
      - name: Run AbstractAdapterTestSuite (Critical)
        run: vendor/bin/phpunit tests/Integration/ --coverage-clover=coverage.xml
        
      - name: Run unit tests
        run: vendor/bin/phpunit tests/Unit/
        
      - name: Check code style
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
        
      - name: Run static analysis
        run: vendor/bin/phpstan analyse --level=8
        
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
```

### **Development Workflow**

#### **Creating a New Adapter**

```bash
# 1. Generate project structure
./vendor/bin/create-adapter mongodb MongoDBAdapter

# 2. Set up development environment
cd edgebinder-mongodb-adapter
composer install

# 3. Implement core adapter methods
# Edit src/MongoDBAdapter.php:
#   - implement store(BindingInterface $binding): void
#   - implement find(array $criteria): array
#   - implement delete(string $id): void
#   - implement executeQuery(QueryBuilderInterface $query): array

# 4. Implement test setup
# Edit tests/Integration/MongoDBAdapterIntegrationTest.php:
#   - implement createAdapter(): PersistenceAdapterInterface
#   - implement cleanupAdapter(): void

# 5. Run tests iteratively
composer test

# 6. Fix implementation until all tests pass
# All 29 AbstractAdapterTestSuite tests must pass!

# 7. Add storage-specific tests if needed
# 8. Ensure code quality gates pass
# 9. Create documentation
# 10. Submit for review
```

### **Quality Gates**

#### **Before Release**
- ✅ All 29 AbstractAdapterTestSuite tests pass
- ✅ Code coverage > 90%
- ✅ PHPStan level 8 clean
- ✅ PHP-CS-Fixer compliant
- ✅ Documentation complete
- ✅ CI/CD pipeline green

#### **Cross-Adapter Consistency**
- ✅ Identical behavior to InMemoryAdapter for all standard tests
- ✅ Performance benchmarks within acceptable range
- ✅ Error handling consistent with other adapters
- ✅ Configuration patterns follow established conventions

### **Documentation Templates**

#### **README.md Template**
```markdown
# EdgeBinder {Storage} Adapter

Official {Storage} adapter for EdgeBinder relationship management.

## Installation

```bash
composer require edgebinder/{storage}-adapter
```

## Configuration

```php
use EdgeBinder\Adapter\{Storage}\{Storage}AdapterFactory;

$config = [
    'host' => 'localhost',
    'port' => {port},
    // ... other configuration
];

$adapter = {Storage}AdapterFactory::create($config);
$edgeBinder = new EdgeBinder($adapter);
```

## Testing

This adapter passes all 29 comprehensive tests from EdgeBinder's AbstractAdapterTestSuite, ensuring identical behavior to all other EdgeBinder adapters.

```bash
composer test
```
```

## Implementation Timeline

### **When Next Adapter is Needed**

1. **Week 1**: Implement Phase 3 framework
   - Create project template
   - Develop CLI tool
   - Set up CI/CD templates

2. **Week 2**: Validate framework
   - Test with sample adapter
   - Refine templates based on usage
   - Document development process

3. **Week 3+**: Use framework for actual adapter development
   - Generate project with CLI tool
   - Implement adapter using template
   - Verify all quality gates pass

## Benefits of Deferred Implementation

### **Immediate Focus**
- **Phase 1 & 2 address critical production bug** in WeaviateAdapter
- **Establish proven testing standard** before building tooling around it
- **Validate approach** with real adapter fixes

### **Better Framework Design**
- **Learn from Phase 1 & 2 experience** to improve templates
- **Understand real pain points** in adapter development
- **Design tooling based on actual needs** rather than assumptions

### **Resource Efficiency**
- **Don't build tooling until we need it** (next adapter development)
- **Focus current effort on fixing production issues**
- **Avoid over-engineering** before validating the approach

---

**Implementation Trigger**: Begin Phase 3 development 2-3 weeks before starting work on the next EdgeBinder adapter to ensure all tooling and templates are ready for efficient development.
