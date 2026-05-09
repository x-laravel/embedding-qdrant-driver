<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Http;

return new class extends Migration
{
    public function up(): void
    {
        $dimensions = config('embedding.dimensions', 1536);
        $collection = config('embedding.qdrant.collection', 'embeddings');
        $url = config('embedding.qdrant.url', 'http://localhost:6333');

        Http::baseUrl($url)->put("/collections/{$collection}", [
            'vectors' => [
                'size'     => $dimensions,
                'distance' => 'Cosine',
            ],
        ]);
    }

    public function down(): void
    {
        $collection = config('embedding.qdrant.collection', 'embeddings');
        $url = config('embedding.qdrant.url', 'http://localhost:6333');

        Http::baseUrl($url)->delete("/collections/{$collection}");
    }
};
