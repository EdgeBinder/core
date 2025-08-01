<?php
declare(strict_types=1);

namespace EdgeBinder\Tests\Performance;

use PHPUnit\Framework\TestCase;
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\EdgeBinder;
use Psr\Container\ContainerInterface;

/**
 * Performance tests for the adapter registry system.
 * 
 * These tests validate that the registry system performs well under load
 * and doesn't introduce significant overhead to EdgeBinder operations.
 */
class RegistryPerformanceTest extends TestCase
{
    private array $performanceResults = [];

    protected function setUp(): void
    {
        AdapterRegistry::clear();
        $this->performanceResults = [];
    }

    protected function tearDown(): void
    {
        AdapterRegistry::clear();
        
        // Output performance results for analysis
        if (!empty($this->performanceResults)) {
            echo "\n" . str_repeat('=', 80) . "\n";
            echo "PERFORMANCE RESULTS\n";
            echo str_repeat('=', 80) . "\n";
            foreach ($this->performanceResults as $test => $result) {
                echo sprintf("%-40s: %s\n", $test, $result);
            }
            echo str_repeat('=', 80) . "\n";
        }
    }

    public function testRegistryRegistrationPerformance(): void
    {
        $numAdapters = 100;
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Register many adapters
        for ($i = 0; $i < $numAdapters; $i++) {
            $factory = new class("adapter_$i") implements AdapterFactoryInterface {
                private string $type;
                
                public function __construct(string $type) {
                    $this->type = $type;
                }
                
                public function createAdapter(array $config): PersistenceAdapterInterface {
                    return $this->createMock(PersistenceAdapterInterface::class);
                }
                
                public function getAdapterType(): string {
                    return $this->type;
                }
            };
            
            AdapterRegistry::register($factory);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $registrationTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = ($endMemory - $startMemory) / 1024; // Convert to KB
        
        $this->performanceResults['Registration Time (100 adapters)'] = sprintf('%.2f ms', $registrationTime);
        $this->performanceResults['Memory Used (100 adapters)'] = sprintf('%.2f KB', $memoryUsed);
        
        // Performance assertions
        $this->assertLessThan(100, $registrationTime, 'Registration should take less than 100ms for 100 adapters');
        $this->assertLessThan(1024, $memoryUsed, 'Memory usage should be less than 1MB for 100 adapters');
        
        // Verify all adapters are registered
        $this->assertCount($numAdapters, AdapterRegistry::getRegisteredTypes());
    }

    public function testRegistryLookupPerformance(): void
    {
        // Register some adapters first
        for ($i = 0; $i < 50; $i++) {
            $factory = new class("adapter_$i") implements AdapterFactoryInterface {
                private string $type;
                
                public function __construct(string $type) {
                    $this->type = $type;
                }
                
                public function createAdapter(array $config): PersistenceAdapterInterface {
                    return $this->createMock(PersistenceAdapterInterface::class);
                }
                
                public function getAdapterType(): string {
                    return $this->type;
                }
            };
            
            AdapterRegistry::register($factory);
        }

        $numLookups = 10000;
        $startTime = microtime(true);

        // Perform many lookups
        for ($i = 0; $i < $numLookups; $i++) {
            $exists = AdapterRegistry::hasAdapter('adapter_25'); // Middle adapter
            $this->assertTrue($exists);
        }

        $endTime = microtime(true);
        $lookupTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgLookupTime = $lookupTime / $numLookups;
        
        $this->performanceResults['Lookup Time (10k lookups)'] = sprintf('%.2f ms', $lookupTime);
        $this->performanceResults['Average Lookup Time'] = sprintf('%.4f ms', $avgLookupTime);
        
        // Performance assertions
        $this->assertLessThan(100, $lookupTime, 'Total lookup time should be less than 100ms for 10k lookups');
        $this->assertLessThan(0.01, $avgLookupTime, 'Average lookup time should be less than 0.01ms');
    }

    public function testAdapterCreationPerformance(): void
    {
        // Register a test adapter
        $factory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface {
                // Simulate some work
                usleep(100); // 0.1ms delay to simulate real adapter creation
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string {
                return 'performance_test';
            }
        };
        
        AdapterRegistry::register($factory);
        
        $container = $this->createMock(ContainerInterface::class);
        $numCreations = 100;
        $startTime = microtime(true);

        // Create many adapters
        for ($i = 0; $i < $numCreations; $i++) {
            $config = [
                'container' => $container,
                'instance' => ['adapter' => 'performance_test'],
                'global' => [],
            ];
            
            $adapter = AdapterRegistry::create('performance_test', $config);
            $this->assertInstanceOf(PersistenceAdapterInterface::class, $adapter);
        }

        $endTime = microtime(true);
        $creationTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgCreationTime = $creationTime / $numCreations;
        
        $this->performanceResults['Creation Time (100 adapters)'] = sprintf('%.2f ms', $creationTime);
        $this->performanceResults['Average Creation Time'] = sprintf('%.2f ms', $avgCreationTime);
        
        // Performance assertions (accounting for the 0.1ms delay per creation)
        $expectedMinTime = $numCreations * 0.1; // Minimum time due to usleep
        $this->assertGreaterThan($expectedMinTime, $creationTime, 'Creation time should account for simulated work');
        $this->assertLessThan($expectedMinTime + 50, $creationTime, 'Registry overhead should be minimal');
    }

    public function testEdgeBinderFactoryPerformance(): void
    {
        // Register a test adapter
        $mockAdapter = $this->createMock(PersistenceAdapterInterface::class);
        $factory = new class($mockAdapter) implements AdapterFactoryInterface {
            private PersistenceAdapterInterface $adapter;
            
            public function __construct(PersistenceAdapterInterface $adapter) {
                $this->adapter = $adapter;
            }
            
            public function createAdapter(array $config): PersistenceAdapterInterface {
                return $this->adapter;
            }
            
            public function getAdapterType(): string {
                return 'edgebinder_test';
            }
        };
        
        AdapterRegistry::register($factory);
        
        $container = $this->createMock(ContainerInterface::class);
        $numCreations = 1000;
        $startTime = microtime(true);

        // Create many EdgeBinder instances
        for ($i = 0; $i < $numCreations; $i++) {
            $config = [
                'adapter' => 'edgebinder_test',
                'test_param' => "value_$i",
            ];
            
            $edgeBinder = EdgeBinder::fromConfiguration($config, $container);
            $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        }

        $endTime = microtime(true);
        $factoryTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgFactoryTime = $factoryTime / $numCreations;
        
        $this->performanceResults['EdgeBinder Factory Time (1k instances)'] = sprintf('%.2f ms', $factoryTime);
        $this->performanceResults['Average Factory Time'] = sprintf('%.4f ms', $avgFactoryTime);
        
        // Performance assertions
        $this->assertLessThan(1000, $factoryTime, 'Factory time should be less than 1 second for 1k instances');
        $this->assertLessThan(1, $avgFactoryTime, 'Average factory time should be less than 1ms');
    }

    public function testMemoryUsageWithManyAdapters(): void
    {
        $startMemory = memory_get_usage(true);
        $numAdapters = 1000;

        // Register many adapters
        for ($i = 0; $i < $numAdapters; $i++) {
            $factory = new class("memory_test_$i") implements AdapterFactoryInterface {
                private string $type;
                
                public function __construct(string $type) {
                    $this->type = $type;
                }
                
                public function createAdapter(array $config): PersistenceAdapterInterface {
                    return $this->createMock(PersistenceAdapterInterface::class);
                }
                
                public function getAdapterType(): string {
                    return $this->type;
                }
            };
            
            AdapterRegistry::register($factory);
        }

        $endMemory = memory_get_usage(true);
        $memoryUsed = ($endMemory - $startMemory) / 1024; // Convert to KB
        $memoryPerAdapter = $memoryUsed / $numAdapters;
        
        $this->performanceResults['Memory Used (1k adapters)'] = sprintf('%.2f KB', $memoryUsed);
        $this->performanceResults['Memory Per Adapter'] = sprintf('%.4f KB', $memoryPerAdapter);
        
        // Memory assertions
        $this->assertLessThan(10240, $memoryUsed, 'Memory usage should be less than 10MB for 1k adapters');
        $this->assertLessThan(10, $memoryPerAdapter, 'Memory per adapter should be less than 10KB');
        
        // Verify all adapters are still accessible
        $this->assertCount($numAdapters, AdapterRegistry::getRegisteredTypes());
        $this->assertTrue(AdapterRegistry::hasAdapter('memory_test_500'));
    }

    public function testConcurrentAccessSimulation(): void
    {
        // Register some adapters
        for ($i = 0; $i < 10; $i++) {
            $factory = new class("concurrent_$i") implements AdapterFactoryInterface {
                private string $type;
                
                public function __construct(string $type) {
                    $this->type = $type;
                }
                
                public function createAdapter(array $config): PersistenceAdapterInterface {
                    return $this->createMock(PersistenceAdapterInterface::class);
                }
                
                public function getAdapterType(): string {
                    return $this->type;
                }
            };
            
            AdapterRegistry::register($factory);
        }

        $startTime = microtime(true);
        $operations = 0;

        // Simulate concurrent access patterns
        for ($thread = 0; $thread < 10; $thread++) {
            for ($op = 0; $op < 100; $op++) {
                // Mix of read operations
                AdapterRegistry::hasAdapter("concurrent_" . ($op % 10));
                AdapterRegistry::getRegisteredTypes();
                $operations += 2;
            }
        }

        $endTime = microtime(true);
        $concurrentTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgOpTime = $concurrentTime / $operations;
        
        $this->performanceResults['Concurrent Operations Time'] = sprintf('%.2f ms', $concurrentTime);
        $this->performanceResults['Average Operation Time'] = sprintf('%.4f ms', $avgOpTime);
        
        // Performance assertions
        $this->assertLessThan(100, $concurrentTime, 'Concurrent operations should complete in less than 100ms');
        $this->assertLessThan(0.1, $avgOpTime, 'Average operation time should be less than 0.1ms');
    }
}
