# EdgeBinder Test Suite

This directory contains the comprehensive test suite for EdgeBinder, organized following standard PHPUnit conventions.

## Test Structure

### 📦 Unit Tests (`tests/Unit/`)
**196 tests** focusing on individual component behavior:

- **`Unit/BindingTest.php`** - Tests for the Binding value object
- **`Unit/EdgeBinderTest.php`** - Tests for the main EdgeBinder service
- **`Unit/Contracts/`** - Interface contract tests
- **`Unit/Exception/`** - Exception hierarchy tests  
- **`Unit/Persistence/InMemory/`** - InMemory adapter and factory tests
- **`Unit/Query/`** - Query builder tests
- **`Unit/Registry/`** - Adapter registry system tests

**Characteristics:**
- Fast execution (isolated tests)
- Mocked dependencies
- Focus on individual methods and edge cases
- Test error conditions and boundary cases

### 🔗 Integration Tests (`tests/Integration/`)
**14 tests** focusing on cross-component workflows:

- **`Integration/AdapterRegistryIntegrationTest.php`** - End-to-end adapter workflows
- **`Integration/EdgeBinderBuilderTest.php`** - Factory method integration
- **`Integration/InMemoryAdapterFactory.php`** - Test factory for integration
- **`Integration/MockAdapterFactory.php`** - Mock factory for testing

**Characteristics:**
- Real component interactions
- Actual dependencies (no mocking)
- End-to-end scenarios
- Framework integration testing

## Running Tests

### All Tests
```bash
# Run complete test suite
composer test

# Run with coverage
composer test-coverage
```

### By Test Type
```bash
# Unit tests only (fast)
vendor/bin/phpunit --testsuite="Unit Tests"

# Integration tests only
vendor/bin/phpunit --testsuite="Integration Tests"
```

### By Directory
```bash
# Run specific test directory
vendor/bin/phpunit tests/Unit/Persistence/
vendor/bin/phpunit tests/Integration/
```

### Individual Test Files
```bash
# Run specific test file
vendor/bin/phpunit tests/Unit/EdgeBinderTest.php
vendor/bin/phpunit tests/Integration/AdapterRegistryIntegrationTest.php
```

## Test Coverage

- **Total**: 210 tests, 595 assertions
- **Unit Tests**: 196 tests, 540 assertions  
- **Integration Tests**: 14 tests, 55 assertions
- **Line Coverage**: 97%+

## Test Organization Principles

1. **Clear Separation**: Unit and integration tests are clearly separated
2. **Mirror Source Structure**: Unit tests mirror the `src/` directory structure
3. **Descriptive Names**: Test methods clearly describe what they test
4. **Comprehensive Coverage**: All public methods and edge cases are tested
5. **Clean Code**: Tests are as clean and maintainable as production code

## Writing New Tests

### Unit Tests
- Place in `tests/Unit/` matching the source structure
- Use namespace `EdgeBinder\Tests\Unit\{SourceNamespace}`
- Mock external dependencies
- Focus on single component behavior

### Integration Tests  
- Place in `tests/Integration/`
- Use namespace `EdgeBinder\Tests\Integration`
- Use real dependencies
- Test component interactions

### Example Unit Test
```php
<?php
namespace EdgeBinder\Tests\Unit\Persistence\InMemory;

use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use PHPUnit\Framework\TestCase;

final class InMemoryAdapterTest extends TestCase
{
    private InMemoryAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new InMemoryAdapter();
    }

    public function testStoreAndFind(): void
    {
        // Test implementation
    }
}
```

### Example Integration Test
```php
<?php
namespace EdgeBinder\Tests\Integration;

use EdgeBinder\EdgeBinder;
use EdgeBinder\Registry\AdapterRegistry;
use PHPUnit\Framework\TestCase;

final class AdapterWorkflowTest extends TestCase
{
    public function testCompleteWorkflow(): void
    {
        // Test end-to-end workflow
    }
}
```

This structure ensures maintainable, comprehensive test coverage while following PHP testing best practices.
