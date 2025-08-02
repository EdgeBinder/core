<?php

declare(strict_types=1);

// Simple test runner for InMemoryAdapter without PHPUnit dependency
// This validates our implementation works correctly

echo "=== InMemoryAdapter Simple Test Runner ===\n\n";

// Include required files
require_once 'src/Contracts/PersistenceAdapterInterface.php';
require_once 'src/Contracts/BindingInterface.php';
require_once 'src/Contracts/EntityInterface.php';
require_once 'src/Contracts/QueryBuilderInterface.php';
require_once 'src/Exception/EdgeBinderException.php';
require_once 'src/Exception/PersistenceException.php';
require_once 'src/Exception/EntityExtractionException.php';
require_once 'src/Exception/InvalidMetadataException.php';
require_once 'src/Exception/BindingNotFoundException.php';
require_once 'src/Binding.php';
require_once 'src/Storage/InMemory/InMemoryAdapter.php';

use EdgeBinder\Storage\InMemory\InMemoryAdapter;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Binding;

// Test counter
$testCount = 0;
$passedCount = 0;

function runTest(string $name, callable $test): void {
    global $testCount, $passedCount;
    $testCount++;
    
    try {
        $test();
        echo "✓ $name\n";
        $passedCount++;
    } catch (Throwable $e) {
        echo "✗ $name: " . $e->getMessage() . "\n";
        echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

function assertEquals($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        throw new Exception($msg);
    }
}

function assertTrue($condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertNull($value, string $message = 'Expected null'): void {
    if ($value !== null) {
        throw new Exception($message . ", got " . var_export($value, true));
    }
}

function assertCount(int $expected, array $array, string $message = ''): void {
    $actual = count($array);
    if ($expected !== $actual) {
        $msg = $message ?: "Expected count $expected, got $actual";
        throw new Exception($msg);
    }
}

function assertContains($needle, array $haystack, string $message = 'Item not found in array'): void {
    if (!in_array($needle, $haystack, true)) {
        throw new Exception($message);
    }
}

// Create test entities
class TestEntity implements EntityInterface {
    public function __construct(private string $id, private string $type) {}
    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->type; }
}

class SimpleEntity {
    public function __construct(private string $id) {}
    public function getId(): string { return $this->id; }
}

class PropertyEntity {
    public string $id;
    public function __construct(string $id) { $this->id = $id; }
}

// Mock QueryBuilder for testing
class MockQueryBuilder implements QueryBuilderInterface {
    public function __construct(private array $criteria) {}
    public function getCriteria(): array { return $this->criteria; }
    
    // Stub implementations for interface compliance
    public function from(object|string $entity, ?string $entityId = null): static { return $this; }
    public function to(object|string $entity, ?string $entityId = null): static { return $this; }
    public function type(string $type): static { return $this; }
    public function where(string $field, mixed $operator, mixed $value = null): static { return $this; }
    public function whereIn(string $field, array $values): static { return $this; }
    public function whereBetween(string $field, mixed $min, mixed $max): static { return $this; }
    public function whereExists(string $field): static { return $this; }
    public function whereNull(string $field): static { return $this; }
    public function orWhere(callable $callback): static { return $this; }
    public function orderBy(string $field, string $direction = 'asc'): static { return $this; }
    public function limit(int $limit): static { return $this; }
    public function offset(int $offset): static { return $this; }
    public function get(): array { return []; }
    public function first(): ?\EdgeBinder\Contracts\BindingInterface { return null; }
    public function count(): int { return 0; }
    public function exists(): bool { return false; }
}

echo "Running tests...\n\n";

// Entity Extraction Tests
runTest('Extract entity ID from EntityInterface', function() {
    $adapter = new InMemoryAdapter();
    $entity = new TestEntity('test-123', 'TestType');
    assertEquals('test-123', $adapter->extractEntityId($entity));
});

runTest('Extract entity ID from getId method', function() {
    $adapter = new InMemoryAdapter();
    $entity = new SimpleEntity('simple-456');
    assertEquals('simple-456', $adapter->extractEntityId($entity));
});

runTest('Extract entity ID from property', function() {
    $adapter = new InMemoryAdapter();
    $entity = new PropertyEntity('prop-789');
    assertEquals('prop-789', $adapter->extractEntityId($entity));
});

runTest('Extract entity ID falls back to object hash', function() {
    $adapter = new InMemoryAdapter();
    $entity = new stdClass();
    $expected = spl_object_hash($entity);
    assertEquals($expected, $adapter->extractEntityId($entity));
});

runTest('Extract entity type from EntityInterface', function() {
    $adapter = new InMemoryAdapter();
    $entity = new TestEntity('test-123', 'CustomType');
    assertEquals('CustomType', $adapter->extractEntityType($entity));
});

runTest('Extract entity type falls back to class name', function() {
    $adapter = new InMemoryAdapter();
    $entity = new stdClass();
    assertEquals('stdClass', $adapter->extractEntityType($entity));
});

// Metadata Validation Tests
runTest('Validate simple metadata', function() {
    $adapter = new InMemoryAdapter();
    $metadata = ['key' => 'value', 'number' => 123];
    $result = $adapter->validateAndNormalizeMetadata($metadata);
    assertEquals($metadata, $result);
});

runTest('Validate DateTime metadata', function() {
    $adapter = new InMemoryAdapter();
    $dateTime = new DateTimeImmutable('2023-01-01T12:00:00Z');
    $metadata = ['created_at' => $dateTime];
    $result = $adapter->validateAndNormalizeMetadata($metadata);
    assertEquals(['created_at' => '2023-01-01T12:00:00+00:00'], $result);
});

runTest('Reject resource in metadata', function() {
    $adapter = new InMemoryAdapter();
    $resource = fopen('php://memory', 'r');
    $metadata = ['resource' => $resource];
    
    try {
        $adapter->validateAndNormalizeMetadata($metadata);
        throw new Exception('Should have thrown InvalidMetadataException');
    } catch (InvalidMetadataException $e) {
        assertTrue(str_contains($e->getMessage(), 'cannot contain resources'));
    } finally {
        fclose($resource);
    }
});

runTest('Reject non-string keys', function() {
    $adapter = new InMemoryAdapter();
    $metadata = [123 => 'value'];
    
    try {
        $adapter->validateAndNormalizeMetadata($metadata);
        throw new Exception('Should have thrown InvalidMetadataException');
    } catch (InvalidMetadataException $e) {
        assertTrue(str_contains($e->getMessage(), 'keys must be strings'));
    }
});

// CRUD Operations Tests
runTest('Store and find binding', function() {
    $adapter = new InMemoryAdapter();
    $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    
    $adapter->store($binding);
    $found = $adapter->find($binding->getId());
    assertEquals($binding, $found);
});

runTest('Find non-existent binding returns null', function() {
    $adapter = new InMemoryAdapter();
    $result = $adapter->find('non-existent');
    assertNull($result);
});

runTest('Delete existing binding', function() {
    $adapter = new InMemoryAdapter();
    $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    
    $adapter->store($binding);
    $adapter->delete($binding->getId());
    $found = $adapter->find($binding->getId());
    assertNull($found);
});

runTest('Delete non-existent binding throws exception', function() {
    $adapter = new InMemoryAdapter();
    
    try {
        $adapter->delete('non-existent');
        throw new Exception('Should have thrown BindingNotFoundException');
    } catch (BindingNotFoundException $e) {
        assertTrue(str_contains($e->getMessage(), 'not found'));
    }
});

runTest('Update metadata', function() {
    $adapter = new InMemoryAdapter();
    $binding = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access', ['level' => 'read']);
    
    $adapter->store($binding);
    $adapter->updateMetadata($binding->getId(), ['level' => 'write']);
    
    $updated = $adapter->find($binding->getId());
    assertEquals(['level' => 'write'], $updated->getMetadata());
});

// Entity-based Query Tests
runTest('Find by entity', function() {
    $adapter = new InMemoryAdapter();
    $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
    $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');
    
    $adapter->store($binding1);
    $adapter->store($binding2);
    $adapter->store($binding3);
    
    $results = $adapter->findByEntity('User', 'user-1');
    assertCount(2, $results);
    assertContains($binding1, $results);
    assertContains($binding2, $results);
});

runTest('Find between entities', function() {
    $adapter = new InMemoryAdapter();
    $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    $binding2 = Binding::create('User', 'user-1', 'Project', 'project-1', 'owns');
    $binding3 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
    
    $adapter->store($binding1);
    $adapter->store($binding2);
    $adapter->store($binding3);
    
    $results = $adapter->findBetweenEntities('User', 'user-1', 'Project', 'project-1');
    assertCount(2, $results);
    assertContains($binding1, $results);
    assertContains($binding2, $results);
});

runTest('Delete by entity', function() {
    $adapter = new InMemoryAdapter();
    $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    $binding2 = Binding::create('User', 'user-1', 'Project', 'project-2', 'has_access');
    $binding3 = Binding::create('User', 'user-2', 'Project', 'project-1', 'has_access');
    
    $adapter->store($binding1);
    $adapter->store($binding2);
    $adapter->store($binding3);
    
    $deletedCount = $adapter->deleteByEntity('User', 'user-1');
    assertEquals(2, $deletedCount);
    
    assertNull($adapter->find($binding1->getId()));
    assertNull($adapter->find($binding2->getId()));
    assertEquals($binding3, $adapter->find($binding3->getId()));
});

// Query Execution Tests
runTest('Execute query with from filter', function() {
    $adapter = new InMemoryAdapter();
    $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');
    
    $adapter->store($binding1);
    $adapter->store($binding2);
    
    $query = new MockQueryBuilder(['from' => ['type' => 'User', 'id' => 'user-1']]);
    $results = $adapter->executeQuery($query);
    
    assertCount(1, $results);
    assertContains($binding1, $results);
});

runTest('Count query', function() {
    $adapter = new InMemoryAdapter();
    $binding1 = Binding::create('User', 'user-1', 'Project', 'project-1', 'has_access');
    $binding2 = Binding::create('User', 'user-2', 'Project', 'project-2', 'has_access');
    
    $adapter->store($binding1);
    $adapter->store($binding2);
    
    $query = new MockQueryBuilder([]);
    $count = $adapter->count($query);
    
    assertEquals(2, $count);
});

echo "\n=== Test Results ===\n";
echo "Passed: $passedCount / $testCount\n";

if ($passedCount === $testCount) {
    echo "✓ All tests passed!\n";
    echo "\nInMemoryAdapter implementation is working correctly!\n";
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
