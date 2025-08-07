<?php

declare(strict_types=1);

namespace EdgeBinder\Tests\Unit\Persistence\InMemory;

use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Persistence\InMemory\InMemoryAdapter;
use EdgeBinder\Testing\AbstractAdapterTestSuite;

/**
 * Integration tests for InMemoryAdapter using the standard AbstractAdapterTestSuite.
 *
 * This test class now extends AbstractAdapterTestSuite instead of TestCase,
 * ensuring that InMemoryAdapter passes all the comprehensive integration tests
 * that would catch bugs like the WeaviateAdapter query filtering issue.
 *
 * All the previous unit tests have been converted to integration tests in
 * AbstractAdapterTestSuite and are now executed through the EdgeBinder.
 */
final class InMemoryAdapterTest extends AbstractAdapterTestSuite
{
    protected function createAdapter(): PersistenceAdapterInterface
    {
        return new InMemoryAdapter();
    }

    protected function cleanupAdapter(): void
    {
        // InMemoryAdapter doesn't need cleanup as it's reset between tests
        // The adapter is recreated in setUp() for each test
    }
}
