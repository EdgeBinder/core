<?php

declare(strict_types=1);

namespace EdgeBinder\Testing;

use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\WhereCriteria;

/**
 * Weaviate-like transformer for demonstrating the pattern.
 * 
 * This simulates how a real Weaviate transformer would work,
 * converting criteria into Weaviate-like filter structures.
 */
class WeaviateLikeTransformer implements CriteriaTransformerInterface
{
    public function transformEntity(EntityCriteria $entity, string $direction): array
    {
        $typeProperty = $direction === 'from' ? 'fromEntityType' : 'toEntityType';
        $idProperty = $direction === 'from' ? 'fromEntityId' : 'toEntityId';
        
        return [
            'operator' => 'And',
            'operands' => [
                [
                    'path' => [$typeProperty],
                    'operator' => 'Equal',
                    'valueText' => $entity->type,
                ],
                [
                    'path' => [$idProperty],
                    'operator' => 'Equal',
                    'valueText' => $entity->id,
                ],
            ],
        ];
    }
    
    public function transformWhere(WhereCriteria $where): array
    {
        $operator = match($where->operator) {
            '=' => 'Equal',
            '!=' => 'NotEqual',
            '>' => 'GreaterThan',
            '<' => 'LessThan',
            '>=' => 'GreaterThanEqual',
            '<=' => 'LessThanEqual',
            'in' => 'ContainsAny',
            'not_in' => 'ContainsNone',
            'like' => 'Like',
            default => 'Equal',
        };
        
        $valueKey = is_string($where->value) ? 'valueText' : 
                   (is_int($where->value) || is_float($where->value) ? 'valueNumber' : 'valueText');
        
        return [
            'path' => [$where->field],
            'operator' => $operator,
            $valueKey => $where->value,
        ];
    }
    
    public function transformBindingType(string $type): array
    {
        return [
            'path' => ['bindingType'],
            'operator' => 'Equal',
            'valueText' => $type,
        ];
    }
    
    public function transformOrderBy(OrderByCriteria $orderBy): array
    {
        return [
            'path' => [$orderBy->field],
            'order' => $orderBy->direction === 'desc' ? 'desc' : 'asc',
        ];
    }
    
    public function combineFilters(array $filters): ?array
    {
        if (empty($filters)) {
            return null;
        }
        
        if (count($filters) === 1) {
            return $filters[0];
        }
        
        return [
            'operator' => 'And',
            'operands' => $filters,
        ];
    }
}
