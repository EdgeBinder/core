<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Persistence\InMemory;

use EdgeBinder\Persistence\InMemory\InMemoryTransformer;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\QueryCriteria;
use EdgeBinder\Query\WhereCriteria;
use PHPUnit\Framework\TestCase;

class TransformerTest extends TestCase
{
    private InMemoryTransformer $transformer;
    
    protected function setUp(): void
    {
        $this->transformer = new InMemoryTransformer();
    }
    
    public function testTransformEntityFrom(): void
    {
        $entity = new EntityCriteria('User', 'user123');
        
        $result = $this->transformer->transformEntity($entity, 'from');
        
        $expected = [
            'fromType' => 'User',
            'fromId' => 'user123'
        ];
        
        $this->assertEquals($expected, $result);
    }
    
    public function testTransformWhere(): void
    {
        $where = new WhereCriteria('status', '=', 'active');
        
        $result = $this->transformer->transformWhere($where);
        
        $expected = [
            'field' => 'status',
            'operator' => '=',
            'value' => 'active'
        ];
        
        $this->assertEquals($expected, $result);
    }
    
    public function testCompleteQueryCriteriaTransformation(): void
    {
        $criteria = new QueryCriteria(
            from: new EntityCriteria('User', 'user123'),
            type: 'collaboration',
            where: [
                new WhereCriteria('status', '=', 'active')
            ]
        );
        
        $result = $criteria->transform($this->transformer);
        
        $expected = [
            'fromType' => 'User',
            'fromId' => 'user123',
            'type' => 'collaboration',
            'where' => [
                [
                    'field' => 'status',
                    'operator' => '=',
                    'value' => 'active'
                ]
            ]
        ];
        
        $this->assertEquals($expected, $result);
    }
}
