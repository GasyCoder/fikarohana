<?php

namespace Database\Factories;

use App\Models\SourceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SourceRecord> */
class SourceRecordFactory extends Factory
{
    protected $model = SourceRecord::class;

    public function definition(): array
    {
        return [
            'journal_source_id' => JournalSourceFactory::new(),
            'external_id' => (string) Str::uuid7(),
            'revision_fingerprint' => 'synthetic-v1',
            'source_url' => 'https://journal.example.test/guidelines',
            'retrieved_at' => now()->subHour(),
            'status' => 'captured',
        ];
    }
}
