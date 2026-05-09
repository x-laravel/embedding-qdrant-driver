<?php

namespace XLaravel\Embedding\Driver\Qdrant\Tests;

use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Embeddings;
use Orchestra\Testbench\TestCase as Orchestra;
use XLaravel\Embedding\EmbeddingServiceProvider;
use XLaravel\Embedding\Driver\Qdrant\QdrantEmbeddingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Embeddings::fake();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            EmbeddingServiceProvider::class,
            QdrantEmbeddingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.providers.openai', [
            'driver' => 'openai',
            'api_key' => 'fake-api-key-for-testing',
        ]);
        $app['config']->set('ai.default_for_embeddings', 'openai');

        $app['config']->set('embedding.database.connection', 'sqlite');
        $app['config']->set('embedding.queue.connection', 'sync');
        $app['config']->set('embedding.similarity.driver', 'qdrant');
        $app['config']->set('embedding.qdrant.url', env('EMBEDDING_QDRANT_URL', 'http://127.0.0.1:6333'));
        $app['config']->set('embedding.qdrant.collection', env('EMBEDDING_QDRANT_COLLECTION', 'embeddings_test'));
    }
}
