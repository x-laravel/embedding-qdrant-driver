<?php

namespace XLaravel\Embedding\Driver\Qdrant\Tests\Feature;

use Illuminate\Support\Facades\Http;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Driver\Qdrant\QdrantDriver;
use XLaravel\Embedding\Driver\Qdrant\QdrantVectorStore;
use XLaravel\Embedding\Driver\Qdrant\QdrantVectorStoreMetrics;
use XLaravel\Embedding\Driver\Qdrant\Tests\Fixtures\Models\Post;
use XLaravel\Embedding\Driver\Qdrant\Tests\TestCase;
use XLaravel\Embedding\SimilarityManager;

class QdrantEmbeddingServiceProviderTest extends TestCase
{
    public function test_it_registers_the_qdrant_driver(): void
    {
        $manager = app(SimilarityManager::class);

        $this->assertInstanceOf(QdrantDriver::class, $manager->driver('qdrant'));
    }

    public function test_it_can_be_set_as_the_default_driver(): void
    {
        $manager = app(SimilarityManager::class);
        $manager->forgetDrivers();

        config(['embedding.similarity.driver' => 'qdrant']);

        $this->assertInstanceOf(QdrantDriver::class, $manager->driver());
    }

    public function test_it_binds_qdrant_vector_store(): void
    {
        $this->assertInstanceOf(QdrantVectorStore::class, app(VectorStore::class));
    }

    public function test_it_stores_embedding_in_sql_and_qdrant(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $this->assertNotNull($post->fresh()->embedding);
        $this->assertIsArray($post->fresh()->embedding->vector);
    }

    public function test_it_binds_qdrant_vector_store_metrics(): void
    {
        $this->assertInstanceOf(QdrantVectorStoreMetrics::class, app(VectorStoreMetrics::class));
    }

    public function test_metrics_snapshot_reports_rows_from_qdrant_points_count(): void
    {
        Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $snapshot = app(VectorStoreMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['rows']);
        $this->assertNull($snapshot['bytes']);
        $this->assertNull($snapshot['data_bytes']);
        $this->assertNull($snapshot['index_bytes']);
    }

    public function test_metrics_snapshot_falls_back_to_sql_when_qdrant_unreachable(): void
    {
        \XLaravel\Embedding\Models\Embedding::create([
            'embeddable_type' => Post::class,
            'embeddable_id' => 1,
            'slot' => 'default',
            'vector' => [0.1, 0.2, 0.3],
        ]);

        Http::fake([
            '*/collections/*' => Http::response('boom', 500),
        ]);

        $snapshot = app(VectorStoreMetrics::class)->snapshot();

        $this->assertSame(1, $snapshot['rows']);
    }
}
