<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Support\ServiceProvider;
use XLaravel\Embedding\Contracts\PayloadStore;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\SimilarityManager;

class QdrantEmbeddingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
                __DIR__.'/../config/embedding-qdrant-driver.php' => config_path('embedding-qdrant-driver.php'),
            ], 'embedding-qdrant');
        }

        $this->app->resolving(SimilarityManager::class, function (SimilarityManager $manager) {
            $manager->extend('qdrant', fn ($app) => $app->make(QdrantDriver::class));
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/embedding-qdrant-driver.php', 'embedding.qdrant');

        $this->app->bind(VectorStore::class, QdrantVectorStore::class);
        $this->app->bind(PayloadStore::class, QdrantPayloadStore::class);
        $this->app->bind(VectorStoreMetrics::class, QdrantVectorStoreMetrics::class);
    }
}
