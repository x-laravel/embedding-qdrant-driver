<?php

namespace XLaravel\Embedding\Driver\Qdrant\Tests\Feature;

use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Driver\Qdrant\QdrantDriver;
use XLaravel\Embedding\Driver\Qdrant\QdrantVectorStore;
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
}
