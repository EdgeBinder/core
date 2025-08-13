# EdgeBinder Adapter Testing Standard v0.8.0

## Overview

The `AbstractAdapterTestSuite` is a **mandatory testing standard** that ensures all EdgeBinder adapters behave consistently and correctly. Every adapter implementation MUST extend this test suite to guarantee 100% compliance with EdgeBinder's expected behavior.

**v0.8.0 Enhancement**: The test suite now provides **world-class coverage** with enhanced edge case testing, complex filtering scenarios, and comprehensive method coverage validation.

## Why This Standard Exists

### **Production Bug Prevention**
The AbstractAdapterTestSuite was created after discovering critical bugs in production adapters:
- Query filtering returning entire databases instead of filtered results
- Metadata validation inconsistencies between adapters
- Entity extraction edge cases causing silent failures
- Complex query scenarios failing in subtle ways

### **Proven Bug Detection**
During development, the AbstractAdapterTestSuite found and helped fix **5 critical bugs** in the reference InMemoryAdapter implementation, proving its effectiveness at catching real issues.

### **v0.8.0 Coverage Achievement**
The enhanced test suite now achieves **exceptional coverage**:
- **97.73% method coverage** across the entire codebase
- **99.09% line coverage** with comprehensive edge case testing
- **100% coverage** for all core classes (Query, Registry, Session, Exception classes)
- **Enhanced adapter method testing** with direct coverage of `extractEntityType()`, `validateAndNormalizeMetadata()`, `applyOrdering()`, and `filterBindings()`

## v0.8.0 Enhanced Testing Requirements

### **NEW: Enhanced Method Coverage Testing**
The v0.8.0 AbstractAdapterTestSuite now includes **direct method coverage testing** for critical adapter methods:

#### **Required Method Coverage:**
- ✅ **`extractEntityType()`** - Must handle various entity types, anonymous classes, and fallback scenarios
- ✅ **`validateAndNormalizeMetadata()`** - Must validate all data types and throw appropriate exceptions
- ✅ **`applyOrdering()`** - Must support ordering by string, numeric, datetime, and complex metadata fields
- ✅ **`filterBindings()`** - Must support all operators: `=`, `!=`, `>`, `<`, `>=`, `<=`, `in`, `not_in`, `between`, `exists`, `null`, `not_null`

#### **Enhanced Test Scenarios:**
- **Complex multi-condition filtering** with multiple WHERE clauses
- **Advanced ordering scenarios** with various data types
- **Edge case entity extraction** with anonymous classes and objects without type methods
- **Comprehensive metadata validation** with all supported data types and error conditions

### **Coverage Expectations:**
Adapters extending AbstractAdapterTestSuite should achieve:
- **≥95% method coverage** for core adapter functionality
- **≥95% line coverage** for comprehensive edge case handling
- **100% operator support** for all query filtering operations
- **Robust error handling** for all failure scenarios

## Requirements

### **MANDATORY: Extend AbstractAdapterTestSuite**

```php
<?php
namespace YourVendor\YourAdapter\Tests;

use EdgeBinder\Testing\AbstractAdapterTestSuite;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use YourVendor\YourAdapter\YourAdapter;

class YourAdapterTest extends AbstractAdapterTestSuite
{
    protected function createAdapter(): PersistenceAdapterInterface
    {
        // Setup your adapter with test configuration
        $client = $this->setupTestClient();
        return new YourAdapter($client, $this->getTestConfig());
    }

    protected function cleanupAdapter(): void
    {
        // Clean up test data and connections
        $this->teardownTestClient();
    }

    // 60+ comprehensive tests are inherited automatically
    // Add adapter-specific tests only if needed
}
```

## What the AbstractAdapterTestSuite Tests

### **Core Functionality (100% Coverage)**
- ✅ **Store/Find/Delete operations** with all edge cases
- ✅ **Query execution** with all criteria combinations
- ✅ **Entity extraction** with all fallback scenarios
- ✅ **Metadata validation** with comprehensive edge cases

### **Query Scenarios (All Operators)**
- ✅ **Basic queries**: from(), to(), type()
- ✅ **Where conditions**: =, !=, >, <, >=, <=, in, not_in, between
- ✅ **Special operators**: exists, null, not_null
- ✅ **Complex queries**: multiple conditions, ordering, pagination
- ✅ **Edge cases**: empty results, null values, missing fields

### **Metadata Handling (All Edge Cases)**
- ✅ **Validation**: scalar types, arrays, DateTime objects, resources
- ✅ **Normalization**: deep nesting, empty arrays, null values
- ✅ **Error handling**: invalid types, too deep nesting, non-string keys

### **Entity Extraction (All Fallback Paths)**
- ✅ **EntityInterface**: getId() and getType() methods
- ✅ **Method fallbacks**: getId() and getType() on regular objects
- ✅ **Property fallbacks**: public $id property
- ✅ **Object hash fallback**: when no other method works
- ✅ **Type conversion**: non-string IDs and types

