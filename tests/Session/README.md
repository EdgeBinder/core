# Session Integration Tests

This directory contains comprehensive tests for EdgeBinder's session-based consistency functionality, designed to validate the solution to the database timing issues identified in `DATABASE_TIMING_ISSUE.md`.

## Test Structure

### Unit Tests (`tests/Unit/Session/`)

**BindingCacheTest.php**
- Tests the in-memory cache component that provides immediate consistency
- Validates indexing performance and memory efficiency
- Tests cache operations: store, retrieve, query, clear, remove
- Verifies index consistency after operations

**SessionAwareQueryBuilderTest.php**
- Tests the query builder that merges cache and adapter results
- Validates result deduplication and prioritization
- Tests query building and criteria handling
- Verifies count, first, and get operations

### Integration Tests (`tests/Integration/Session/`)

**SessionIntegrationTest.php**
- Core integration tests for session functionality
- Tests the exact scenarios from `DATABASE_TIMING_ISSUE.md`
- Validates read-after-write consistency
- Tests session isolation, flush behavior, and scoping
- Verifies backward compatibility

**SessionConsistencyTest.php**
- Focused tests on consistency guarantees
- Tests rapid operation sequences that cause race conditions
- Validates complex query scenarios
- Tests auto-flush and manual flush behavior
- Includes the `isOrganizationMember` pattern from the original issue

**SessionPerformanceTest.php**
- Performance validation for session operations
- Tests with large datasets (1000+ bindings)
- Memory usage and efficiency tests
- Concurrent session performance
- Query merging performance validation

## Running Session Tests

### Run All Session Tests
```bash
vendor/bin/phpunit -c phpunit-session.xml
```

### Run Specific Test Suites
```bash
# Unit tests only
vendor/bin/phpunit -c phpunit-session.xml --testsuite session-unit

# Integration tests only
vendor/bin/phpunit -c phpunit-session.xml --testsuite session-integration

# All session tests
vendor/bin/phpunit -c phpunit-session.xml --testsuite session-all
```

### Run Individual Test Files
```bash
# Core integration tests
vendor/bin/phpunit tests/Integration/Session/SessionIntegrationTest.php

# Consistency tests
vendor/bin/phpunit tests/Integration/Session/SessionConsistencyTest.php

# Performance tests
vendor/bin/phpunit tests/Integration/Session/SessionPerformanceTest.php

# Cache unit tests
vendor/bin/phpunit tests/Unit/Session/BindingCacheTest.php

# Query builder unit tests
vendor/bin/phpunit tests/Unit/Session/SessionAwareQueryBuilderTest.php
```

## Test Coverage

### Core Functionality Coverage
- ✅ **Read-after-write consistency** - Primary issue from `DATABASE_TIMING_ISSUE.md`
- ✅ **Session isolation** - Different sessions don't interfere
- ✅ **Result merging** - Cache + adapter results combined correctly
- ✅ **Deduplication** - No duplicate results from cache/adapter overlap
- ✅ **Flush behavior** - Manual and auto-flush consistency
- ✅ **Session scoping** - Callback-based session management
- ✅ **Backward compatibility** - Existing code continues to work

### Performance Coverage
- ✅ **Large dataset handling** - 1000+ bindings performance
- ✅ **Memory efficiency** - Reasonable memory usage and cleanup
- ✅ **Query performance** - Fast queries with proper indexing
- ✅ **Concurrent sessions** - Multiple sessions performance
- ✅ **Complex relationships** - Multi-entity relationship patterns

### Edge Case Coverage
- ✅ **Rapid operation sequences** - Race condition scenarios
- ✅ **Complex query patterns** - Multi-criteria queries
- ✅ **Empty result sets** - Queries with no matches
- ✅ **Cache consistency** - Index updates after operations
- ✅ **Session state management** - Dirty state, pending operations

## Key Test Scenarios

### 1. Database Timing Issue Reproduction
```php
// The exact failing pattern from DATABASE_TIMING_ISSUE.md
$session = $edgeBinder->createSession();
$binding = $session->bind(from: $profile, to: $org, type: 'member_of');
$result = $session->query()->from($profile)->type('member_of')->get();
// ✅ Should find 1 binding immediately
```

### 2. Complex Query Scenarios
```php
// Multi-entity queries that were failing
$allMembers = $session->query()->to($org)->type('member_of')->get();
$adminRelations = $session->query()->to($org)->type('admin_of')->get();
$specificRelation = $session->query()
    ->from($user)->to($org)->type('member_of')->get();
```

### 3. Rapid Operation Sequences
```php
// Create-then-query patterns that cause race conditions
for ($i = 0; $i < 20; $i++) {
    $binding = $session->bind(...);
    $result = $session->query()->get(); // Should always find binding
}
```

### 4. Session Isolation
```php
$session1 = $edgeBinder->createSession();
$session2 = $edgeBinder->createSession();

$session1->bind(...); // Session2 should not see this
$session1->flush();   // Now session2 should see it
```

## Performance Benchmarks

The performance tests validate:

- **Binding creation**: < 1.0 second for 1000 bindings
- **Query performance**: < 0.1 second for 100 queries on large datasets
- **Memory usage**: < 10MB for 1000 bindings
- **Flush operations**: < 0.5 second for 500 bindings
- **Result merging**: < 0.5 second for 500 cache + 500 adapter results

## Test Data Patterns

Tests use realistic entity patterns:
- **Users** and **Organizations** with membership relationships
- **Teams** and **Projects** with assignment relationships
- **Complex hierarchies** with multiple relationship types
- **Large datasets** for performance validation

## Assertions and Validations

### Consistency Assertions
- Immediate read-after-write within sessions
- Proper isolation between sessions
- Correct result merging without duplicates
- Cache prioritization over adapter results

### Performance Assertions
- Query response times under thresholds
- Memory usage within reasonable bounds
- Concurrent operation efficiency
- Index-based query optimization

### Correctness Assertions
- Exact binding counts and IDs
- Proper entity relationship mapping
- Metadata preservation and retrieval
- State management accuracy

## Integration with Existing Tests

These session tests complement the existing test structure:

- **AbstractAdapterTestSuite**: Session tests use the same patterns for consistency
- **InMemoryAdapterTest**: Sessions work with existing InMemory adapter
- **EdgeBinderTest**: Session functionality integrates with core EdgeBinder
- **Performance alignment**: Session performance meets EdgeBinder standards

## Future Test Enhancements

Potential additions as session functionality evolves:

1. **Persistent adapter integration** - Test sessions with Weaviate, JanusGraph
2. **Nested session support** - Test session hierarchies and scoping
3. **Transaction-like behavior** - Test rollback and commit semantics
4. **Concurrent access patterns** - Test thread-safety and race conditions
5. **Memory pressure testing** - Test behavior under memory constraints

## Test Maintenance

### Adding New Tests
1. Follow existing naming conventions (`test*` methods)
2. Use consistent entity creation patterns
3. Include performance assertions where relevant
4. Document complex test scenarios

### Updating Tests
1. Maintain backward compatibility validation
2. Update performance thresholds as needed
3. Keep test data patterns realistic
4. Ensure comprehensive edge case coverage

This test suite provides comprehensive validation that the session-based approach successfully resolves the database timing and consistency issues identified in the original problem report.
