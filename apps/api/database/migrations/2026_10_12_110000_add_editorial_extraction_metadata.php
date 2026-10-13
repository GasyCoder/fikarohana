<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_assertions', fn (Blueprint $table) => $table->jsonb('extraction_metadata')->nullable());
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION editorial_valid_contexts(value jsonb) RETURNS boolean LANGUAGE plpgsql IMMUTABLE AS $$
DECLARE context jsonb;
BEGIN
 IF jsonb_typeof(value) IS DISTINCT FROM 'array' THEN RETURN false; END IF;
 IF jsonb_array_length(value) > 8 THEN RETURN false; END IF;
 FOR context IN SELECT * FROM jsonb_array_elements(value) LOOP
  IF (jsonb_typeof(context) = 'object' AND context ?& ARRAY['kind','text','locator']
   AND context - ARRAY['kind','text','locator'] = '{}'::jsonb
   AND context->>'kind' IN ('heading','caption','table_header','qualifier')
   AND jsonb_typeof(context->'text') = 'string' AND octet_length(context->>'text') BETWEEN 1 AND 128
   AND jsonb_typeof(context->'locator') = 'string' AND length(context->>'locator') BETWEEN 1 AND 512) IS NOT TRUE THEN
   RETURN false;
  END IF;
 END LOOP;
 RETURN true;
END $$;
ALTER TABLE journal_assertions ADD CONSTRAINT editorial_metadata_valid CHECK (extraction_metadata IS NULL OR (
 jsonb_typeof(extraction_metadata) = 'object' AND octet_length(extraction_metadata::text) <= 8192
 AND extraction_metadata ?& ARRAY['proposal_key','rule','version','status','document_version','scope']
 AND extraction_metadata - ARRAY['proposal_key','rule','version','status','document_version','scope','href','anchor','contexts'] = '{}'::jsonb
 AND extraction_metadata->>'proposal_key' ~ '^[a-f0-9]{64}$'
 AND extraction_metadata->>'rule' ~ '^[a-z_]{1,64}$'
 AND extraction_metadata->>'version' ~ '^editorial-v[0-9]{1,3}$'
 AND extraction_metadata->>'status' = 'deterministic_explicit'
 AND extraction_metadata->>'document_version' IN ('editorial-dom-v1','editorial-dom-v2')
 AND (NOT extraction_metadata ? 'contexts' OR editorial_valid_contexts(extraction_metadata->'contexts'))
 AND (extraction_metadata->>'document_version' <> 'editorial-dom-v2' OR extraction_metadata ? 'contexts')
 AND jsonb_typeof(extraction_metadata->'scope') = 'string'
 AND source_locator IS NOT NULL AND length(source_locator) BETWEEN 1 AND 512
 AND original_wording IS NOT NULL AND octet_length(original_wording) BETWEEN 1 AND 1024
 AND field IN ('fee','language','article_type','requirement','template','submission_url')
) IS TRUE);
CREATE UNIQUE INDEX editorial_proposal_identity ON journal_assertions(source_record_id, (extraction_metadata->>'proposal_key')) WHERE extraction_metadata IS NOT NULL;
CREATE OR REPLACE FUNCTION editorial_assertion_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE record source_records; source journal_sources;
BEGIN
 IF NEW.extraction_metadata IS NOT NULL THEN
  SELECT * INTO record FROM source_records WHERE id = NEW.source_record_id;
  SELECT * INTO source FROM journal_sources WHERE id = record.journal_source_id;
  IF record.raw_content IS NULL OR record.status <> 'captured' OR source.approval_status IS DISTINCT FROM 'approved'
   OR source.type NOT IN ('official_journal','official_publisher') OR source.official_journal_id IS DISTINCT FROM NEW.journal_id
   OR record.snapshot_metadata->>'content_type' NOT IN ('text/html','text/plain') THEN
   RAISE EXCEPTION 'editorial_invalid_source' USING ERRCODE = '23514';
  END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER editorial_assertion_insert BEFORE INSERT ON journal_assertions FOR EACH ROW EXECUTE FUNCTION editorial_assertion_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('editorial_rollback_requires_transaction');
        }
        DB::statement('LOCK TABLE journal_assertions IN ACCESS EXCLUSIVE MODE');
        if (DB::table('journal_assertions')->whereNotNull('extraction_metadata')->exists()) {
            throw new DomainException('editorial_rollback_preserves_evidence');
        }
        DB::unprepared('DROP TRIGGER editorial_assertion_insert ON journal_assertions; DROP FUNCTION editorial_assertion_guard(); DROP INDEX editorial_proposal_identity; ALTER TABLE journal_assertions DROP CONSTRAINT editorial_metadata_valid; ALTER TABLE journal_assertions DROP COLUMN extraction_metadata; DROP FUNCTION editorial_valid_contexts(jsonb);');
    }
};
