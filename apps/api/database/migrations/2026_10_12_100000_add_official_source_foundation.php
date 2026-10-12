<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_sources', function (Blueprint $table): void {
            $table->foreignUuid('official_journal_id')->nullable()->constrained('journals')->restrictOnDelete();
            $table->string('official_purpose', 32)->nullable();
            $table->string('official_url', 2048)->nullable();
            $table->string('official_origin', 260)->nullable();
            $table->string('approval_status', 16)->nullable();
            $table->string('approved_by', 128)->nullable();
            $table->string('approval_basis', 512)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('first_observed_at')->nullable();
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('last_changed_at')->nullable();
            $table->string('last_outcome', 64)->nullable();
            $table->string('last_etag', 512)->nullable();
            $table->string('last_modified', 512)->nullable();
            $table->string('last_final_url', 2048)->nullable();
            $table->uuid('last_source_record_id')->nullable();
            $table->unique(['official_journal_id', 'official_url'], 'official_page_identity_unique');
        });
        Schema::table('source_records', function (Blueprint $table): void {
            $table->text('raw_content')->nullable();
            $table->jsonb('snapshot_metadata')->nullable();
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE source_records ADD CONSTRAINT official_record_source_pair_unique UNIQUE(id,journal_source_id);
ALTER TABLE journal_sources ADD CONSTRAINT official_last_record_source_fk FOREIGN KEY(last_source_record_id,id) REFERENCES source_records(id,journal_source_id) ON DELETE RESTRICT;
ALTER TABLE journal_sources ADD CONSTRAINT official_operational_shape CHECK (
 (last_etag IS NULL OR last_etag !~ '[^ -~]') AND (last_modified IS NULL OR last_modified !~ '[^ -~]')
 AND (last_final_url IS NULL OR (catalog_valid_url(last_final_url) AND last_final_url LIKE official_origin || '/%' AND last_final_url !~ '[?#]'))
 AND (last_outcome IS NULL OR last_outcome ~ '^(changed|unchanged|not_modified|official_[a-z0-9_]+|postgres_incompatible_text)$')
 AND (last_changed_at IS NULL OR (last_checked_at IS NOT NULL AND last_changed_at <= last_checked_at)));
ALTER TABLE journal_sources ADD CONSTRAINT official_source_shape CHECK (
 (official_journal_id IS NULL AND official_url IS NULL AND official_origin IS NULL AND official_purpose IS NULL
 AND approval_status IS NULL AND approved_at IS NULL AND approved_by IS NULL AND approval_basis IS NULL
 AND first_observed_at IS NULL AND last_checked_at IS NULL AND last_changed_at IS NULL AND last_outcome IS NULL
 AND last_etag IS NULL AND last_modified IS NULL AND last_final_url IS NULL AND last_source_record_id IS NULL)
 OR (official_journal_id IS NOT NULL AND type IN ('official_journal','official_publisher')
 AND official_purpose IS NOT NULL AND official_purpose IN ('website','guidelines','submission','fees','template','article_types','editorial')
 AND official_url IS NOT NULL AND official_origin IS NOT NULL
 AND official_origin ~ '^https://[a-z0-9.-]+$' AND official_url LIKE official_origin || '/%'
 AND catalog_valid_url(official_url) AND official_url !~ '[?#]' AND approval_status IS NOT NULL
 AND approval_status IN ('candidate','approved','inactive') AND first_observed_at IS NOT NULL
 AND ((approval_status='candidate' AND approved_at IS NULL AND approved_by IS NULL AND approval_basis IS NULL)
 OR (approval_status IN ('approved','inactive') AND approved_at IS NOT NULL AND approved_by IS NOT NULL
 AND btrim(approved_by) <> '' AND approval_basis IS NOT NULL AND btrim(approval_basis) <> ''))));
ALTER TABLE source_records ADD CONSTRAINT official_snapshot_size CHECK (
 (raw_content IS NULL AND snapshot_metadata IS NULL) OR
 (raw_content IS NOT NULL AND snapshot_metadata IS NOT NULL AND octet_length(raw_content) BETWEEN 1 AND 262144
 AND jsonb_typeof(snapshot_metadata)='object' AND octet_length(snapshot_metadata::text)<=8192));
CREATE OR REPLACE FUNCTION catalog_source_identity_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF OLD.official_journal_id IS NOT NULL OR NEW.official_journal_id IS NOT NULL THEN
  IF (NEW.type,NEW.name,NEW.base_url,NEW.official_journal_id,NEW.official_purpose,NEW.official_url,NEW.official_origin,NEW.first_observed_at)
   IS DISTINCT FROM (OLD.type,OLD.name,OLD.base_url,OLD.official_journal_id,OLD.official_purpose,OLD.official_url,OLD.official_origin,OLD.first_observed_at) THEN
   RAISE EXCEPTION 'Registered official page identity is immutable' USING ERRCODE='23514';
  END IF;
  IF OLD.approved_at IS NOT NULL AND (NEW.approved_at,NEW.approved_by,NEW.approval_basis) IS DISTINCT FROM (OLD.approved_at,OLD.approved_by,OLD.approval_basis) THEN
   RAISE EXCEPTION 'Official approval is immutable' USING ERRCODE='23514';
  END IF;
  IF (OLD.approval_status='approved' AND NEW.approval_status NOT IN ('approved','inactive'))
   OR (OLD.approval_status='inactive' AND NEW.approval_status <> 'inactive') THEN
   RAISE EXCEPTION 'Official approval cannot be reset' USING ERRCODE='23514';
  END IF;
  IF NEW.last_source_record_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM source_records r WHERE r.id=NEW.last_source_record_id AND r.journal_source_id=NEW.id AND r.raw_content IS NOT NULL) THEN
   RAISE EXCEPTION 'Snapshot attribution mismatch' USING ERRCODE='23514';
  END IF;
 ELSIF EXISTS (SELECT 1 FROM source_records WHERE journal_source_id=OLD.id) THEN
  RAISE EXCEPTION 'Source identity supporting evidence cannot be edited' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION official_snapshot_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE s journal_sources%ROWTYPE;
BEGIN
 IF NEW.raw_content IS NOT NULL THEN
  SELECT * INTO s FROM journal_sources WHERE id=NEW.journal_source_id;
  IF s.official_journal_id IS NULL OR s.approval_status <> 'approved'
   OR NEW.source_url IS DISTINCT FROM s.official_url
   OR NEW.snapshot_metadata->>'requested_url' IS DISTINCT FROM s.official_url
   OR NEW.snapshot_metadata->>'final_url' NOT LIKE s.official_origin || '/%'
   OR NEW.snapshot_metadata->>'final_url' IS NULL
   OR NOT catalog_valid_url(NEW.snapshot_metadata->>'final_url')
   OR NEW.snapshot_metadata->>'final_url' ~ '[?#]'
   OR NEW.snapshot_metadata->>'content_type' IS NULL
   OR NEW.snapshot_metadata->>'content_type' NOT IN ('text/html','text/plain')
   OR NEW.status <> 'captured'
   OR NEW.content_hash IS DISTINCT FROM encode(sha256(convert_to(NEW.raw_content,'UTF8')), 'hex')
   OR NEW.external_id IS DISTINCT FROM encode(sha256(convert_to(s.official_url,'UTF8')), 'hex')
   OR NEW.revision_fingerprint IS DISTINCT FROM encode(sha256(convert_to(s.official_url || E'\n' || (NEW.snapshot_metadata->>'content_type') || E'\n' || NEW.raw_content,'UTF8')), 'hex')
   OR NEW.snapshot_metadata->>'http_status' IS DISTINCT FROM '200'
   OR (NEW.snapshot_metadata->>'bytes')::int IS DISTINCT FROM octet_length(NEW.raw_content)
   OR NEW.snapshot_metadata->>'parser_version' IS DISTINCT FROM 'official-page-raw-v1' THEN
   RAISE EXCEPTION 'Invalid official snapshot attribution' USING ERRCODE='23514';
  END IF;
 END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION official_assertion_journal_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM source_records r JOIN journal_sources s ON s.id=r.journal_source_id
 WHERE r.id=NEW.source_record_id AND s.official_journal_id IS NOT NULL AND s.official_journal_id <> NEW.journal_id) THEN
  RAISE EXCEPTION 'Official page belongs to another journal' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER official_assertion_journal_guard BEFORE INSERT ON journal_assertions FOR EACH ROW EXECUTE FUNCTION official_assertion_journal_guard();
