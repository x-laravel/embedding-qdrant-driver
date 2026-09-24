<?php

namespace XLaravel\Embedding\Driver\Qdrant\Tests\Feature;

use Closure;
use InvalidArgumentException;
use XLaravel\Embedding\Contracts\PayloadStore;
use XLaravel\Embedding\Driver\Qdrant\Tests\Fixtures\Models\VenueWithPayload;
use XLaravel\Embedding\Driver\Qdrant\Tests\TestCase;
use XLaravel\Embedding\Models\Embeddable as EmbeddableRecord;

class QdrantPayloadFilterTest extends TestCase
{
    private VenueWithPayload $alpha;

    private VenueWithPayload $beta;

    private VenueWithPayload $gamma;

    private VenueWithPayload $nullProvince;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = VenueWithPayload::create([
            'name' => 'Alpha', 'province_id' => 34, 'category_id' => 3, 'active' => true, 'code' => '34',
        ]);
        $this->beta = VenueWithPayload::create([
            'name' => 'Beta', 'province_id' => 6, 'category_id' => 3, 'active' => false, 'code' => 'B',
        ]);
        $this->gamma = VenueWithPayload::create([
            'name' => 'Gamma', 'province_id' => 34, 'category_id' => 7, 'active' => true,
        ]);
        $this->nullProvince = VenueWithPayload::create([
            'name' => 'NullProvince', 'province_id' => null, 'category_id' => 3, 'active' => true,
        ]);

        foreach ([$this->alpha, $this->beta, $this->gamma, $this->nullProvince] as $venue) {
            $this->setVector($venue, $this->vector([1.0, 0.0, 0.0]));
        }
    }

    /**
     * @return array<int, int>
     */
    private function search(?array $filter = null, ?Closure $where = null, float $threshold = 0.0, int $limit = 10): array
    {
        return VenueWithPayload::similarTo(
            $this->vector([1.0, 0.0, 0.0]),
            limit: $limit,
            threshold: $threshold,
            where: $where,
            filter: $filter,
        )->pluck('id')->sort()->values()->all();
    }

    public function test_no_filter_returns_all(): void
    {
        $this->assertSame(
            [$this->alpha->id, $this->beta->id, $this->gamma->id, $this->nullProvince->id],
            $this->search(),
        );
    }

    public function test_equality_filter(): void
    {
        $this->assertSame(
            [$this->alpha->id, $this->gamma->id],
            $this->search(['province_id' => 34]),
        );
    }

    public function test_in_filter(): void
    {
        $this->assertSame(
            [$this->alpha->id, $this->beta->id, $this->gamma->id],
            $this->search(['province_id' => [34, 6]]),
        );

        $this->assertSame(
            [$this->gamma->id],
            $this->search(['category_id' => [7, 99]]),
        );
    }

    public function test_in_filter_with_mixed_types_stays_type_strict(): void
    {
        $this->assertSame(
            [$this->alpha->id, $this->beta->id],
            $this->search(['code' => ['34', 'B', 34]]),
        );

        $this->assertSame([$this->beta->id], $this->search(['code' => [34, 'B']]));
    }

    public function test_empty_in_filter_matches_nothing(): void
    {
        $this->assertSame([], $this->search(['province_id' => []]));
    }

    public function test_and_filter(): void
    {
        $this->assertSame(
            [$this->alpha->id],
            $this->search(['province_id' => 34, 'category_id' => 3]),
        );
    }

    public function test_null_payload_value_never_matches(): void
    {
        $this->assertSame([$this->beta->id], $this->search(['province_id' => 6]));
        $this->assertNotContains($this->nullProvince->id, $this->search(['province_id' => [6, 34]]));
    }

    public function test_null_filter_matches_null_payload_value(): void
    {
        $this->assertSame([$this->nullProvince->id], $this->search(['province_id' => null]));
    }

    public function test_integer_filter_does_not_match_string_payload_value(): void
    {
        $this->assertSame([], $this->search(['code' => 34]));
        $this->assertSame([$this->alpha->id], $this->search(['code' => '34']));
    }

    public function test_string_filter_does_not_match_integer_payload_value(): void
    {
        $this->assertSame([], $this->search(['province_id' => '34']));
    }

    public function test_boolean_filter(): void
    {
        $this->assertSame(
            [$this->alpha->id, $this->gamma->id, $this->nullProvince->id],
            $this->search(['active' => true]),
        );
        $this->assertSame([$this->beta->id], $this->search(['active' => false]));
        $this->assertSame([], $this->search(['active' => 1]));
    }

    public function test_invalid_filter_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->search(['province_id") OR ("1' => 34]);
    }

    public function test_record_without_payload_row_never_matches_a_filtered_search(): void
    {
        EmbeddableRecord::query()
            ->where('embeddable_type', VenueWithPayload::class)
            ->where('embeddable_id', $this->gamma->id)
            ->delete();

        $this->assertSame([$this->alpha->id], $this->search(['province_id' => 34]));

        $this->assertContains($this->gamma->id, $this->search());
    }

    public function test_payload_store_delete_removes_the_payload_from_qdrant(): void
    {
        app(PayloadStore::class)->delete($this->gamma);

        $this->assertArrayNotHasKey('payload', $this->pointPayload($this->gamma));
        $this->assertSame([$this->alpha->id], $this->search(['province_id' => 34]));
        $this->assertContains($this->gamma->id, $this->search());
    }

    public function test_payload_is_mirrored_to_the_qdrant_point(): void
    {
        $payload = $this->pointPayload($this->alpha)['payload'];
        ksort($payload);

        $this->assertSame(
            ['active' => true, 'category_id' => 3, 'code' => '34', 'province_id' => 34],
            $payload,
        );
    }

    public function test_payload_update_reaches_the_qdrant_point(): void
    {
        $this->alpha->update(['province_id' => 6]);

        $this->assertSame(6, $this->pointPayload($this->alpha)['payload']['province_id']);
        $this->assertSame([$this->alpha->id, $this->beta->id], $this->search(['province_id' => 6]));
    }

    public function test_payload_survives_a_vector_rewrite(): void
    {
        $this->setVector($this->alpha, $this->vector([0.0, 1.0, 0.0]));

        $this->assertSame(34, $this->pointPayload($this->alpha)['payload']['province_id']);
    }

    public function test_filter_respects_limit(): void
    {
        $this->assertCount(2, $this->search(['active' => true], limit: 2));
    }

    public function test_filter_combined_with_where(): void
    {
        $this->assertSame(
            [$this->alpha->id],
            $this->search(['province_id' => 34], fn ($q) => $q->where('name', 'Alpha')),
        );
    }

    public function test_filter_combined_with_threshold(): void
    {
        $this->setVector($this->gamma, $this->vector([0.0, 1.0, 0.0]));

        $this->assertSame(
            [$this->alpha->id],
            $this->search(['province_id' => 34], threshold: 0.9),
        );
    }

    public function test_similar_to_text_forwards_the_filter(): void
    {
        $result = VenueWithPayload::similarToText('kafe', filter: ['category_id' => 7]);

        $this->assertSame([$this->gamma->id], $result->pluck('id')->all());
    }

    public function test_most_similar_forwards_the_filter(): void
    {
        $result = $this->alpha->mostSimilar(filter: ['category_id' => 3]);

        $this->assertSame(
            [$this->beta->id, $this->nullProvince->id],
            $result->pluck('id')->sort()->values()->all(),
        );
    }
}
