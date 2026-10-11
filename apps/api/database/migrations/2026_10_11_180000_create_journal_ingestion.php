<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Journal ingestion requires PostgreSQL.');
        }
        // One adapter identity per source protects source-record/staging keys across future providers.
        Schema::create('ingestion_source_providers', function (Blueprint $table) {
            $table->foreignUuid('journal_source_id')->primary()->constrained()->restrictOnDelete();
            $table->string('provider', 64);
        });
        Schema::create('ingestion_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_source_id')->constrained()->restrictOnDelete();
            $table->string('provider', 64);
            $table->string('mapping_version', 64);
            $table->string('idempotency_key', 128);
            $table->char('input_hash', 64);
            $table->string('status', 24)->default('staged');
            $table->jsonb('errors')->default('[]');
            $table->timestampsTz();
            $table->unique(['journal_source_id', 'idempotency_key']);
        });
        Schema::create('ingestion_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_source_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('source_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mapping_version', 64);
            $table->char('item_key', 64);
            $table->jsonb('payload')->nullable();
            $table->jsonb('normalized')->nullable();
            $table->jsonb('errors')->default('[]');
            $table->jsonb('conflict_ids')->default('[]');
            $table->string('status', 24)->default('staged');
            $table->foreignUuid('journal_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('catalog_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('publication_hash', 64)->nullable();
            $table->timestampsTz();
            $table->unique(['journal_source_id', 'item_key', 'mapping_version']);
        });
        Schema::create('ingestion_run_items', function (Blueprint $table) {
            $table->foreignUuid('ingestion_run_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('ingestion_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->boolean('unchanged')->default(false);
            $table->primary(['ingestion_run_id', 'position']);
        });
        Schema::create('ingestion_claims', function (Blueprint $table) {
            $table->foreignUuid('ingestion_item_id')->constrained()->restrictOnDelete();
            $table->uuid('assertion_id');
            $table->foreign('assertion_id')->references('id')->on('journal_assertions')->restrictOnDelete();
            $table->char('claim_hash', 64);
            $table->primary(['ingestion_item_id', 'assertion_id']);
        });
        DB::unprepared(<<<'SQL'
-- Forward fix: identifier rows have a `value` column, ambiguous with the Phase 2 argument.
-- Positional argument references preserve the existing function signature and trigger callers.
CREATE OR REPLACE FUNCTION catalog_assertion_selection_key(value uuid) RETURNS text LANGUAGE plpgsql STABLE AS $$
DECLARE claim journal_assertions; discriminator text;
BEGIN
 SELECT * INTO claim FROM journal_assertions WHERE id = $1;
 CASE claim.field
  WHEN 'fee' THEN SELECT fee_type INTO discriminator FROM journal_fees WHERE assertion_id = $1;
  WHEN 'requirement' THEN SELECT requirement_type INTO discriminator FROM journal_requirements WHERE assertion_id = $1;
  WHEN 'template' THEN SELECT format INTO discriminator FROM journal_templates WHERE assertion_id = $1;
  WHEN 'identifier' THEN SELECT normalized_value INTO discriminator FROM journal_identifiers WHERE assertion_id = $1;
  WHEN 'subject' THEN SELECT subject_id::text INTO discriminator FROM journal_subjects WHERE assertion_id = $1;
  WHEN 'language' THEN SELECT language_id::text INTO discriminator FROM journal_languages WHERE assertion_id = $1;
  WHEN 'article_type' THEN SELECT article_type_id::text INTO discriminator FROM journal_article_types WHERE assertion_id = $1;
  WHEN 'indexation' THEN SELECT indexation_service_id::text INTO discriminator FROM journal_indexations WHERE assertion_id = $1;
  ELSE discriminator := '';
 END CASE;
 IF discriminator IS NULL THEN RETURN NULL; END IF;
 RETURN concat(claim.field, ':', discriminator, ':', coalesce(claim.article_type_id::text, ''), ':', coalesce(claim.language_id::text, ''));
END;
$$;
ALTER TABLE ingestion_source_providers ADD CHECK (provider ~ '^[a-z0-9][a-z0-9_.-]{0,63}$');
ALTER TABLE ingestion_runs ADD CHECK (status IN ('staged','failed','completed'));
ALTER TABLE ingestion_runs ADD CHECK (errors IN ('[]'::jsonb, '["processing_interrupted"]'::jsonb));
ALTER TABLE ingestion_runs ADD CHECK (input_hash ~ '^[0-9a-f]{64}$' AND btrim(idempotency_key) <> '' AND btrim(provider) <> '' AND btrim(mapping_version) <> '');
ALTER TABLE ingestion_items ADD CHECK (status IN ('staged','failed','quarantined','pending','conflicting','published'));
ALTER TABLE ingestion_items ADD CHECK (item_key ~ '^[0-9a-f]{64}$' AND btrim(mapping_version) <> '');
ALTER TABLE ingestion_items ADD CHECK (payload IS NULL OR (jsonb_typeof(payload) = 'object' AND octet_length(payload::text) <= 65536));
ALTER TABLE ingestion_items ADD CHECK (normalized IS NULL OR (jsonb_typeof(normalized) = 'object' AND octet_length(normalized::text) <= 65536));
ALTER TABLE ingestion_items ADD CHECK (jsonb_typeof(errors) = 'array' AND jsonb_typeof(conflict_ids) = 'array');
ALTER TABLE ingestion_items ADD CHECK ((status = 'published' AND catalog_revision_id IS NOT NULL AND publication_hash IS NOT NULL AND publication_hash ~ '^[0-9a-f]{64}$') OR (status <> 'published' AND catalog_revision_id IS NULL AND publication_hash IS NULL));
ALTER TABLE ingestion_items ADD CHECK (status NOT IN ('pending','conflicting','published') OR (journal_id IS NOT NULL AND source_record_id IS NOT NULL AND normalized IS NOT NULL));
ALTER TABLE ingestion_claims ADD CHECK (claim_hash ~ '^[0-9a-f]{64}$');
CREATE OR REPLACE FUNCTION ingestion_guard_item() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Ingestion audit cannot be deleted' USING ERRCODE='23514'; END IF;
 IF NEW.journal_source_id IS DISTINCT FROM OLD.journal_source_id OR NEW.source_record_id IS DISTINCT FROM OLD.source_record_id
 OR NEW.item_key IS DISTINCT FROM OLD.item_key OR NEW.mapping_version IS DISTINCT FROM OLD.mapping_version
 OR NEW.payload IS DISTINCT FROM OLD.payload OR OLD.status = 'published'
 OR (OLD.normalized IS NOT NULL AND NEW.normalized IS DISTINCT FROM OLD.normalized)
 OR (OLD.journal_id IS NOT NULL AND NEW.journal_id IS DISTINCT FROM OLD.journal_id)
 OR (OLD.status NOT IN ('staged','failed') AND (NEW.errors IS DISTINCT FROM OLD.errors OR NEW.conflict_ids IS DISTINCT FROM OLD.conflict_ids)) THEN
  RAISE EXCEPTION 'Staged input and published result are immutable' USING ERRCODE='23514';
 END IF;
 IF OLD.status NOT IN ('staged','failed') AND NEW.status NOT IN (OLD.status,'published') THEN
  RAISE EXCEPTION 'Processed staging cannot be reset' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER ingestion_item_guard BEFORE UPDATE OR DELETE ON ingestion_items FOR EACH ROW EXECUTE FUNCTION ingestion_guard_item();
CREATE OR REPLACE FUNCTION ingestion_guard_run() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'Ingestion audit cannot be deleted' USING ERRCODE='23514'; END IF;
 IF (to_jsonb(NEW) - 'status' - 'updated_at' - 'errors') IS DISTINCT FROM (to_jsonb(OLD) - 'status' - 'updated_at' - 'errors')
 OR (OLD.errors <> '[]'::jsonb AND NEW.errors IS DISTINCT FROM OLD.errors)
 OR OLD.status = 'completed' THEN RAISE EXCEPTION 'Run input is immutable' USING ERRCODE='23514'; END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER ingestion_run_guard BEFORE UPDATE OR DELETE ON ingestion_runs FOR EACH ROW EXECUTE FUNCTION ingestion_guard_run();
CREATE OR REPLACE FUNCTION ingestion_check_run() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'INSERT' AND NEW.status <> 'staged' THEN
  RAISE EXCEPTION 'Runs must start staged' USING ERRCODE='23514';
 END IF;
 IF NOT EXISTS (SELECT 1 FROM ingestion_source_providers b WHERE b.journal_source_id = NEW.journal_source_id AND b.provider = NEW.provider) THEN
  RAISE EXCEPTION 'Source belongs to another provider' USING ERRCODE='23514';
 END IF;
 IF NEW.status = 'completed' AND EXISTS (SELECT 1 FROM ingestion_run_items m JOIN ingestion_items i ON i.id = m.ingestion_item_id
 WHERE m.ingestion_run_id = NEW.id AND i.status IN ('staged','failed')) THEN
  RAISE EXCEPTION 'Run still has unfinished items' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER ingestion_run_check BEFORE INSERT OR UPDATE ON ingestion_runs FOR EACH ROW EXECUTE FUNCTION ingestion_check_run();
CREATE TRIGGER ingestion_provider_immutable BEFORE UPDATE OR DELETE ON ingestion_source_providers FOR EACH ROW EXECUTE FUNCTION catalog_no_history_change();
CREATE OR REPLACE FUNCTION ingestion_check_item() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF TG_OP = 'INSERT' AND NEW.status NOT IN ('staged','quarantined') THEN
  RAISE EXCEPTION 'Items must enter staging before processing' USING ERRCODE='23514';
 END IF;
 IF NEW.source_record_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM source_records s WHERE s.id = NEW.source_record_id AND s.journal_source_id = NEW.journal_source_id) THEN
  RAISE EXCEPTION 'Observation belongs to a different source' USING ERRCODE='23514';
 END IF;
 IF NEW.status = 'published' AND NOT EXISTS (SELECT 1 FROM catalog_revision_journals m JOIN catalog_revisions c ON c.id = m.catalog_revision_id
 WHERE c.id = NEW.catalog_revision_id AND c.published_at IS NOT NULL AND m.journal_id = NEW.journal_id) THEN
  RAISE EXCEPTION 'Publication must reference a sealed catalog containing this journal' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER ingestion_item_check BEFORE INSERT OR UPDATE ON ingestion_items FOR EACH ROW EXECUTE FUNCTION ingestion_check_item();
CREATE OR REPLACE FUNCTION ingestion_check_membership() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT EXISTS (SELECT 1 FROM ingestion_runs r JOIN ingestion_items i ON i.journal_source_id = r.journal_source_id
 AND i.mapping_version = r.mapping_version WHERE r.id = NEW.ingestion_run_id AND i.id = NEW.ingestion_item_id AND r.status = 'staged') THEN
  RAISE EXCEPTION 'Membership requires an open run and matching source/mapping' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER ingestion_membership_check BEFORE INSERT ON ingestion_run_items FOR EACH ROW EXECUTE FUNCTION ingestion_check_membership();
CREATE OR REPLACE FUNCTION ingestion_check_claim() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NOT EXISTS (SELECT 1 FROM ingestion_items i JOIN journal_assertions a
 ON a.journal_id = i.journal_id AND a.source_record_id = i.source_record_id
 WHERE i.id = NEW.ingestion_item_id AND a.id = NEW.assertion_id AND i.status IN ('staged','failed')) THEN
  RAISE EXCEPTION 'Claim must belong to staged journal and source' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER ingestion_claim_guard BEFORE INSERT ON ingestion_claims FOR EACH ROW EXECUTE FUNCTION ingestion_check_claim();
CREATE TRIGGER ingestion_claim_immutable BEFORE UPDATE OR DELETE ON ingestion_claims FOR EACH ROW EXECUTE FUNCTION catalog_no_history_change();
CREATE TRIGGER ingestion_membership_immutable BEFORE UPDATE OR DELETE ON ingestion_run_items FOR EACH ROW EXECUTE FUNCTION catalog_no_history_change();
SQL);
    }

    public function down(): void
    {
        foreach (['ingestion_claims', 'ingestion_run_items', 'ingestion_items', 'ingestion_runs', 'ingestion_source_providers'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['ingestion_check_claim', 'ingestion_guard_run', 'ingestion_guard_item', 'ingestion_check_item', 'ingestion_check_membership', 'ingestion_check_run'] as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}()");
        }
    }
};
