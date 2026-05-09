# CLAUDE.md — embedding-qdrant-driver

This file provides guidance to Claude Code (claude.ai/code) when working with this repository.

## Overview

Qdrant vector database driver for `x-laravel/embedding`. Handles similarity search via Qdrant's native ANN engine and dual-writes embeddings to both SQL and Qdrant.

- **Package name:** `x-laravel/embedding-qdrant-driver` — **Namespace:** `XLaravel\Embedding\Driver\Qdrant`
- PHP `^8.3`, Laravel (illuminate) `^12.0|^13.0`, `x-laravel/embedding ^1.0`
- Qdrant server (self-hosted or Qdrant Cloud)
- Dev: Orchestra Testbench `^10.0|^11.0`, PHPUnit `^11.0|^12.0`

## Running Tests

```bash
# Build once per PHP version
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run all tests
docker compose --profile php83 up   # PHP 8.3
docker compose --profile php84 up   # PHP 8.4
docker compose --profile php85 up   # PHP 8.5

# Run a single test class or method
docker compose --profile php83 run --rm php83 vendor/bin/phpunit --filter QdrantDriverTest
docker compose --profile php83 run --rm php83 vendor/bin/phpunit --filter test_identical_vector_returns_score_of_one
```

Tests use SQLite for the SQL side and a local Qdrant container for similarity search. CI runs PHP 8.3–8.5 via `.github/workflows/tests.yml`.

## Source Files (`src/`)

| File | Responsibility |
|------|----------------|
| `QdrantDriver.php` | Implements `SimilarityDriver`. Calls Qdrant's `/points/search` REST API with payload filters, maps results back to Eloquent models. |
| `QdrantVectorStore.php` | Implements `VectorStore`. Dual-writes: calls `JsonVectorStore` for SQL, then upserts the point to Qdrant via `/collections/{collection}/points`. Returns the SQL `Embedding` record. |
| `QdrantEmbeddingServiceProvider.php` | `register()` merges `config/embedding-qdrant-driver.php` and binds `VectorStore` → `QdrantVectorStore`. `boot()` registers `qdrant` similarity driver, loads migration, publishes under `embedding-qdrant` tag. |

## Test Structure (`tests/`)

| Path | Purpose |
|------|---------|
| `TestCase.php` | Base test case. SQLite for SQL side, Qdrant for search. Sets `embedding.similarity.driver = qdrant`. |
| `Models/Post.php` | Fixture model using `#[EmbedOn]` and `Embeddable` trait. |
| `database/migrations/` | Creates SQL `embeddings` table (JSON vector) and `posts` table for tests. |
| `Feature/QdrantDriverTest.php` | Tests similarity search end-to-end through Qdrant. |
| `Feature/QdrantEmbeddingServiceProviderTest.php` | Tests driver registration, `VectorStore` binding, dual-write storage. |

## Driver Lifecycle

```
register()
  ├─► mergeConfigFrom(config/embedding-qdrant-driver.php, 'embedding.qdrant')
  └─► app->bind(VectorStore::class, QdrantVectorStore::class)

boot()
  ├─► loadMigrationsFrom(...)
  ├─► publishes([migrations, config], 'embedding-qdrant')
  └─► SimilarityManager::extend('qdrant', fn() => new QdrantDriver())
```

## Key Design Decisions

**Dual-write storage:** `QdrantVectorStore` calls `JsonVectorStore::store()` first (SQL), then upserts to Qdrant. The SQL `Embedding` record is returned (type is `Embedding` — no core contract changes needed). If Qdrant is unreachable, an exception propagates before the record is returned.

**Qdrant point ID = SQL embedding ID:** The auto-increment `embeddings.id` is used as the Qdrant point ID (unsigned integer). This allows mapping search results back without an extra SQL query.

**Payload filtering:** Each Qdrant point carries `embeddable_type`, `embeddable_id`, and `slot` as payload. The driver filters on all three during search, supporting multi-slot models and polymorphism correctly.

**Cosine similarity:** Qdrant is configured with `distance: Cosine`. Qdrant returns the cosine similarity score directly (not distance) — `1.0` = identical, `0.0` = orthogonal. The score is set directly as `similarity_score`.

**No custom Embedding model:** Unlike the MongoDB driver, Qdrant works alongside the standard SQL `Embedding` model. No `VectorStore` return type changes are needed in the core.

**`EMBEDDING_SIMILARITY_DRIVER`:** Auto-detection cannot detect Qdrant (there is no `qdrant` DB connection). Users must explicitly set `EMBEDDING_SIMILARITY_DRIVER=qdrant` or configure it in `config/embedding.php`.

**HTTP client:** Uses Laravel's built-in `Illuminate\Support\Facades\Http` (Guzzle wrapper) — no extra Qdrant PHP client dependency.

## Migration

Run the core SQL migration AND the Qdrant collection migration:

```bash
composer require x-laravel/embedding-qdrant-driver
php artisan vendor:publish --tag=embedding-migrations     # SQL table
php artisan vendor:publish --tag=embedding-qdrant         # Qdrant collection + config
php artisan migrate
```

## Git Commits

Never create a commit unless the user explicitly requests it.
