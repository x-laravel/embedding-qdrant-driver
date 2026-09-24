# Changelog

All notable changes to `x-laravel/embedding-qdrant-driver` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The package's major version follows `laravel/ai`.

## 1.0.0 - 2026-09-24

Initial release. Requires PHP ^8.3, Laravel ^12.0 | ^13.0, `x-laravel/embedding` ^1.0 and a Qdrant server.

### Added

- `QdrantDriver` — `qdrant` similarity driver running cosine search in Qdrant through the REST `points/search` endpoint, scoped by `embeddable_type`, `slot` and the optional ID restriction. `score_threshold` is sent only when `threshold > 0.0`. Hits whose SQL embedding record no longer exists are dropped, and soft-deleting models are loaded with `withTrashed()`.
- Payload `filter` translation for `similarTo()` / `similarToText()` / `mostSimilar()` into native Qdrant conditions on `payload.<key>`: `match.value` for equality, `match.any` for IN, `is_null` for `null`, ANDed in `must`. Matching is type-strict (`34` never matches `"34"`), an empty array matches nothing, records without a payload row never match, and filter keys are validated.
- `QdrantPayloadStore` — extends the core `DatabasePayloadStore`; keeps the `embeddables` row as the source of truth and mirrors the payload onto the entity's Qdrant points with `set_payload` / `delete_payload`.
- `QdrantVectorStore` — dual-writes embeddings to the SQL `embeddings` table and the Qdrant collection, using the embedding ID as the point ID and carrying the entity's current payload.
- `QdrantVectorStoreMetrics` — reports `rows` from the collection's `points_count`, falling back to the SQL count when Qdrant is unreachable.
- `QdrantClient` — shared HTTP client built on Laravel's `Http` facade, with optional `api-key` authentication.
- Qdrant collection migration (cosine distance, `embedding.dimensions` vector size) and config, published under the `embedding-qdrant` tag.
