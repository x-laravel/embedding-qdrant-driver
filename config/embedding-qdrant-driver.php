<?php

return [
    'url'        => env('EMBEDDING_QDRANT_URL', 'http://localhost:6333'),
    'collection' => env('EMBEDDING_QDRANT_COLLECTION', 'embeddings'),
    'api_key'    => env('EMBEDDING_QDRANT_API_KEY'),
];
