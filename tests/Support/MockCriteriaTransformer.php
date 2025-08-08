<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Support;

use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\WhereCriteria;

/**
 * Mock transformer for testing the criteria transformation pattern.
 * 
 * This transformer converts criteria into simple array representations
 * that can be easily tested and verified.
 */
class MockCriteriaTransformer implements CriteriaTransformerInterface
{
    public function transformEntity(EntityCriteria $entity, string $direction): array
    {
        return [
            'type' => 'entity',
            'direction' => $direction,
            'entityType' => $entity->type,
            'entityId' => $entity->id,
        ];
    }
    
    public function transformWhere(WhereCriteria $where): array
    {
        return [
            'type' => 'where',
            'field' => $where->field,
            'operator' => $where->operator,
            'value' => $where->value,
        ];
    }
    
    public function transformBindingType(string $type): array
    {
        return [
            'type' => 'bindingType',
            'value' => $type,
        ];
    }
    
    public function transformOrderBy(OrderByCriteria $orderBy): array
    {
        return [
            'type' => 'orderBy',
            'field' => $orderBy->field,
            'direction' => $orderBy->direction,
        ];
    }
    
    public function combineFilters(array $filters): array
    {
        return [
            'type' => 'combined',
            'filters' => $filters,
            'count' => count($filters),
        ];
    }
}
