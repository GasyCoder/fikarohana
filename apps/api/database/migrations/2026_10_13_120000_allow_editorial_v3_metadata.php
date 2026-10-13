<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replace(true);
    }

    public function down(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('editorial_rollback_requires_transaction');
        }
        DB::statement('LOCK TABLE journal_assertions IN ACCESS EXCLUSIVE MODE');
        if (DB::table('journal_assertions')->where('extraction_metadata->document_version', 'editorial-dom-v3')->exists()) {
            throw new DomainException('editorial_rollback_preserves_evidence');
        }
        $this->replace(false);
    }

    private function replace(bool $forward): void
    {
        $definition = DB::selectOne("SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid = 'journal_assertions'::regclass AND conname = 'editorial_metadata_valid'")->definition;
        if ($forward) {
            $definition = str_replace("'editorial-dom-v2'::text]", "'editorial-dom-v2'::text, 'editorial-dom-v3'::text]", $definition);
            $definition = str_replace("(extraction_metadata ->> 'document_version'::text) <> 'editorial-dom-v2'::text", "NOT ((extraction_metadata ->> 'document_version'::text) = ANY (ARRAY['editorial-dom-v2'::text, 'editorial-dom-v3'::text]))", $definition);
        } else {
            $definition = str_replace("'editorial-dom-v2'::text, 'editorial-dom-v3'::text]", "'editorial-dom-v2'::text]", $definition);
        }
        DB::statement('ALTER TABLE journal_assertions DROP CONSTRAINT editorial_metadata_valid');
        DB::unprepared('ALTER TABLE journal_assertions ADD CONSTRAINT editorial_metadata_valid '.$definition);
    }
};
