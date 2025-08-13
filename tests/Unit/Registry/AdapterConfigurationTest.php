<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Registry;

use EdgeBinder\Registry\AdapterConfiguration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit tests for AdapterConfiguration.
 *
 * Tests the configuration object that provides type-safe access to
 * adapter instance config, global settings, and container.
 */
final class AdapterConfigurationTest extends TestCase
{
    private ContainerInterface $container;

    /** @var array<string, mixed> */
    private array $instanceConfig;

    /** @var array<string, mixed> */
    private array $globalConfig;

    private AdapterConfiguration $config;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);

        $this->instanceConfig = [
            'adapter' => 'redis',
            'redis_client' => 'redis.client.cache',
            'ttl' => 3600,
            'prefix' => 'edgebinder:',
            'host' => 'localhost',
            'port' => 6379,
        ];

        $this->globalConfig = [
            'default_metadata_validation' => true,
            'entity_extraction_strategy' => 'reflection',
            'max_binding_depth' => 10,
            'debug_mode' => false,
        ];

        $this->config = new AdapterConfiguration(
            instance: $this->instanceConfig,
            global: $this->globalConfig,
            container: $this->container
        );
    }

    public function testGetInstanceConfig(): void
    {
        $result = $this->config->getInstanceConfig();

        $this->assertEquals($this->instanceConfig, $result);
        $this->assertIsArray($result);
    }

    public function testGetGlobalSettings(): void
    {
        $result = $this->config->getGlobalSettings();

        $this->assertEquals($this->globalConfig, $result);
        $this->assertIsArray($result);
    }

    public function testGetContainer(): void
    {
        $result = $this->config->getContainer();

        $this->assertSame($this->container, $result);
        $this->assertInstanceOf(ContainerInterface::class, $result);
    }

    public function testGetInstanceValueWithExistingKey(): void
    {
        $result = $this->config->getInstanceValue('adapter');

        $this->assertEquals('redis', $result);
    }

    public function testGetInstanceValueWithNonExistentKey(): void
    {
        $result = $this->config->getInstanceValue('nonexistent_key');

        $this->assertNull($result);
    }

    public function testGetInstanceValueWithDefault(): void
    {
        $result = $this->config->getInstanceValue('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function testGetInstanceValueWithComplexDefault(): void
    {
        $defaultArray = ['key' => 'value', 'nested' => ['data' => 123]];
        $result = $this->config->getInstanceValue('complex_key', $defaultArray);

        $this->assertEquals($defaultArray, $result);
    }

    public function testGetGlobalValueWithExistingKey(): void
    {
        $result = $this->config->getGlobalValue('debug_mode');

        $this->assertFalse($result);
    }

    public function testGetGlobalValueWithNonExistentKey(): void
    {
        $result = $this->config->getGlobalValue('nonexistent_global_key');

        $this->assertNull($result);
    }

    public function testGetGlobalValueWithDefault(): void
    {
        $result = $this->config->getGlobalValue('nonexistent_global_key', 'global_default');

        $this->assertEquals('global_default', $result);
    }

    public function testGetGlobalValueWithComplexDefault(): void
    {
        $defaultObject = new \stdClass();
        $defaultObject->property = 'value';

        $result = $this->config->getGlobalValue('complex_global_key', $defaultObject);

        $this->assertSame($defaultObject, $result);
    }

    public function testConfigurationIsImmutable(): void
    {
        // Get references to the arrays
        $instanceConfig = $this->config->getInstanceConfig();
        $globalSettings = $this->config->getGlobalSettings();

        // Modify the returned arrays
        $instanceConfig['new_key'] = 'new_value';
        $globalSettings['new_global'] = 'new_global_value';

        // Original configuration should be unchanged
        $this->assertArrayNotHasKey('new_key', $this->config->getInstanceConfig());
        $this->assertArrayNotHasKey('new_global', $this->config->getGlobalSettings());
    }

    public function testEmptyConfiguration(): void
    {
        $emptyConfig = new AdapterConfiguration(
            instance: [],
            global: [],
            container: $this->container
        );

        $this->assertEquals([], $emptyConfig->getInstanceConfig());
        $this->assertEquals([], $emptyConfig->getGlobalSettings());
        $this->assertSame($this->container, $emptyConfig->getContainer());

        // Test convenience methods with empty config
        $this->assertNull($emptyConfig->getInstanceValue('any_key'));
        $this->assertNull($emptyConfig->getGlobalValue('any_key'));
        $this->assertEquals('default', $emptyConfig->getInstanceValue('any_key', 'default'));
        $this->assertEquals('default', $emptyConfig->getGlobalValue('any_key', 'default'));
    }

    public function testConfigurationWithNullValues(): void
    {
        $configWithNulls = new AdapterConfiguration(
            instance: [
                'adapter' => 'test',
                'nullable_setting' => null,
                'zero_value' => 0,
                'empty_string' => '',
                'false_value' => false,
            ],
            global: [
                'nullable_global' => null,
                'zero_global' => 0,
                'empty_global' => '',
                'false_global' => false,
            ],
            container: $this->container
        );

        // Test that null values are preserved (not replaced with defaults)
        $this->assertNull($configWithNulls->getInstanceValue('nullable_setting'));
        $this->assertNull($configWithNulls->getGlobalValue('nullable_global'));

        // Test that falsy values are preserved
        $this->assertEquals(0, $configWithNulls->getInstanceValue('zero_value'));
        $this->assertEquals('', $configWithNulls->getInstanceValue('empty_string'));
        $this->assertFalse($configWithNulls->getInstanceValue('false_value'));

        $this->assertEquals(0, $configWithNulls->getGlobalValue('zero_global'));
        $this->assertEquals('', $configWithNulls->getGlobalValue('empty_global'));
        $this->assertFalse($configWithNulls->getGlobalValue('false_global'));

        // Test that defaults are only used for missing keys
        $this->assertEquals('default', $configWithNulls->getInstanceValue('missing_key', 'default'));
        $this->assertEquals('default', $configWithNulls->getGlobalValue('missing_key', 'default'));
    }
}