### **Error Handling (All Exception Paths)**
- ✅ **BindingNotFoundException**: missing bindings
- ✅ **InvalidMetadataException**: invalid metadata
- ✅ **PersistenceException**: storage failures
- ✅ **Edge cases**: duplicate IDs, malformed data

## Implementation Guide

### Step 1: Create Test Class

```php
<?php
namespace YourVendor\YourAdapter\Tests;

use EdgeBinder\Testing\AbstractAdapterTestSuite;
use EdgeBinder\Contracts\PersistenceAdapterInterface;

class YourAdapterTest extends AbstractAdapterTestSuite
{
    private $testClient;
    private string $testDatabase;

    protected function createAdapter(): PersistenceAdapterInterface
    {
        // Setup test environment
        $this->testClient = $this->createTestClient();
        $this->testDatabase = 'test_' . uniqid();
        
        return new YourAdapter($this->testClient, [
            'database' => $this->testDatabase,
            'timeout' => 30,
            // ... other test config
        ]);
    }

    protected function cleanupAdapter(): void
    {
        // Clean up test data
        if ($this->testClient && $this->testDatabase) {
            $this->testClient->dropDatabase($this->testDatabase);
            $this->testClient->disconnect();
        }
    }

    private function createTestClient()
    {
        // Create and configure your test client
        // Use test database/collection/namespace
        // Return configured client
    }
}
```

### Step 2: Run Tests

```bash
# Run your adapter tests
vendor/bin/phpunit tests/YourAdapterTest.php

# Expected result: All 60+ tests pass (enhanced in v0.8.0)
# If any tests fail, your adapter has compliance issues that must be fixed
```

### Step 3: Fix Compliance Issues

If tests fail, your adapter has bugs that need fixing:

1. **Query filtering issues** - Most common problem
2. **Metadata handling inconsistencies** - Second most common
3. **Entity extraction edge cases** - Less common but critical
4. **Error handling gaps** - Important for robustness

## Test Coverage Requirements

### **Minimum Coverage Standards**
- **Line Coverage**: 80%+ of your adapter code
- **Method Coverage**: 100% of public API methods
- **All AbstractAdapterTestSuite tests**: Must pass

### **Coverage Verification**
```bash
# Run with coverage
XDEBUG_MODE=coverage vendor/bin/phpunit tests/YourAdapterTest.php --coverage-text

# Check coverage report
# InMemoryAdapter achieves 84.71% line coverage as reference
```

## Common Implementation Issues

### **Query Filtering Problems**
- **Symptom**: Tests like `testExecuteQueryWithFromCriteria` fail
- **Cause**: Query builder not properly filtering results
- **Fix**: Implement proper query translation and filtering

### **Metadata Validation Issues**
- **Symptom**: Tests like `testValidateMetadataRejectsResources` fail
- **Cause**: Insufficient metadata validation
- **Fix**: Implement comprehensive metadata validation

### **Entity Extraction Issues**
- **Symptom**: Tests like `testExtractEntityIdFromEntityInterface` fail
- **Cause**: Missing fallback logic for entity ID/type extraction
- **Fix**: Implement all fallback scenarios

## Success Criteria

### **✅ Compliance Achieved When:**
1. All 57+ AbstractAdapterTestSuite tests pass
2. 80%+ line coverage of your adapter code
3. No test failures or errors
4. Consistent behavior with InMemoryAdapter reference

### **✅ Quality Indicators:**
- Tests run in under 30 seconds
- No flaky or intermittent test failures
- Clean test output with no warnings
- Proper test isolation (tests can run in any order)

## Support

### **Getting Help**
- Review the [InMemoryAdapter implementation](../src/Persistence/InMemory/InMemoryAdapter.php) as reference
- Check the [AbstractAdapterTestSuite source](../src/Testing/AbstractAdapterTestSuite.php) for test details
- See [Extensible Adapters Guide](EXTENSIBLE_ADAPTERS.md) for implementation patterns

### **Common Questions**
- **Q**: Can I skip some tests? **A**: No, all tests must pass for compliance
- **Q**: Can I modify the test suite? **A**: No, extend it but don't modify the base tests
- **Q**: What if my adapter has different behavior? **A**: Fix your adapter to match the standard

## Next Steps

1. Implement your adapter extending `PersistenceAdapterInterface`
2. Create test class extending `AbstractAdapterTestSuite`
3. Run tests and fix any failures
4. Achieve 80%+ coverage
5. Document adapter-specific features
6. Publish your adapter package

**Remember**: The AbstractAdapterTestSuite is your safety net - it ensures your adapter works correctly in all scenarios that EdgeBinder users depend on.
