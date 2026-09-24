<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Embedding\Storage\DatabasePayloadStore;

class QdrantPayloadStore extends DatabasePayloadStore
{
    public function __construct(protected QdrantClient $qdrant) {}

    public function upsert(Model $model, array $payload): void
    {
        parent::upsert($model, $payload);

        $this->qdrant->setPayload([QdrantClient::PAYLOAD_KEY => (object) $payload], $this->pointsOf($model));
    }

    public function delete(Model $model): void
    {
        parent::delete($model);

        $this->qdrant->deletePayload([QdrantClient::PAYLOAD_KEY], $this->pointsOf($model));
    }

    /**
     * @return array<string, mixed>
     */
    protected function pointsOf(Model $model): array
    {
        return [
            'must' => [
                ['key' => 'embeddable_type', 'match' => ['value' => $model->getMorphClass()]],
                ['key' => 'embeddable_id', 'match' => ['value' => $model->getKey()]],
            ],
        ];
    }
}
