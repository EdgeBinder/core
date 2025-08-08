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
    /**
     * @return array<string, mixed>
     */
    public function transformEntity(EntityCriteria $entity, string $direction): array
    {
        return [
            'type' => 'entity',
            'direction' => $direction,
            'entityType' => $entity->type,
            'entityId' => $entity->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transformWhere(WhereCriteria $where): array
    {
        return [
            'type' => 'where',
            'field' => $where->field,
            'operator' => $where->operator,
            'value' => $where->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function transformBindingType(string $type): array
    {
        return [
            'type' => 'bindingType',
            'value' => $type,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function transformOrderBy(OrderByCriteria $orderBy): array
    {
        return [
            'type' => 'orderBy',
            'field' => $orderBy->field,
            'direction' => $orderBy->direction,
        ];
    }

    /**
     * @param array<mixed>        $filters
     * @param array<array<mixed>> $orFilters
     */
    public function combineFilters(array $filters, array $orFilters = []): mixed
    {
        return [
            'type' => 'combined',
            'filters' => $filters,
            'count' => count($filters),
        ];
    }
}
