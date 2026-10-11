<?php

namespace App\Services\JournalIngestion\Providers\Crossref;

use DomainException;
use JsonException;

class CrossrefResponse
{
    /** Validate only the singleton envelope here; safe semantic failures go to staging. */
    public function journal(string $body): array
    {
        try {
            $shape = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('crossref_invalid_json');
        }
        if (! is_object($shape) || ($data['status'] ?? null) !== 'ok'
            || ($data['message-type'] ?? null) !== 'journal'
            || ! is_string($data['message-version'] ?? null) || strlen($data['message-version']) > 64
            || ! is_object($shape->message ?? null)) {
            throw new DomainException('crossref_invalid_response');
        }

        return $data;
    }
}
