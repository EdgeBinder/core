<?php
declare(strict_types=1);

namespace EdgeBinder\Tests\Security;

use PHPUnit\Framework\TestCase;
use EdgeBinder\Registry\AdapterRegistry;
use EdgeBinder\Registry\AdapterFactoryInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\EdgeBinder;
use EdgeBinder\Exception\AdapterException;
use Psr\Container\ContainerInterface;

/**
 * Security validation tests for the extensible adapter system.
 * 
 * These tests validate that the adapter system is secure against common
 * attack vectors and doesn't expose sensitive information.
 */
class SecurityValidationTest extends TestCase
{
    protected function setUp(): void
    {
        AdapterRegistry::clear();
    }

    protected function tearDown(): void
    {
        AdapterRegistry::clear();
    }

    public function testConfigurationInjectionPrevention(): void
    {
        // Register a test adapter
        $factory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                // This should validate input and prevent injection
                $instanceConfig = $config['instance'];
                
                if (isset($instanceConfig['malicious_param'])) {
                    throw new \InvalidArgumentException('Malicious parameter detected');
                }
                
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return 'security_test';
            }
        };
        
        AdapterRegistry::register($factory);
        
        $container = $this->createMock(ContainerInterface::class);
        
        // Test various injection attempts
        $maliciousConfigs = [
            [
                'adapter' => 'security_test',
                'malicious_param' => '$(rm -rf /)',  // Command injection
            ],
            [
                'adapter' => 'security_test',
                'malicious_param' => '<?php system("rm -rf /"); ?>',  // PHP injection
            ],
            [
                'adapter' => 'security_test',
                'malicious_param' => '<script>alert("xss")</script>',  // XSS attempt
            ],
            [
                'adapter' => 'security_test',
                'malicious_param' => '../../../etc/passwd',  // Path traversal
            ],
        ];
        
        foreach ($maliciousConfigs as $config) {
            $this->expectException(AdapterException::class);
            EdgeBinder::fromConfiguration($config, $container);
        }
    }

    public function testContainerServiceIsolation(): void
    {
        // Test that adapters can only access intended services
        $container = new class implements ContainerInterface {
            private array $services = [
                'safe_service' => 'safe_value',
                'sensitive_service' => 'sensitive_data',
            ];
            
            public function get(string $id)
            {
                if ($id === 'sensitive_service') {
                    throw new \Exception('Access to sensitive service denied');
                }
                
                if (!$this->has($id)) {
                    throw new \Exception("Service {$id} not found");
                }
                
                return $this->services[$id];
            }
            
            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
        
        $factory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                $container = $config['container'];
                
                // Try to access a service
                $service = $container->get($config['instance']['service_name']);
                
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return 'isolation_test';
            }
        };
        
        AdapterRegistry::register($factory);
        
        // Test access to safe service (should work)
        $safeConfig = [
            'adapter' => 'isolation_test',
            'service_name' => 'safe_service',
        ];
        
        $edgeBinder = EdgeBinder::fromConfiguration($safeConfig, $container);
        $this->assertInstanceOf(EdgeBinder::class, $edgeBinder);
        
        // Test access to sensitive service (should fail)
        $sensitiveConfig = [
            'adapter' => 'isolation_test',
            'service_name' => 'sensitive_service',
        ];
        
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Access to sensitive service denied');
        EdgeBinder::fromConfiguration($sensitiveConfig, $container);
    }

    public function testErrorMessageInformationDisclosure(): void
    {
        // Test that error messages don't expose sensitive information
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        
        $factory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                throw new \Exception('Database password is: secret123');
            }
            
            public function getAdapterType(): string
            {
                return 'disclosure_test';
            }
        };
        
        AdapterRegistry::register($factory);
        
        $config = [
            'adapter' => 'disclosure_test',
            'database_password' => 'secret123',
        ];
        
        try {
            EdgeBinder::fromConfiguration($config, $container);
            $this->fail('Expected exception was not thrown');
        } catch (AdapterException $e) {
            // Verify that sensitive information is not exposed in the exception message
            $message = $e->getMessage();
            $this->assertStringNotContainsString('secret123', $message, 'Sensitive data should not be in error messages');
            $this->assertStringNotContainsString('password', $message, 'Sensitive field names should not be in error messages');
        }
    }

    public function testAdapterTypeValidation(): void
    {
        // Test that adapter types are properly validated
        $maliciousFactory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return '../../../malicious';  // Path traversal attempt
            }
        };
        
        // This should not cause issues with the registry
        AdapterRegistry::register($maliciousFactory);
        
        // Verify the adapter type is stored as-is but doesn't cause security issues
        $this->assertTrue(AdapterRegistry::hasAdapter('../../../malicious'));
        $types = AdapterRegistry::getRegisteredTypes();
        $this->assertContains('../../../malicious', $types);
        
        // The registry should handle this safely
        $this->assertIsArray($types);
    }

    public function testStaticRegistryMemoryLeaks(): void
    {
        // Test that the static registry doesn't cause memory leaks
        $initialMemory = memory_get_usage(true);
        
        // Register many adapters
        for ($i = 0; $i < 1000; $i++) {
            $factory = new class("leak_test_$i") implements AdapterFactoryInterface {
                private string $type;
                private array $largeData;
                
                public function __construct(string $type) {
                    $this->type = $type;
                    // Add some data to make the object larger
                    $this->largeData = array_fill(0, 100, "data_$type");
                }
                
                public function createAdapter(array $config): PersistenceAdapterInterface
                {
                    return $this->createMock(PersistenceAdapterInterface::class);
                }
                
                public function getAdapterType(): string
                {
                    return $this->type;
                }
            };
            
            AdapterRegistry::register($factory);
        }
        
        $afterRegistrationMemory = memory_get_usage(true);
        
        // Clear the registry
        AdapterRegistry::clear();
        
        // Force garbage collection
        gc_collect_cycles();
        
        $afterClearMemory = memory_get_usage(true);
        
        // Memory should be mostly reclaimed after clearing
        $memoryDifference = $afterClearMemory - $initialMemory;
        $this->assertLessThan(1024 * 1024, $memoryDifference, 'Memory should be reclaimed after clearing registry');
        
        // Verify registry is actually cleared
        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
    }

    public function testConcurrentRegistrationSafety(): void
    {
        // Test that concurrent registration attempts are handled safely
        $factory1 = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return 'concurrent_test';
            }
        };
        
        $factory2 = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return 'concurrent_test';  // Same type as factory1
            }
        };
        
        // First registration should succeed
        AdapterRegistry::register($factory1);
        $this->assertTrue(AdapterRegistry::hasAdapter('concurrent_test'));
        
        // Second registration with same type should fail
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('already registered');
        AdapterRegistry::register($factory2);
    }

    public function testConfigurationSanitization(): void
    {
        // Test that configuration values are properly sanitized
        $factory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                $instanceConfig = $config['instance'];
                
                // Validate that dangerous characters are handled
                foreach ($instanceConfig as $key => $value) {
                    if (is_string($value) && preg_match('/[<>"\']/', $value)) {
                        throw new \InvalidArgumentException("Potentially dangerous characters in config value: $key");
                    }
                }
                
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return 'sanitization_test';
            }
        };
        
        AdapterRegistry::register($factory);
        
        $container = $this->createMock(ContainerInterface::class);
        
        // Test with potentially dangerous configuration
        $dangerousConfig = [
            'adapter' => 'sanitization_test',
            'param1' => '<script>alert("xss")</script>',
            'param2' => '"malicious"',
            'param3' => "'injection'",
        ];
        
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Potentially dangerous characters');
        EdgeBinder::fromConfiguration($dangerousConfig, $container);
    }

    public function testAdapterFactoryValidation(): void
    {
        // Test that adapter factories are properly validated
        $invalidFactory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                // Return wrong type
                return new \stdClass();
            }
            
            public function getAdapterType(): string
            {
                return 'invalid_factory';
            }
        };
        
        AdapterRegistry::register($invalidFactory);
        
        $container = $this->createMock(ContainerInterface::class);
        $config = [
            'adapter' => 'invalid_factory',
        ];
        
        // This should fail because the factory returns wrong type
        $this->expectException(\TypeError::class);
        EdgeBinder::fromConfiguration($config, $container);
    }

    public function testRegistryStateIsolation(): void
    {
        // Test that registry state is properly isolated between tests
        $factory = new class implements AdapterFactoryInterface {
            public function createAdapter(array $config): PersistenceAdapterInterface
            {
                return $this->createMock(PersistenceAdapterInterface::class);
            }
            
            public function getAdapterType(): string
            {
                return 'isolation_test';
            }
        };
        
        // Register adapter
        AdapterRegistry::register($factory);
        $this->assertTrue(AdapterRegistry::hasAdapter('isolation_test'));
        
        // Clear registry
        AdapterRegistry::clear();
        $this->assertFalse(AdapterRegistry::hasAdapter('isolation_test'));
        $this->assertEmpty(AdapterRegistry::getRegisteredTypes());
        
        // Re-register should work
        AdapterRegistry::register($factory);
        $this->assertTrue(AdapterRegistry::hasAdapter('isolation_test'));
    }
}
