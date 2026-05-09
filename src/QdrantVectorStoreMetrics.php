<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Support\Facades\Http;
use Throwable;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Models\Embedding;

class QdrantVectorStoreMetrics implements VectorStoreMetrics
{
    public function snapshot(): array
    {
        // SQL-side count is the always-available baseline. Qdrant's
        // /collections/{name} endpoint reports its own points_count, which
        // we prefer when reachable so an operator can spot dual-write drift
        // (Qdrant ahead/behind SQL).
        $rows = Embedding::query()->count();

        try {
            $collection = config('embedding.qdrant.collection', 'embeddings');
            $headers = ['Content-Type' => 'application/json'];

            if ($apiKey = config('embedding.qdrant.api_key')) {
                $headers['api-key'] = $apiKey;
            }

            $response = Http::baseUrl(config('embedding.qdrant.url', 'http://localhost:6333'))
                ->withHeaders($headers)
                ->timeout(2)
                ->get("/collections/{$collection}");

            if ($response->ok()) {
                $points = $response->json('result.points_count');

                if (is_int($points)) {
                    $rows = $points;
                }
            }
        } catch (Throwable) {
            // Qdrant unreachable / auth failure / collection missing —
            // fall back to the SQL-side count we already computed.
        }

        // Qdrant does not expose a stable "disk size" REST endpoint for a
        // collection, so byte fields stay null and embedding:status
        // renders them as "n/a".
        return [
            'rows' => $rows,
            'bytes' => null,
            'data_bytes' => null,
            'index_bytes' => null,
        ];
    }
}