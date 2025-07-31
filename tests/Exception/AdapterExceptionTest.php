<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Exception;

use EdgeBinder\Exception\AdapterException;
use EdgeBinder\Exception\EdgeBinderException;
use PHPUnit\Framework\TestCase;

class AdapterExceptionTest extends TestCase
{
    public function testAdapterExceptionExtendsEdgeBinderException(): void
    {
        $exception = new AdapterException('Test message');

        $this->assertInstanceOf(EdgeBinderException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testFactoryNotFoundWithoutAvailableTypes(): void
    {
        $exception = AdapterException::factoryNotFound('unknown_adapter');

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Adapter factory for type 'unknown_adapter' not found. No adapters are currently registered.",
            $exception->getMessage()
        );
    }

    public function testFactoryNotFoundWithAvailableTypes(): void
    {
        $availableTypes = ['weaviate', 'janus', 'redis'];
        $exception = AdapterException::factoryNotFound('unknown_adapter', $availableTypes);

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Adapter factory for type 'unknown_adapter' not found. Available types: weaviate, janus, redis",
            $exception->getMessage()
        );
    }

    public function testFactoryNotFoundWithEmptyAvailableTypes(): void
    {
        $exception = AdapterException::factoryNotFound('unknown_adapter', []);

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Adapter factory for type 'unknown_adapter' not found. No adapters are currently registered.",
            $exception->getMessage()
        );
    }

    public function testCreationFailedWithoutPrevious(): void
    {
        $exception = AdapterException::creationFailed('janus', 'Connection timeout');

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Failed to create adapter of type 'janus': Connection timeout",
            $exception->getMessage()
        );
        $this->assertNull($exception->getPrevious());
    }

    public function testCreationFailedWithPrevious(): void
    {
        $previousException = new \RuntimeException('Network error');
        $exception = AdapterException::creationFailed('janus', 'Connection timeout', $previousException);

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Failed to create adapter of type 'janus': Connection timeout",
            $exception->getMessage()
        );
        $this->assertSame($previousException, $exception->getPrevious());
    }

    public function testAlreadyRegistered(): void
    {
        $exception = AdapterException::alreadyRegistered('janus');

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Adapter type 'janus' is already registered",
            $exception->getMessage()
        );
    }

    public function testInvalidConfiguration(): void
    {
        $exception = AdapterException::invalidConfiguration('janus', 'Missing host parameter');

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Invalid configuration for adapter type 'janus': Missing host parameter",
            $exception->getMessage()
        );
    }

    public function testMissingConfigurationSingleKey(): void
    {
        $exception = AdapterException::missingConfiguration('janus', ['host']);

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Missing required configuration for adapter type 'janus': host",
            $exception->getMessage()
        );
    }

    public function testMissingConfigurationMultipleKeys(): void
    {
        $exception = AdapterException::missingConfiguration('janus', ['host', 'port', 'username']);

        $this->assertInstanceOf(AdapterException::class, $exception);
        $this->assertEquals(
            "Missing required configuration for adapter type 'janus': host, port, username",
            $exception->getMessage()
        );
    }

    public function testExceptionChaining(): void
    {
        $originalException = new \RuntimeException('Original error');

        $adapterException = AdapterException::creationFailed('test', 'reason', $originalException);
        $this->assertSame($originalException, $adapterException->getPrevious());
    }

    public function testExceptionCode(): void
    {
        $exception = AdapterException::creationFailed('test', 'reason');
        $this->assertEquals(0, $exception->getCode());

        $previousException = new \RuntimeException('Original error', 123);
        $exceptionWithPrevious = AdapterException::creationFailed('test', 'reason', $previousException);
        $this->assertEquals(0, $exceptionWithPrevious->getCode());

        $previous = $exceptionWithPrevious->getPrevious();
        $this->assertNotNull($previous);
        $this->assertEquals(123, $previous->getCode());
    }
}
