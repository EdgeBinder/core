<?php

declare(strict_types=1);

namespace EdgeBinder\Persistence\InMemory;

use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\WhereCriteria;

/**
 * Transformer for InMemoryAdapter.
 * 
 * Converts EdgeBinder criteria objects into the array format
 * that the InMemoryAdapter expects for filtering.
 */
class InMemoryTransformer implements CriteriaTransformerInterface
{
    public function transformEntity(EntityCriteria $entity, string $direction): array
    {
        $typeKey = $direction === 'from' ? 'fromType' : 'toType';
        $idKey = $direction === 'from' ? 'fromId' : 'toId';
        
        return [
            $typeKey => $entity->type,
            $idKey => $entity->id,
        ];
    }
    
    public function transformWhere(WhereCriteria $where): array
    {
        return [
            'field' => $where->field,
            'operator' => $where->operator,
            'value' => $where->value,
        ];
    }
    
    public function transformBindingType(string $type): array
    {
        return [
            'type' => $type,
        ];
    }
    
    public function transformOrderBy(OrderByCriteria $orderBy): array
    {
        return [
            'field' => $orderBy->field,
            'direction' => $orderBy->direction,
        ];
    }
    
    public function combineFilters(array $filters): array
    {
        $criteria = [];
        $whereConditions = [];
        $orderByConditions = [];
        
        foreach ($filters as $filter) {
            // Merge entity filters (fromType, fromId, toType, toId)
            if (isset($filter['fromType'])) {
                $criteria['fromType'] = $filter['fromType'];
            }
            if (isset($filter['fromId'])) {
                $criteria['fromId'] = $filter['fromId'];
            }
            if (isset($filter['toType'])) {
                $criteria['toType'] = $filter['toType'];
            }
            if (isset($filter['toId'])) {
                $criteria['toId'] = $filter['toId'];
            }
            
            // Handle binding type
            if (isset($filter['type'])) {
                $criteria['type'] = $filter['type'];
            }
            
            // Collect where conditions
            if (isset($filter['field'])) {
                $whereConditions[] = $filter;
            }
            
            // Collect order by conditions
            if (isset($filter['field']) && isset($filter['direction'])) {
                $orderByConditions[] = $filter;
            }
        }
        
        // Add collected conditions to criteria
        if (!empty($whereConditions)) {
            $criteria['where'] = $whereConditions;
        }
        
        if (!empty($orderByConditions)) {
            $criteria['orderBy'] = $orderByConditions;
        }
        
        return $criteria;
    }
}
