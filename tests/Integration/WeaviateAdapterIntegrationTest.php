<?php

declare(strict_types=1);

namespace EdgeBinder\Adapter\Weaviate\Tests\Integration;

use EdgeBinder\Adapter\Weaviate\WeaviateAdapter;
use EdgeBinder\Contracts\PersistenceAdapterInterface;
use EdgeBinder\Testing\AbstractAdapterTestSuite;
use Weaviate\WeaviateClient;

/**
 * Integration tests for WeaviateAdapter using the standard AbstractAdapterTestSuite.
 *
 * This test class now extends AbstractAdapterTestSuite instead of TestCase,
 * ensuring that WeaviateAdapter passes all the comprehensive integration tests
 * that would catch the query filtering bug mentioned in the proposal.
 *
 * These tests require a running Weaviate instance and test the full
 * integration between the adapter and Weaviate.
 *
 * @group integration
 */
class WeaviateAdapterIntegrationTest extends AbstractAdapterTestSuite
{
    private WeaviateClient $client;
    private string $testCollectionName;

    protected function createAdapter(): PersistenceAdapterInterface
    {
        $this->client = WeaviateClient::connectToLocal();
        $this->testCollectionName = 'TestBindings_'.uniqid();

        return new WeaviateAdapter($this->client, [
            'collection_name' => $this->testCollectionName,
            'schema' => [
                'auto_create' => true,
                'vectorizer' => 'none', // Disable vectorizer for testing
            ],
        ]);
    }

    protected function cleanupAdapter(): void
    {
        if (isset($this->client) && isset($this->testCollectionName)) {
            try {
                // Clean up test collection
                $this->client->collections()->delete($this->testCollectionName);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
}
