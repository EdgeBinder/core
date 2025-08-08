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
    /**
     * @return array<string, string>
     */
    public function transformEntity(EntityCriteria $entity, string $direction): array
    {
        $typeKey = 'from' === $direction ? 'fromType' : 'toType';
        $idKey = 'from' === $direction ? 'fromId' : 'toId';

        return [
            $typeKey => $entity->type,
            $idKey => $entity->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transformWhere(WhereCriteria $where): array
    {
        return [
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
            'type' => $type,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function transformOrderBy(OrderByCriteria $orderBy): array
    {
        return [
            'field' => $orderBy->field,
            'direction' => $orderBy->direction,
        ];
    }

    /**
     * @param array<mixed>        $filters
     * @param array<array<mixed>> $orFilters
     *
     * @return array<string, mixed>
     */
    public function combineFilters(array $filters, array $orFilters = []): array
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

            // Collect order by conditions (check this first since they have both field and direction)
            if (isset($filter['field']) && isset($filter['direction'])) {
                $orderByConditions[] = $filter;
            }
            // Collect where conditions (only if not an orderBy condition)
            elseif (isset($filter['field'])) {
                $whereConditions[] = $filter;
            }
        }

        // Add collected conditions to criteria
        if (!empty($whereConditions)) {
            $criteria['where'] = $whereConditions;
        }

        if (!empty($orderByConditions)) {
            $criteria['orderBy'] = $orderByConditions;
        }

        // Add OR conditions
        if (!empty($orFilters)) {
            $criteria['orWhere'] = $orFilters;
        }

        return $criteria;
    }
}
