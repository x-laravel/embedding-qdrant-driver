<?php

namespace XLaravel\Embedding\Driver\Qdrant;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use XLaravel\Embedding\Contracts\SearchRequest;
use XLaravel\Embedding\Contracts\SimilarityDriver;
use XLaravel\Embedding\Models\Embeddable as EmbeddableRecord;

class QdrantDriver implements SimilarityDriver
{
    public function __construct(protected QdrantClient $qdrant) {}

    /**
     * Search for models similar to the request's query vector using Qdrant.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function search(Model $prototype, SearchRequest $request): Collection
    {
        $morphClass = $prototype->getMorphClass();

        $filter = [
            'must' => [
                ['key' => 'embeddable_type', 'match' => ['value' => $morphClass]],
                ['key' => 'slot', 'match' => ['value' => $request->slot]],
            ],
        ];

        if ($request->ids !== null) {
            if ($request->ids === []) {
                return new Collection;
            }

            $filter['must'][] = ['key' => 'embeddable_id', 'match' => ['any' => array_values($request->ids)]];
        }

        foreach ($request->filter ?? [] as $key => $value) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)) {
                throw new InvalidArgumentException("Invalid payload filter key [{$key}].");
            }

            if ($value === []) {
                return new Collection;
            }

            $filter['must'][] = $this->payloadCondition(QdrantClient::PAYLOAD_KEY.".{$key}", $value);
        }

        $body = [
            'vector' => $request->vector,
            'limit' => $request->limit,
            'with_payload' => ['embeddable_id'],
            'filter' => $filter,
        ];

        if ($request->threshold > 0.0) {
            $body['score_threshold'] = $request->threshold;
        }

        $scores = $this->liveScores($this->qdrant->search($body), $morphClass, ! empty($request->filter));

        $modelQuery = in_array(SoftDeletes::class, class_uses_recursive($prototype), true)
            ? $prototype::query()->withTrashed()
            : $prototype::query();

        return $modelQuery->findMany(array_keys($scores))
            ->each(fn ($m) => $m->setAttribute('similarity_score', $scores[$m->getKey()] ?? 0.0))
            ->sortByDesc(fn ($m) => $m->getAttribute('similarity_score'))
            ->values();
    }

    /**
     * Map hits to scores keyed by model key, keeping only points whose SQL
     * embedding record (and, for a filtered search, payload record) still exists.
     *
     * @param  array<int, array{id: int|string, score: float, payload?: array<string, mixed>}>  $hits
     * @return array<int|string, float>
     */
    protected function liveScores(array $hits, string $morphClass, bool $requirePayload): array
    {
        if ($hits === []) {
            return [];
        }

        $embeddingClass = config('embedding.model');
        $embedding = new $embeddingClass;

        $livePoints = array_flip($embeddingClass::query()
            ->whereKey(array_column($hits, 'id'))
            ->pluck($embedding->getKeyName())
            ->all());

        $hits = array_filter($hits, fn ($hit) => isset($livePoints[$hit['id']]));

        if ($requirePayload && $hits !== []) {
            $withPayload = array_flip(EmbeddableRecord::query()
                ->where('embeddable_type', $morphClass)
                ->whereIn('embeddable_id', array_map(fn ($hit) => $hit['payload']['embeddable_id'], $hits))
                ->pluck('embeddable_id')
                ->all());

            $hits = array_filter($hits, fn ($hit) => isset($withPayload[$hit['payload']['embeddable_id']]));
        }

        $scores = [];

        foreach ($hits as $hit) {
            $scores[$hit['payload']['embeddable_id']] = (float) $hit['score'];
        }

        return $scores;
    }

    /**
     * Scalars compare as equality, arrays as IN.
     *
     * @return array<string, mixed>
     */
    protected function payloadCondition(string $field, mixed $value): array
    {
        if (! is_array($value)) {
            return $this->payloadEquals($field, $value);
        }

        $strings = array_values(array_filter($value, 'is_string'));
        $integers = array_values(array_filter($value, 'is_int'));

        $conditions = array_map(
            fn ($candidate) => $this->payloadEquals($field, $candidate),
            array_values(array_filter($value, fn ($candidate) => ! is_string($candidate) && ! is_int($candidate))),
        );

        if ($strings !== []) {
            $conditions[] = ['key' => $field, 'match' => ['any' => $strings]];
        }

        if ($integers !== []) {
            $conditions[] = ['key' => $field, 'match' => ['any' => $integers]];
        }

        return count($conditions) === 1 ? $conditions[0] : ['should' => $conditions];
    }

    /**
     * Qdrant matches keyword, integer and bool values by JSON type, so 34 never matches "34".
     *
     * @return array<string, mixed>
     */
    protected function payloadEquals(string $field, mixed $value): array
    {
        if (is_string($value) || is_int($value) || is_bool($value)) {
            return ['key' => $field, 'match' => ['value' => $value]];
        }

        if (is_float($value)) {
            return ['key' => $field, 'range' => ['gte' => $value, 'lte' => $value]];
        }

        if ($value === null) {
            return ['is_null' => ['key' => $field]];
        }

        throw new InvalidArgumentException("Payload filter values must be scalar or arrays of scalars [{$field}].");
    }
}
