<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Exception;

use EdgeBinder\Exception\BindingNotFoundException;
use EdgeBinder\Exception\EdgeBinderException;
use EdgeBinder\Exception\EntityExtractionException;
use EdgeBinder\Exception\InvalidMetadataException;
use EdgeBinder\Exception\PersistenceException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testEdgeBinderExceptionIsBaseException(): void
    {
        $exception = new EdgeBinderException('Test message');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testBindingNotFoundExceptionWithId(): void
    {
        $exception = new BindingNotFoundException('binding-123');

        $this->assertInstanceOf(EdgeBinderException::class, $exception);
        $this->assertEquals("Binding with ID 'binding-123' was not found", $exception->getMessage());
    }

    public function testBindingNotFoundExceptionBetweenEntities(): void
    {
        $exception = BindingNotFoundException::betweenEntities(
            'User',
            'user-1',
            'Project',
            'project-1',
            'has_access'
        );

        $this->assertInstanceOf(BindingNotFoundException::class, $exception);
        $this->assertStringContainsString('has_access', $exception->getMessage());
        $this->assertStringContainsString('User:user-1', $exception->getMessage());
        $this->assertStringContainsString('Project:project-1', $exception->getMessage());
    }

    public function testInvalidMetadataException(): void
    {
        $metadata = ['invalid' => 'data'];
        $exception = new InvalidMetadataException('Test reason', $metadata);

        $this->assertInstanceOf(EdgeBinderException::class, $exception);
        $this->assertEquals('Invalid metadata: Test reason', $exception->getMessage());
        $this->assertEquals($metadata, $exception->invalidMetadata);
    }

    public function testInvalidMetadataExceptionSizeLimitExceeded(): void
    {
        $exception = InvalidMetadataException::sizeLimitExceeded(1000, 500);

        $this->assertStringContainsString('1000 bytes', $exception->getMessage());
        $this->assertStringContainsString('500 bytes', $exception->getMessage());
    }

    public function testInvalidMetadataExceptionInvalidFieldType(): void
    {
        $exception = InvalidMetadataException::invalidFieldType('field1', 'string', 'integer');

        $this->assertStringContainsString('field1', $exception->getMessage());
        $this->assertStringContainsString('string', $exception->getMessage());
        $this->assertStringContainsString('integer', $exception->getMessage());
    }

    public function testInvalidMetadataExceptionForbiddenField(): void
    {
        $metadata = ['forbidden_field' => 'value'];
        $exception = InvalidMetadataException::forbiddenField('forbidden_field', $metadata);

        $this->assertStringContainsString('forbidden_field', $exception->getMessage());
        $this->assertStringContainsString('not allowed', $exception->getMessage());
        $this->assertEquals($metadata, $exception->invalidMetadata);
    }

    public function testPersistenceException(): void
    {
        $exception = new PersistenceException('save', 'Connection timeout');

        $this->assertInstanceOf(EdgeBinderException::class, $exception);
        $this->assertEquals("Persistence operation 'save' failed: Connection timeout", $exception->getMessage());
    }

    public function testPersistenceExceptionStaticMethods(): void
    {
        $storeException = PersistenceException::storeFailed('Disk full');
        $this->assertStringContainsString('store', $storeException->getMessage());

        $findException = PersistenceException::findFailed('Index corrupted');
        $this->assertStringContainsString('find', $findException->getMessage());

        $deleteException = PersistenceException::deleteFailed('Permission denied');
        $this->assertStringContainsString('delete', $deleteException->getMessage());

        $updateException = PersistenceException::updateFailed('Lock timeout');
        $this->assertStringContainsString('update', $updateException->getMessage());

        $connectionException = PersistenceException::connectionFailed('Network unreachable');
        $this->assertStringContainsString('connection', $connectionException->getMessage());
    }

    public function testEntityExtractionException(): void
    {
        $entity = new \stdClass();
        $exception = new EntityExtractionException('Test reason', $entity);

        $this->assertInstanceOf(EdgeBinderException::class, $exception);
        $this->assertSame($entity, $exception->entity);
        $this->assertStringContainsString('stdClass', $exception->getMessage());
        $this->assertStringContainsString('Test reason', $exception->getMessage());
    }

    public function testEntityExtractionExceptionStaticMethods(): void
    {
        $entity = new \stdClass();

        $missingIdException = EntityExtractionException::missingId($entity);
        $this->assertStringContainsString('ID property', $missingIdException->getMessage());
        $this->assertSame($entity, $missingIdException->entity);

        $invalidIdException = EntityExtractionException::invalidId($entity, null);
        $this->assertStringContainsString('non-empty string', $invalidIdException->getMessage());
        $this->assertSame($entity, $invalidIdException->entity);

        $cannotDetermineTypeException = EntityExtractionException::cannotDetermineType($entity);
        $this->assertStringContainsString('determine entity type', $cannotDetermineTypeException->getMessage());
        $this->assertSame($entity, $cannotDetermineTypeException->entity);

        $invalidTypeException = EntityExtractionException::invalidType($entity, 123);
        $this->assertStringContainsString('non-empty string', $invalidTypeException->getMessage());
        $this->assertSame($entity, $invalidTypeException->entity);
    }

    public function testExceptionChaining(): void
    {
        $originalException = new \RuntimeException('Original error');

        $bindingException = new BindingNotFoundException('test-id', $originalException);
        $this->assertSame($originalException, $bindingException->getPrevious());

        $metadataException = new InvalidMetadataException('test reason', [], $originalException);
        $this->assertSame($originalException, $metadataException->getPrevious());

        $persistenceException = new PersistenceException('test', 'reason', $originalException);
        $this->assertSame($originalException, $persistenceException->getPrevious());

        $entityException = new EntityExtractionException('test', new \stdClass(), $originalException);
        $this->assertSame($originalException, $entityException->getPrevious());
    }
}
