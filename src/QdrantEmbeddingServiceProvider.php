<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Support\ServiceProvider;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\SimilarityManager;
use XLaravel\Embedding\Storage\JsonVectorStore;

class QdrantEmbeddingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
                __DIR__.'/../config/embedding-qdrant-driver.php' => config_path('embedding-qdrant-driver.php'),
            ], 'embedding-qdrant');
        }

        $this->app->resolving(SimilarityManager::class, function (SimilarityManager $manager) {
            $manager->extend('qdrant', fn () => new QdrantDriver());
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/embedding-qdrant-driver.php', 'embedding.qdrant');

        $this->app->bind(VectorStore::class, function ($app) {
            return new QdrantVectorStore($app->make(JsonVectorStore::class));
        });
    }
}
