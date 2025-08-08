<?php

declare(strict_types=1);

namespace EdgeBinder\Contracts;

use EdgeBinder\Query\EntityCriteria;
use EdgeBinder\Query\OrderByCriteria;
use EdgeBinder\Query\WhereCriteria;

/**
 * Interface for transforming generic criteria into adapter-specific query formats.
 * 
 * Each adapter implements this interface to convert EdgeBinder's generic criteria
 * objects into their native query format (e.g., SQL queries, NoSQL filters, graph queries, etc.).
 */
interface CriteriaTransformerInterface
{
    /**
     * Transform an entity criteria into adapter-specific format.
     * 
     * @param EntityCriteria $entity The entity criteria to transform
     * @param string $direction Either 'from' or 'to' to indicate relationship direction
     * @return mixed Adapter-specific representation of entity filter
     */
    public function transformEntity(EntityCriteria $entity, string $direction): mixed;
    
    /**
     * Transform a where criteria into adapter-specific format.
     * 
     * @param WhereCriteria $where The where criteria to transform
     * @return mixed Adapter-specific representation of where condition
     */
    public function transformWhere(WhereCriteria $where): mixed;
    
    /**
     * Transform a binding type filter into adapter-specific format.
     * 
     * @param string $type The binding type to filter by
     * @return mixed Adapter-specific representation of binding type filter
     */
    public function transformBindingType(string $type): mixed;
    
    /**
     * Transform an order by criteria into adapter-specific format.
     * 
     * @param OrderByCriteria $orderBy The order by criteria to transform
     * @return mixed Adapter-specific representation of ordering
     */
    public function transformOrderBy(OrderByCriteria $orderBy): mixed;
    
    /**
     * Combine multiple filters into a single adapter-specific query.
     *
     * @param array $filters Array of adapter-specific filter objects
     * @param array $orFilters Array of OR condition groups
     * @return mixed Final adapter-specific query object
     */
    public function combineFilters(array $filters, array $orFilters = []): mixed;
}
