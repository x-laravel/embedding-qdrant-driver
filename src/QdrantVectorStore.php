<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Database\Eloquent\Model;
use XLaravel\Embedding\Contracts\VectorStore;
use XLaravel\Embedding\Models\Embeddable as EmbeddableRecord;
use XLaravel\Embedding\Models\Embedding;
use XLaravel\Embedding\Storage\JsonVectorStore;

class QdrantVectorStore implements VectorStore
{
    public function __construct(
        private readonly JsonVectorStore $sql,
        private readonly QdrantClient $qdrant,
    ) {}

    public function store(Model $model, array $vector, string $slot): Embedding
    {
        $embedding = $this->sql->store($model, $vector, $slot);

        $payload = [
            'embeddable_type' => $model->getMorphClass(),
            'embeddable_id' => $model->getKey(),
            'slot' => $slot,
        ];

        $record = EmbeddableRecord::query()
            ->where('embeddable_type', $model->getMorphClass())
            ->where('embeddable_id', $model->getKey())
            ->first();

        if ($record !== null) {
            $payload[QdrantClient::PAYLOAD_KEY] = (object) $record->payload;
        }

        $this->qdrant->upsertPoints([[
            'id' => $embedding->getKey(),
            'vector' => $vector,
            'payload' => $payload,
        ]]);

        return $embedding;
    }
}
