<?php

declare(strict_types=1);

// Simple test runner for auto-registration enhancement
echo "=== EdgeBinder Auto-Registration Enhancement Test Runner ===\n\n";

// Include required files
require_once 'src/Contracts/PersistenceAdapterInterface.php';
require_once 'src/Contracts/BindingInterface.php';
require_once 'src/Contracts/EntityInterface.php';
require_once 'src/Contracts/QueryBuilderInterface.php';
require_once 'src/Contracts/EdgeBinderInterface.php';
require_once 'src/Exception/EdgeBinderException.php';
require_once 'src/Exception/AdapterException.php';
require_once 'src/Exception/PersistenceException.php';
require_once 'src/Exception/BindingNotFoundException.php';
require_once 'src/Exception/InvalidMetadataException.php';
require_once 'src/Exception/EntityExtractionException.php';
require_once 'src/Binding.php';
require_once 'src/Registry/AdapterFactoryInterface.php';
require_once 'src/Registry/AdapterRegistry.php';
require_once 'src/EdgeBinder.php';
require_once 'src/Persistence/InMemory/InMemoryAdapter.php';

use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\EdgeBinder;

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

function assertFalse($condition, string $message = 'Assertion failed'): void {
    if ($condition) {
        throw new Exception($message);
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

function assertSame($expected, $actual, string $message = 'Objects are not the same'): void {
    if ($expected !== $actual) {
        throw new Exception($message);
    }
}

function assertNotSame($expected, $actual, string $message = 'Objects should not be the same'): void {
    if ($expected === $actual) {
        throw new Exception($message);
    }
}

// Mock adapter factory for testing
class MockAdapterFactory implements AdapterFactoryInterface {
    public function __construct(private string $type = 'mock') {}
    
    public function createAdapter(array $config): PersistenceAdapterInterface {
        return new InMemoryAdapter();
    }
    
    public function getAdapterType(): string {
        return $this->type;
    }
}

echo "Running auto-registration enhancement tests...\n\n";

// Clear registry before tests
AdapterRegistry::clear();

// Test 1: Basic idempotent registration
runTest('Register adapter multiple times without error', function() {
    AdapterRegistry::clear();
    
    $factory = new MockAdapterFactory('test_adapter');
    
    // Register multiple times - should not throw exception
    AdapterRegistry::register($factory);
    AdapterRegistry::register($factory);
    AdapterRegistry::register($factory);
    
    assertTrue(AdapterRegistry::hasAdapter('test_adapter'));
    assertCount(1, AdapterRegistry::getRegisteredTypes());
});

// Test 2: First registration wins
runTest('First registration takes precedence', function() {
    AdapterRegistry::clear();
    
    $factory1 = new MockAdapterFactory('test_adapter');
    $factory2 = new MockAdapterFactory('test_adapter');
    
    AdapterRegistry::register($factory1);
    AdapterRegistry::register($factory2);
    
    assertSame($factory1, AdapterRegistry::getFactory('test_adapter'));
    assertNotSame($factory2, AdapterRegistry::getFactory('test_adapter'));
});

// Test 3: Auto-registration pattern simulation
runTest('Auto-registration pattern works correctly', function() {
    AdapterRegistry::clear();
    
    $factory1 = new MockAdapterFactory('auto_adapter');
    $factory2 = new MockAdapterFactory('auto_adapter');
    
    // Simulate first auto-registration attempt
    if (!AdapterRegistry::hasAdapter('auto_adapter')) {
        AdapterRegistry::register($factory1);
    }
    
    assertTrue(AdapterRegistry::hasAdapter('auto_adapter'));
    assertSame($factory1, AdapterRegistry::getFactory('auto_adapter'));
    
    // Simulate second auto-registration attempt (should be ignored)
    if (!AdapterRegistry::hasAdapter('auto_adapter')) {
        AdapterRegistry::register($factory2);
    }
    
    // Should still have the first factory
    assertSame($factory1, AdapterRegistry::getFactory('auto_adapter'));
    assertNotSame($factory2, AdapterRegistry::getFactory('auto_adapter'));
});

// Test 4: Version constant exists
runTest('EdgeBinder version constant exists', function() {
    assertTrue(defined('EdgeBinder\EdgeBinder::VERSION'));

    assertEquals('0.2.0', EdgeBinder::VERSION);
});

// Test 5: Version format validation
runTest('Version follows semantic versioning', function() {
    $version = EdgeBinder::VERSION;
    assertTrue(preg_match('/^\d+\.\d+\.\d+$/', $version) === 1, "Version should follow semantic versioning format");
});

// Test 6: Multiple different adapters can be registered
runTest('Multiple different adapters can be registered', function() {
    AdapterRegistry::clear();
    
    $factory1 = new MockAdapterFactory('adapter1');
    $factory2 = new MockAdapterFactory('adapter2');
    $factory3 = new MockAdapterFactory('adapter3');
    
    AdapterRegistry::register($factory1);
    AdapterRegistry::register($factory2);
    AdapterRegistry::register($factory3);
    
    assertTrue(AdapterRegistry::hasAdapter('adapter1'));
    assertTrue(AdapterRegistry::hasAdapter('adapter2'));
    assertTrue(AdapterRegistry::hasAdapter('adapter3'));
    
    $types = AdapterRegistry::getRegisteredTypes();
    assertCount(3, $types);
    assertContains('adapter1', $types);
    assertContains('adapter2', $types);
    assertContains('adapter3', $types);
});

// Test 7: Clear functionality still works
runTest('Clear functionality removes all adapters', function() {
    AdapterRegistry::clear();
    
    $factory1 = new MockAdapterFactory('adapter1');
    $factory2 = new MockAdapterFactory('adapter2');
    
    AdapterRegistry::register($factory1);
    AdapterRegistry::register($factory2);
    
    assertCount(2, AdapterRegistry::getRegisteredTypes());
    
    AdapterRegistry::clear();
    
    assertCount(0, AdapterRegistry::getRegisteredTypes());
    assertFalse(AdapterRegistry::hasAdapter('adapter1'));
    assertFalse(AdapterRegistry::hasAdapter('adapter2'));
});

// Test 8: Existing functionality still works
runTest('Existing functionality maintained', function() {
    AdapterRegistry::clear();
    
    $factory = new MockAdapterFactory('test');
    AdapterRegistry::register($factory);
    
    // Test all existing methods still work
    assertTrue(AdapterRegistry::hasAdapter('test'));
    assertContains('test', AdapterRegistry::getRegisteredTypes());
    assertSame($factory, AdapterRegistry::getFactory('test'));
    
    // Test adapter creation still works
    $config = [
        'instance' => ['adapter' => 'test'],
        'global' => [],
        'container' => new class {
            public function get($id) { return null; }
            public function has($id) { return false; }
        }
    ];
    
    $adapter = AdapterRegistry::create('test', $config);
    assertTrue($adapter instanceof PersistenceAdapterInterface);
});

echo "\n=== Test Results ===\n";
echo "Passed: $passedCount / $testCount\n";

if ($passedCount === $testCount) {
    echo "✓ All auto-registration enhancement tests passed!\n";
    echo "\nAuto-registration enhancement is working correctly!\n";
    echo "- Idempotent registration implemented\n";
    echo "- Version constants added\n";
    echo "- Existing functionality maintained\n";
    echo "- Ready for adapter auto-registration patterns\n";
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
