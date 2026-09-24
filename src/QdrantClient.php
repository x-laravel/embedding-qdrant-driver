<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class QdrantClient
{
    /**
     * The point payload key that holds the entity's embedding payload.
     */
    public const PAYLOAD_KEY = 'payload';

    public function collection(): string
    {
        return config('embedding.qdrant.collection', 'embeddings');
    }

    public function request(): PendingRequest
    {
        $request = Http::baseUrl(config('embedding.qdrant.url', 'http://localhost:6333'))->asJson();

        if ($apiKey = config('embedding.qdrant.api_key')) {
            $request->withHeaders(['api-key' => $apiKey]);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, array{id: int|string, score: float, payload?: array<string, mixed>}>
     */
    public function search(array $body): array
    {
        return $this->request()
            ->post("/collections/{$this->collection()}/points/search", $body)
            ->throw()
            ->json('result', []);
    }

    /**
     * @param  array<int, array{id: int|string, vector: array<int, float>, payload: array<string, mixed>}>  $points
     */
    public function upsertPoints(array $points): void
    {
        $this->request()
            ->put("/collections/{$this->collection()}/points?wait=true", ['points' => $points])
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $filter
     */
    public function setPayload(array $payload, array $filter): void
    {
        $this->request()
            ->post("/collections/{$this->collection()}/points/payload?wait=true", [
                'payload' => $payload,
                'filter' => $filter,
            ])
            ->throw();
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $filter
     */
    public function deletePayload(array $keys, array $filter): void
    {
        $this->request()
            ->post("/collections/{$this->collection()}/points/payload/delete?wait=true", [
                'keys' => $keys,
                'filter' => $filter,
            ])
            ->throw();
    }
}
