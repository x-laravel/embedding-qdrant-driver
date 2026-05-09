<?php

namespace XLaravel\Embedding\Driver\Qdrant\Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use XLaravel\Embedding\Driver\Qdrant\Tests\Fixtures\Models\Post;
use XLaravel\Embedding\Driver\Qdrant\Tests\TestCase;

class QdrantDriverTest extends TestCase
{
    public function test_it_returns_sorted_results_using_qdrant_search(): void
    {
        $post1 = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);
        $post2 = Post::create(['title' => 'Django', 'body' => 'Python Framework']);

        $queryVector = $post1->fresh()->embedding->vector;

        $results = Post::similarTo($queryVector, limit: 2);

        $this->assertCount(2, $results);
        $this->assertEquals($post1->id, $results->first()->id);
    }

    public function test_it_returns_model_instances_not_embedding_records(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $queryVector = $post->fresh()->embedding->vector;
        $results = Post::similarTo($queryVector, limit: 1);

        $this->assertInstanceOf(Post::class, $results->first());
    }

    public function test_it_returns_collection(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $queryVector = $post->fresh()->embedding->vector;
        $results = Post::similarTo($queryVector, limit: 10);

        $this->assertInstanceOf(Collection::class, $results);
    }

    public function test_similarity_score_is_returned_as_float(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $queryVector = $post->fresh()->embedding->vector;
        $results = Post::similarTo($queryVector, limit: 1);

        $this->assertIsFloat($results->first()->similarity_score);
    }

    public function test_identical_vector_returns_score_of_one(): void
    {
        $post = Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $queryVector = $post->fresh()->embedding->vector;
        $results = Post::similarTo($queryVector, limit: 1);

        $this->assertEqualsWithDelta(1.0, $results->first()->similarity_score, 0.0001);
    }

    public function test_it_respects_threshold(): void
    {
        Post::create(['title' => 'Laravel', 'body' => 'PHP Framework']);

        $results = Post::similarTo([1.0, 0.0], limit: 10, threshold: 2.0);

        $this->assertCount(0, $results);
    }

    public function test_it_respects_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Post::create(['title' => "Post {$i}", 'body' => 'Content']);
        }

        $queryVector = Post::first()->fresh()->embedding->vector;
        $results = Post::similarTo($queryVector, limit: 3);

        $this->assertCount(3, $results);
    }

    public function test_it_filters_by_ids(): void
    {
        $post1 = Post::create(['title' => 'P1', 'body' => 'C1']);
        $post2 = Post::create(['title' => 'P2', 'body' => 'C2']);

        $queryVector = $post1->fresh()->embedding->vector;
        $results = Post::similarTo($queryVector, limit: 10, where: fn ($q) => $q->where('id', $post2->id));

        $this->assertCount(1, $results);
        $this->assertEquals($post2->id, $results->first()->id);
    }

    public function test_it_returns_empty_collection_when_no_embeddings_exist(): void
    {
        Post::withoutEmbedding(fn () => Post::create(['title' => 'Laravel', 'body' => 'PHP']));

        $results = Post::similarTo([1.0, 0.0], limit: 10);

        $this->assertCount(0, $results);
    }
}
