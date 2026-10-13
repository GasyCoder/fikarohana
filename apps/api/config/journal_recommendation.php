<?php

return [
    'version' => 'metadata-relevance-v1',
    'weights' => ['taxonomy' => 0.6, 'lexical' => 0.4],
    'taxonomy_decay' => 0.7,
    'taxonomy_max_distance' => 3,
    'max_tokens' => 24,
    'candidate_limit' => 200,
    'public_requests_per_minute' => 60,
];
