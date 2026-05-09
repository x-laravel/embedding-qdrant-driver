<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use XLaravel\Embedding\Contracts\SimilarityDriver;

class QdrantDriver implements SimilarityDriver
{
    public function search(Model $prototype, array $queryVector, int $limit, float $threshold = 0.0, ?array $ids = null, string $slot = 'default'): Collection
    {
        $collection = config('embedding.qdrant.collection', 'embeddings');

        $filter = [
            'must' => [
                ['key' => 'embeddable_type', 'match' => ['value' => $prototype->getMorphClass()]],
                ['key' => 'slot',            'match' => ['value' => $slot]],
            ],
        ];

        if ($ids !== null) {
            $filter['must'][] = ['key' => 'embeddable_id', 'match' => ['any' => $ids]];
        }

        $body = [
            'vector' => $queryVector,
            'limit' => $limit,
            'with_payload' => true,
            'filter' => $filter,
        ];

        if ($threshold > 0.0) {
            $body['score_threshold'] = $threshold;
        }

        $results = $this->client()
            ->post("/collections/{$collection}/points/search", $body)
            ->json('result', []);

        $matchedIds = [];
        $scores = [];

        foreach ($results as $result) {
            $modelId = $result['payload']['embeddable_id'];
            $matchedIds[] = $modelId;
            $scores[$modelId]   = (float) $result['score'];
        }

        return $prototype::findMany($matchedIds)
            ->each(fn ($m) => $m->setAttribute('similarity_score', $scores[$m->getKey()] ?? 0.0))
            ->sortByDesc(fn ($m) => $m->getAttribute('similarity_score'))
            ->values();
    }

    private function client()
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($apiKey = config('embedding.qdrant.api_key')) {
            $headers['api-key'] = $apiKey;
        }

        return Http::baseUrl(config('embedding.qdrant.url', 'http://localhost:6333'))
            ->withHeaders($headers);
    }
}
