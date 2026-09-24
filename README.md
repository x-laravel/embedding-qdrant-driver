# x-laravel/embedding — Qdrant Driver

[![Tests](https://github.com/x-laravel/embedding-qdrant-driver/actions/workflows/tests.yml/badge.svg)](https://github.com/x-laravel/embedding-qdrant-driver/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

[Qdrant](https://qdrant.tech) vector database driver for [x-laravel/embedding](https://github.com/x-laravel/embedding).

## How It Works

- Implements `SimilarityDriver` — registers as the `qdrant` driver, similarity search runs entirely in Qdrant using its native ANN (Approximate Nearest Neighbor) engine
- Implements `VectorStore` — writes embeddings to both the SQL `embeddings` table (for Eloquent relationships) and the Qdrant collection (for search)
- Implements `PayloadStore` — keeps payload records in the SQL `embeddables` table and mirrors them onto the entity's Qdrant points, so payload `filter:` constraints run natively in Qdrant

## Requirements

- PHP ^8.3
- Laravel ^12.0 | ^13.0
- `x-laravel/embedding ^1.0`
- Qdrant server (self-hosted or [Qdrant Cloud](https://cloud.qdrant.io))

## Installation

```bash
composer require x-laravel/embedding-qdrant-driver
```

The `QdrantEmbeddingServiceProvider` is auto-discovered and registers the `qdrant` driver automatically.

## Setup

### 1. Configure x-laravel/embedding

Publish the config if you haven't already:

```bash
php artisan vendor:publish --tag=embedding-config
```

Set the similarity driver in `config/embedding.php`:

```php
'similarity' => [
    'driver' => env('EMBEDDING_SIMILARITY_DRIVER', 'qdrant'),
],
```

### 2. Configure Qdrant

Set the following environment variables:

```env
EMBEDDING_QDRANT_URL=http://localhost:6333
EMBEDDING_QDRANT_COLLECTION=embeddings
EMBEDDING_QDRANT_API_KEY=          # required for Qdrant Cloud
```

Or publish the config to `config/embedding-qdrant-driver.php` for full customisation:

```bash
php artisan vendor:publish --tag=embedding-qdrant
```

### 3. Create the SQL tables and the Qdrant collection

Migrations are not loaded automatically. Publish the core migrations (`embeddings` and `embeddables` tables) and this driver's Qdrant collection migration, then run them:

```bash
php artisan vendor:publish --tag=embedding-migrations
php artisan vendor:publish --tag=embedding-qdrant
php artisan migrate
```

The `embedding-qdrant` tag also publishes `config/embedding-qdrant-driver.php`.

> **Note:** The Qdrant collection is created with cosine similarity. The `EMBEDDING_DIMENSIONS` config value sets the vector size — it must match your AI model's output dimension.

### 4. Model

Follow the standard `x-laravel/embedding` setup. No Qdrant-specific changes are needed on your models.

```php
use XLaravel\Embedding\Attributes\EmbedOn;
use XLaravel\Embedding\Concerns\Embeddable;
use XLaravel\Embedding\Contracts\HasEmbeddings;

#[EmbedOn(['title', 'body'])]
class Post extends Model implements HasEmbeddings
{
    use Embeddable;

    public function toEmbeddingText(string $slot = 'default'): string
    {
        return $this->title.' '.$this->body;
    }
}
```

## Usage

The driver is transparent — use the standard `x-laravel/embedding` API:

```php
Post::similarToText('web framework', limit: 10);
Post::similarTo($vector, limit: 10, threshold: 0.8);
Post::rankByRelevance($posts, 'web framework');

$post->mostSimilar(limit: 5);
$post->similarityTo($otherPost);
```

All methods set a `similarity_score` float attribute on each returned model. A `threshold` of `0.0` returns every match; a positive value is passed to Qdrant as `score_threshold`.

### Payload filtering

Models using `#[EmbedPayload]` can filter similarity searches inside Qdrant:

```php
use XLaravel\Embedding\Attributes\EmbedPayload;

#[EmbedOn('name')]
#[EmbedPayload(['province_id', 'category_id', 'active'])]
class Venue extends Model implements HasEmbeddings { ... }

Venue::similarTo($vector, limit: 300, filter: ['province_id' => 34]);            // equality
Venue::similarToText('kebap', filter: ['category_id' => [3, 7]]);                // IN
$venue->mostSimilar(limit: 5, filter: ['province_id' => 34, 'active' => true]);  // AND
```

How the payload reaches Qdrant:

- The SQL `embeddables` table stays the source of truth. `QdrantPayloadStore` extends the core `DatabasePayloadStore`: after writing the row it sets the same payload under the `payload` key of every Qdrant point of that entity (all slots), and removes that key when the row is deleted.
- `QdrantVectorStore` reads the entity's current `embeddables` row when it upserts a point, so a point written after the payload sync carries the payload too.
- The driver turns the filter into Qdrant conditions on `payload.<key>`: `match.value` for equality, `match.any` for IN, `is_null` for `null`, all ANDed in `must`. An empty array matches nothing.
- Qdrant matches by JSON type, so `34` never matches `"34"` and `true` never matches `1`. Records without a payload row never match a filtered search.
- Hits are checked against the SQL tables before models are loaded: points whose `embeddings` row is gone, or (for a filtered search) whose `embeddables` row is gone, are dropped.

Filter keys must match `^[A-Za-z_][A-Za-z0-9_]*$`. For large collections, create Qdrant payload indexes on `embeddable_type`, `slot` and the `payload.<key>` fields you filter on.

> **Note:** Qdrant points are not deleted when their `embeddings` rows are: deleting a model, `embedding:vector:clear` and `embedding:vector:clean` remove SQL rows only, and `embedding:payload:clear` / `embedding:payload:clean` bypass the `PayloadStore`. Search results stay correct because hits are checked against SQL, but the stale points remain in the collection and count against `limit`. Run `embedding:payload:sync --force` after clearing payloads if you want Qdrant back in step.

## Testing

```bash
# Build first (once per PHP version)
DOCKER_BUILDKIT=0 docker compose --profile php83 build

# Run tests
docker compose --profile php83 up
docker compose --profile php84 up
docker compose --profile php85 up
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
