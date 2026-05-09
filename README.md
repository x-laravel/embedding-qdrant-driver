# x-laravel/embedding — Qdrant Driver

[![Tests](https://github.com/x-laravel/embedding-qdrant-driver/actions/workflows/tests.yml/badge.svg)](https://github.com/x-laravel/embedding-qdrant-driver/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

[Qdrant](https://qdrant.tech) vector database driver for [x-laravel/embedding](https://github.com/x-laravel/embedding).

## How It Works

- Implements `SimilarityDriver` — registers as the `qdrant` driver, similarity search runs entirely in Qdrant using its native ANN (Approximate Nearest Neighbor) engine
- Implements `VectorStore` — writes embeddings to both the SQL `embeddings` table (for Eloquent relationships) and the Qdrant collection (for search)

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

### 3. Create the Qdrant collection and SQL table

```bash
php artisan migrate
```

This publishes both the SQL `embeddings` table migration (from the core package) and creates the Qdrant collection.

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

    public function toEmbeddingText(): string
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

All methods set a `similarity_score` float attribute on each returned model.

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
