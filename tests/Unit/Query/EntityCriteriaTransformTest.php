<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Query;

use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Tests\Support\MockCriteriaTransformer;
use PHPUnit\Framework\TestCase;

/**
 * Test entity criteria transformation with different transformers.
 */
class EntityCriteriaTransformTest extends TestCase
{
    public function testTransformWithMockTransformer(): void
    {
        $entity = new EntityCriteria('User', 'user123');
        $transformer = new MockCriteriaTransformer();
        
        $result = $entity->transform($transformer, 'from');
        
        $this->assertEquals([
            'type' => 'entity',
            'direction' => 'from',
            'entityType' => 'User',
            'entityId' => 'user123',
        ], $result);
    }
    

    
    public function testTransformCaching(): void
    {
        $entity = new EntityCriteria('User', 'user123');
        $transformer = $this->createMock(MockCriteriaTransformer::class);
        
        // Should only call transformEntity once due to caching
        $transformer->expects($this->once())
            ->method('transformEntity')
            ->with($entity, 'from')
            ->willReturn(['cached' => 'result']);
        
        $result1 = $entity->transform($transformer, 'from');
        $result2 = $entity->transform($transformer, 'from');  // Should use cache
        
        $this->assertSame($result1, $result2);
        $this->assertEquals(['cached' => 'result'], $result1);
    }
}
