<?php

namespace XLaravel\Embedding\Driver\Qdrant\Tests;

use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Embeddings;
use Orchestra\Testbench\TestCase as Orchestra;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Driver\Qdrant\QdrantClient;
use XLaravel\Embedding\Driver\Qdrant\QdrantEmbeddingServiceProvider;
use XLaravel\Embedding\EmbeddingServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Embeddings::fake();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * @param  array<int, float>  $values
     * @return array<int, float>
     */
    protected function vector(array $values): array
    {
        return array_map('floatval', array_pad($values, (int) config('embedding.dimensions', 1536), 0.0));
    }

    /**
     * @param  array<int, float>  $vector
     */
    protected function setVector(Model $model, array $vector, string $slot = 'default'): void
    {
        app(VectorStore::class)->store($model, $vector, $slot);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pointPayload(Model $model, string $slot = 'default'): array
    {
        $qdrant = app(QdrantClient::class);

        return $qdrant->request()
            ->get("/collections/{$qdrant->collection()}/points/{$model->embedding($slot)->value('id')}")
            ->throw()
            ->json('result.payload');
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
