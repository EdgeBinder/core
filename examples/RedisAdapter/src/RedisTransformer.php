<?php

declare(strict_types=1);

namespace MyVendor\RedisAdapter;

use EdgeBinder\Contracts\CriteriaTransformerInterface;
use EdgeBinder\Query\{EntityCriteria, WhereCriteria, OrderByCriteria};

/**
 * Redis-specific criteria transformer for EdgeBinder v0.6.2
 *
 * Transforms EdgeBinder query criteria into Redis-compatible query format.
 * This is a reference implementation showing the v0.6.2 transformer pattern.
 */
class RedisTransformer implements CriteriaTransformerInterface
{
    public function transformEntity(EntityCriteria $entity, string $direction): mixed
    {
        // Convert to Redis key pattern for entity filtering
        return [
            'pattern' => "entity:{$entity->entityType}:{$entity->entityId}:*",
            'direction' => $direction,
            'entityType' => $entity->entityType,
            'entityId' => $entity->entityId
        ];
    }

    public function transformWhere(WhereCriteria $where): mixed
    {
        // Convert to Redis filtering format
        return [
            'field' => $where->field,
            'operator' => $this->mapOperator($where->operator),
            'value' => $where->value
        ];
    }

    public function transformOrderBy(OrderByCriteria $orderBy): mixed
    {
        // Convert to Redis sort format
        return [
            'by' => $orderBy->field,
            'order' => strtoupper($orderBy->direction)
        ];
    }

    public function transformBindingType(string $type): mixed
    {
        return [
            'type_pattern' => "type:{$type}",
            'type' => $type
        ];
    }

    public function combineFilters(array $filters, array $orFilters = []): mixed
    {
        $combined = [
            'and' => $filters,
            'or' => $orFilters
        ];
        
        return $combined;
    }

    /**
     * Map EdgeBinder operators to Redis-compatible operators
     */
    private function mapOperator(string $operator): string
    {
        return match($operator) {
            '=' => 'eq',
            '!=' => 'ne',
            '>' => 'gt',
            '<' => 'lt',
            '>=' => 'gte',
            '<=' => 'lte',
            'in' => 'in',
            'notIn' => 'nin',
            'between' => 'between',
            'exists' => 'exists',
            'null' => 'is_null',
            'notNull' => 'not_null',
            default => throw new \InvalidArgumentException("Unsupported operator: $operator")
        };
    }
}
