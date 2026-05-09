<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Models\Embedding;
use XLaravel\Embedding\Storage\JsonVectorStore;

class QdrantVectorStore implements VectorStore
{
    public function __construct(private readonly JsonVectorStore $sql) {}

    public function store(Model $model, array $vector, string $slot): Embedding
    {
        $embedding = $this->sql->store($model, $vector, $slot);
        $collection = config('embedding.qdrant.collection', 'embeddings');

        $this->client()->put("/collections/{$collection}/points", [
            'points' => [[
                'id' => $embedding->id,
                'vector' => $vector,
                'payload' => [
                    'embeddable_type' => $model->getMorphClass(),
                    'embeddable_id' => $model->getKey(),
                    'slot' => $slot,
                ],
            ]],
        ]);

        return $embedding;
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
