<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Throwable;
use XLaravel\Embedding\Contracts\VectorStoreMetrics;
use XLaravel\Embedding\Models\Embedding;

class QdrantVectorStoreMetrics implements VectorStoreMetrics
{
    public function __construct(protected QdrantClient $qdrant) {}

    public function snapshot(): array
    {
        $rows = Embedding::query()->count();

        try {
            $response = $this->qdrant->request()
                ->timeout(2)
                ->get("/collections/{$this->qdrant->collection()}");

            if ($response->ok()) {
                $points = $response->json('result.points_count');

                if (is_int($points)) {
                    $rows = $points;
                }
            }
        } catch (Throwable) {
            // Qdrant unreachable: keep the SQL-side count.
        }

        return [
            'rows' => $rows,
            'bytes' => null,
            'data_bytes' => null,
            'index_bytes' => null,
        ];
    }
}
