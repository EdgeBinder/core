<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Contracts;

use EdgeBinder\Contracts\BindingInterface;
use EdgeBinder\Contracts\EdgeBinderInterface;
use EdgeBinder\Contracts\EntityInterface;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Contracts\QueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test that verifies interface contracts and method signatures.
 *
 * This test ensures that all interfaces are properly defined with
 * correct method signatures, return types, and parameter types.
 */
class InterfaceContractTest extends TestCase
{
    public function testEntityInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(EntityInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('getId'));
        $this->assertTrue($reflection->hasMethod('getType'));

        $getIdMethod = $reflection->getMethod('getId');
        $returnType = $getIdMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('string', $returnType->getName());
        $this->assertCount(0, $getIdMethod->getParameters());

        $getTypeMethod = $reflection->getMethod('getType');
        $returnType = $getTypeMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('string', $returnType->getName());
        $this->assertCount(0, $getTypeMethod->getParameters());
    }

    public function testBindingInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(BindingInterface::class);

        $this->assertTrue($reflection->isInterface());

        // Test core getter methods
        $requiredMethods = [
            'getId' => 'string',
            'getFromType' => 'string',
            'getFromId' => 'string',
            'getToType' => 'string',
            'getToId' => 'string',
            'getType' => 'string',
            'getMetadata' => 'array',
            'getCreatedAt' => 'DateTimeImmutable',
            'getUpdatedAt' => 'DateTimeImmutable',
        ];

        foreach ($requiredMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Method {$methodName} should exist");
            $method = $reflection->getMethod($methodName);
            $this->assertCount(0, $method->getParameters(), "Method {$methodName} should have no parameters");
        }

        // Test methods with parameters
        $this->assertTrue($reflection->hasMethod('withMetadata'));
        $withMetadataMethod = $reflection->getMethod('withMetadata');
        $this->assertCount(1, $withMetadataMethod->getParameters());

        $this->assertTrue($reflection->hasMethod('connects'));
        $connectsMethod = $reflection->getMethod('connects');
        $this->assertCount(4, $connectsMethod->getParameters());
        $returnType = $connectsMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('bool', $returnType->getName());

        $this->assertTrue($reflection->hasMethod('involves'));
        $involvesMethod = $reflection->getMethod('involves');
        $this->assertCount(2, $involvesMethod->getParameters());
        $returnType = $involvesMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testPersistenceAdapterInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(PersistenceAdapterInterface::class);

        $this->assertTrue($reflection->isInterface());

        // Test entity extraction methods
        $this->assertTrue($reflection->hasMethod('extractEntityId'));
        $this->assertTrue($reflection->hasMethod('extractEntityType'));
        $this->assertTrue($reflection->hasMethod('validateAndNormalizeMetadata'));

        // Test CRUD methods
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->hasMethod('find'));
        $this->assertTrue($reflection->hasMethod('delete'));
        $this->assertTrue($reflection->hasMethod('updateMetadata'));

        // Test query methods
        $this->assertTrue($reflection->hasMethod('findByEntity'));
        $this->assertTrue($reflection->hasMethod('findBetweenEntities'));
        $this->assertTrue($reflection->hasMethod('executeQuery'));
        $this->assertTrue($reflection->hasMethod('count'));
        $this->assertTrue($reflection->hasMethod('deleteByEntity'));

        // Verify method signatures for key methods
        $findMethod = $reflection->getMethod('find');
        $this->assertCount(1, $findMethod->getParameters());

        $storeMethod = $reflection->getMethod('store');
        $this->assertCount(1, $storeMethod->getParameters());

        $executeQueryMethod = $reflection->getMethod('executeQuery');
        $this->assertCount(1, $executeQueryMethod->getParameters());
    }

    public function testQueryBuilderInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(QueryBuilderInterface::class);

        $this->assertTrue($reflection->isInterface());

        // Test fluent interface methods
        $fluentMethods = [
            'from', 'to', 'type', 'where', 'whereIn', 'whereBetween',
            'whereExists', 'whereNull', 'orWhere', 'orderBy', 'limit', 'offset',
        ];

        foreach ($fluentMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Method {$methodName} should exist");
        }

        // Test execution methods
        $this->assertTrue($reflection->hasMethod('get'));
        $this->assertTrue($reflection->hasMethod('first'));
        $this->assertTrue($reflection->hasMethod('count'));
        $this->assertTrue($reflection->hasMethod('exists'));
        $this->assertTrue($reflection->hasMethod('getCriteria'));

        // Verify return types for execution methods
        $countMethod = $reflection->getMethod('count');
        $returnType = $countMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('int', $returnType->getName());

        $existsMethod = $reflection->getMethod('exists');
        $returnType = $existsMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('bool', $returnType->getName());

        $getCriteriaMethod = $reflection->getMethod('getCriteria');
        $returnType = $getCriteriaMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testEdgeBinderInterfaceContract(): void
    {
        $reflection = new \ReflectionClass(EdgeBinderInterface::class);

        $this->assertTrue($reflection->isInterface());

        // Test core binding methods
        $this->assertTrue($reflection->hasMethod('bind'));
        $this->assertTrue($reflection->hasMethod('unbind'));
        $this->assertTrue($reflection->hasMethod('unbindEntities'));
        $this->assertTrue($reflection->hasMethod('unbindEntity'));

        // Test query methods
        $this->assertTrue($reflection->hasMethod('query'));
        $this->assertTrue($reflection->hasMethod('findBinding'));
        $this->assertTrue($reflection->hasMethod('findBindingsFor'));
        $this->assertTrue($reflection->hasMethod('findBindingsBetween'));
        $this->assertTrue($reflection->hasMethod('areBound'));

        // Test metadata methods
        $this->assertTrue($reflection->hasMethod('updateMetadata'));
        $this->assertTrue($reflection->hasMethod('replaceMetadata'));
        $this->assertTrue($reflection->hasMethod('getMetadata'));

        // Test utility methods
        $this->assertTrue($reflection->hasMethod('getStorageAdapter'));

        // Verify key method signatures
        $bindMethod = $reflection->getMethod('bind');
        $this->assertCount(4, $bindMethod->getParameters());

        $queryMethod = $reflection->getMethod('query');
        $this->assertCount(0, $queryMethod->getParameters());

        $areBoundMethod = $reflection->getMethod('areBound');
        $returnType = $areBoundMethod->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testInterfaceNamespaces(): void
    {
        $interfaces = [
            EntityInterface::class,
            BindingInterface::class,
            PersistenceAdapterInterface::class,
            QueryBuilderInterface::class,
            EdgeBinderInterface::class,
        ];

        foreach ($interfaces as $interface) {
            $this->assertStringStartsWith('EdgeBinder\\Contracts\\', $interface);
            $this->assertTrue(interface_exists($interface));
        }
    }
}
