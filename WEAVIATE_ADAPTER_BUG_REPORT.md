# Weaviate Adapter Bug Report: Anonymous Class Entity Handling

## Summary
The Weaviate adapter fails to properly handle anonymous class entities in query operations, causing queries to return empty results even when relationships exist. This affects both `query()` and `findBindingsFor()` methods when used with anonymous class entities.

## Environment
- **EdgeBinder Version**: Current (post v0.7.0 fix)
- **PHP Version**: 8.4+
- **Adapter**: Weaviate
- **Issue Scope**: Anonymous class entities only (regular entities work fine)

## Bug Description
When using anonymous class entities (common in testing scenarios), the Weaviate adapter fails to execute queries correctly. Relationships are created successfully, but subsequent queries return empty results.

**Key Finding**: This bug is **anonymous class specific**. Regular entities implementing `EntityInterface` work correctly.

## Root Cause Analysis
Anonymous class entities have unpredictable type names like:
```
class@anonymous /path/to/file.php:42$abc123
```

The Weaviate adapter likely has issues with:
1. **Entity type extraction** from anonymous classes
2. **Query filtering** using unpredictable type names
3. **Type matching** between stored and queried entities

## Evidence from AbstractAdapterTestSuite

### ✅ InMemory Adapter: PASSES
```bash
./vendor/bin/phpunit tests/Integration/Persistence/InMemory/InMemoryAdapterTest.php --filter "testAnonymousClass"
# Result: 3/3 tests pass, 34 assertions successful
```

### ❌ Weaviate Adapter: Expected to FAIL
```bash
./vendor/bin/phpunit WeaviateAdapterTest.php --filter "testAnonymousClass"
# Expected: Test failures with empty query results
```

## Test Cases That Should Fail

### Test 1: Basic Query Operations
```php
public function testAnonymousClassEntitiesWithQuery(): void
{
    $user = new class('user-test', 'Test User') {
        public function __construct(private string $id, private string $name) {}
        public function getId(): string { return $this->id; }
        public function getName(): string { return $this->name; }
    };

    $project = new class('project-test', 'Test Project') {
        public function __construct(private string $id, private string $name) {}
        public function getId(): string { return $this->id; }
        public function getName(): string { return $this->name; }
    };

    // This should work
    $binding = $edgeBinder->bind($user, $project, 'has_access');

    // These should FAIL with Weaviate adapter
    $result1 = $edgeBinder->query()->from($user)->get();
    $result2 = $edgeBinder->query()->type('has_access')->get();
    $result3 = $edgeBinder->query()->from($user)->type('has_access')->get();

    // Expected: Empty results instead of 1 binding
}
```

### Test 2: findBindingsFor Method
```php
public function testAnonymousClassEntitiesWithFindBindingsFor(): void
{
    $user = new class('user-find', 'Find User') { /* ... */ };
    $project = new class('project-find', 'Find Project') { /* ... */ };

    $binding = $edgeBinder->bind($user, $project, 'owns');

    // This should FAIL with Weaviate adapter
    $userBindings = $edgeBinder->findBindingsFor($user);

    // Expected: Empty array instead of 1 binding
}
```

## Debugging Steps

### 1. Run the New Tests
```bash
# Test with InMemory (should pass)
./vendor/bin/phpunit tests/Integration/Persistence/InMemory/InMemoryAdapterTest.php --filter "testAnonymousClass"

# Test with Weaviate (should fail)
./vendor/bin/phpunit WeaviateAdapterTest.php --filter "testAnonymousClass"
```

### 2. Compare Entity Type Extraction
```php
// Debug entity type extraction
$adapter = new WeaviateAdapter(/* config */);
$anonymousEntity = new class('test-id') {
    public function getId(): string { return 'test-id'; }
};

$extractedType = $adapter->extractEntityType($anonymousEntity);
echo "Extracted type: '{$extractedType}'\n";
// Expected output: "class@anonymous /path/to/file.php:42$abc123"
```

### 3. Check Query Criteria Transformation
```php
// Debug query criteria
$queryBuilder = $edgeBinder->query()->from($anonymousEntity);
$criteria = $queryBuilder->getCriteria();

echo "Query criteria:\n";
echo "  - fromType: " . ($criteria['fromType'] ?? 'NOT SET') . "\n";
echo "  - fromId: " . ($criteria['fromId'] ?? 'NOT SET') . "\n";
```

## Expected Fix Areas

### 1. Entity Type Handling
The Weaviate adapter may need to:
- Properly extract anonymous class type names
- Handle special characters in type names (@ symbols, paths, etc.)
- Ensure consistent type name usage between storage and querying

### 2. Query Translation
The Weaviate query transformer may need to:
- Properly escape or encode anonymous class type names
- Handle unpredictable type names in WHERE clauses
- Ensure type matching works with complex type names

### 3. Collection/Schema Management
The Weaviate adapter may need to:
- Handle dynamic type names in schema creation
- Properly index entities with unpredictable type names
- Ensure query performance with complex type names

## Impact
- **Testing**: Anonymous class entities are commonly used in tests
- **Development**: Affects development workflows using anonymous entities
- **Reliability**: Silent failures make debugging difficult
- **Consistency**: InMemory adapter works, Weaviate doesn't

## Workaround
Until fixed, use regular entities implementing `EntityInterface` instead of anonymous classes:

```php
// Instead of anonymous class
$user = new class('123') { /* ... */ };

// Use regular entity
class TestUser implements EntityInterface {
    public function __construct(private string $id) {}
    public function getId(): string { return $this->id; }
    public function getType(): string { return 'TestUser'; }
}
$user = new TestUser('123');
```

## Next Steps
1. **Confirm the bug** by running the new AbstractAdapterTestSuite tests
2. **Debug the Weaviate adapter** entity type extraction and query logic
3. **Fix the adapter** to handle anonymous class type names properly
4. **Verify the fix** by ensuring all anonymous class tests pass
5. **Update documentation** about anonymous class entity support

## Test Integration
The new anonymous class tests are now part of the mandatory `AbstractAdapterTestSuite`. All adapters must pass these tests for certification.

**Status**: 🔍 Investigation needed - run tests to confirm bug exists