CREATE TRIGGER official_snapshot_guard BEFORE INSERT ON source_records FOR EACH ROW EXECUTE FUNCTION official_snapshot_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Official source rollback requires a PostgreSQL migration transaction.');
        }
        // DDL locks precede both checks and remain held until commit/rollback; writers cannot slip in.
        DB::statement('LOCK TABLE journal_sources, source_records IN ACCESS EXCLUSIVE MODE');
        if (DB::table('journal_sources')->whereNotNull('official_journal_id')->exists()
            || DB::table('source_records')->whereNotNull('raw_content')->exists()) {
            throw new LogicException('Official source history must be preserved; rollback requires an empty official registry.');
        }
        DB::unprepared(<<<'SQL'
DROP TRIGGER official_assertion_journal_guard ON journal_assertions;
DROP FUNCTION official_assertion_journal_guard();
DROP TRIGGER official_snapshot_guard ON source_records;
DROP FUNCTION official_snapshot_guard();
ALTER TABLE source_records DROP CONSTRAINT official_snapshot_size;
ALTER TABLE journal_sources DROP CONSTRAINT official_source_shape;
ALTER TABLE journal_sources DROP CONSTRAINT official_operational_shape;
ALTER TABLE journal_sources DROP CONSTRAINT official_last_record_source_fk;
ALTER TABLE source_records DROP CONSTRAINT official_record_source_pair_unique;
CREATE OR REPLACE FUNCTION catalog_source_identity_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS (SELECT 1 FROM source_records WHERE journal_source_id=OLD.id) THEN
  RAISE EXCEPTION 'Source identity supporting evidence cannot be edited' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
SQL);
        Schema::table('journal_sources', function (Blueprint $table): void {
            $table->dropForeign(['official_journal_id']);
            $table->dropUnique('official_page_identity_unique');
            $table->dropColumn(['official_journal_id', 'official_purpose', 'official_url', 'official_origin', 'approval_status',
                'approved_by', 'approval_basis', 'approved_at', 'first_observed_at', 'last_checked_at', 'last_changed_at',
                'last_outcome', 'last_etag', 'last_modified', 'last_final_url', 'last_source_record_id']);
        });
        Schema::table('source_records', fn (Blueprint $table) => $table->dropColumn(['raw_content', 'snapshot_metadata']));
    }
};
