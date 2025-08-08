<?php

declare(strict_types=1);

namespace EdgeBinder\Query;

use EdgeBinder\Contracts\CriteriaTransformerInterface;

/**
 * Immutable entity criteria for binding queries.
 *
 * Represents criteria for filtering bindings by entity (from or to).
 * Can transform itself using adapter-specific transformers.
 */
class EntityCriteria
{
    private mixed $transformedValue = null;
    private mixed $lastTransformer = null;
    private ?string $lastDirection = null;

    public function __construct(
        public readonly string $type,
        public readonly string $id
    ) {
    }

    /**
     * Transform this entity criteria using the provided transformer.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     * @param string                       $direction   Either 'from' or 'to' to indicate relationship direction
     *
     * @return mixed Adapter-specific representation of this entity criteria
     */
    public function transform(CriteriaTransformerInterface $transformer, string $direction = 'from'): mixed
    {
        // Cache based on transformer instance and direction to avoid re-transformation
        if (null === $this->transformedValue
            || $this->lastTransformer !== $transformer
            || $this->lastDirection !== $direction) {
            $this->transformedValue = $this->doTransform($transformer, $direction);
            $this->lastTransformer = $transformer;
            $this->lastDirection = $direction;
        }

        return $this->transformedValue;
    }

    /**
     * Perform the actual transformation by delegating to the transformer.
     *
     * @param CriteriaTransformerInterface $transformer The transformer to use
     * @param string                       $direction   The relationship direction
     *
     * @return mixed Transformed representation
     */
    protected function doTransform(CriteriaTransformerInterface $transformer, string $direction): mixed
    {
        return $transformer->transformEntity($this, $direction);
    }
}
