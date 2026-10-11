<?php

namespace App\Services\JournalIngestion\Providers\Doaj;

use DomainException;
use JsonException;

/** Validate the page envelope; record-level problems belong in Phase 3A quarantine. */
class DoajResponse
{
    public function page(string $body, int $page, int $pageSize): array
    {
        try {
            $shape = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('doaj_invalid_json');
        }
        if (! is_object($shape) || ! isset($shape->results) || ! is_array($shape->results)
            || ! is_array($data) || ! isset($data['total'], $data['page'], $data['pageSize'], $data['results'])
            || ! is_int($data['total']) || $data['total'] < 0 || $data['page'] !== $page || $data['pageSize'] !== $pageSize
            || ! is_array($data['results']) || ! array_is_list($data['results']) || count($data['results']) > $pageSize
            || count($data['results']) !== min($pageSize, max(0, $data['total'] - ($page - 1) * $pageSize))) {
            throw new DomainException('doaj_invalid_page');
        }

        return $data;
    }
}
