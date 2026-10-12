<?php

namespace App\Services\JournalIngestion\Providers\OpenAlex;

use DomainException;
use JsonException;

class OpenAlexResponse
{
    public function source(string $body): array
    {
        try {
            $shape = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('openalex_invalid_json');
        }
        if (! is_object($shape) || ! is_string($data['id'] ?? null)
            || ! preg_match('~^https://openalex\.org/S[1-9][0-9]{0,19}$~D', $data['id'])) {
            throw new DomainException('openalex_invalid_response');
        }

        return $data;
    }
}
