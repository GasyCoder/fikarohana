<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('provider', 32)->nullable();
            $table->string('external_id', 128)->nullable();
            $table->string('taxonomy_level', 16)->nullable();
            $table->string('revision_fingerprint', 64)->nullable();
        });
        DB::unprepared(<<<'SQL'
ALTER TABLE subjects DROP CONSTRAINT subject_name_unique;
CREATE UNIQUE INDEX subject_name_unique ON subjects (parent_id, normalized_name) NULLS NOT DISTINCT WHERE provider IS NULL;
ALTER TABLE subjects ADD CONSTRAINT subject_provider_revision_unique UNIQUE(provider, external_id, revision_fingerprint);
ALTER TABLE subjects ADD CONSTRAINT subject_provider_shape CHECK (
 (provider IS NULL AND external_id IS NULL AND taxonomy_level IS NULL AND revision_fingerprint IS NULL)
 OR (provider IS NOT NULL AND external_id IS NOT NULL AND taxonomy_level IS NOT NULL AND revision_fingerprint IS NOT NULL
 AND provider='openalex' AND revision_fingerprint ~ '^[a-f0-9]{64}$'
 AND CASE taxonomy_level
 WHEN 'topic' THEN external_id ~ '^https://openalex[.]org/T[1-9][0-9]{0,19}$'
 WHEN 'subfield' THEN external_id ~ '^https://openalex[.]org/subfields/[1-9][0-9]{3}$'
 WHEN 'field' THEN external_id ~ '^https://openalex[.]org/fields/(1[1-9]|2[0-9]|3[0-6])$'
 WHEN 'domain' THEN external_id ~ '^https://openalex[.]org/domains/[1-4]$'
 ELSE false END)
);
CREATE OR REPLACE FUNCTION ingestion_subject_taxonomy_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE expected text; p subjects%ROWTYPE;
BEGIN
 IF TG_OP IN ('UPDATE','DELETE') AND OLD.provider IS NOT NULL THEN
  RAISE EXCEPTION 'Provider taxonomy versions are immutable' USING ERRCODE='23514';
 END IF;
 IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
 IF NEW.provider IS NOT NULL AND NEW.parent_id IS NOT NULL THEN
  expected := CASE NEW.taxonomy_level WHEN 'topic' THEN 'subfield' WHEN 'subfield' THEN 'field' WHEN 'field' THEN 'domain' ELSE NULL END;
  SELECT * INTO p FROM subjects WHERE id=NEW.parent_id;
  IF expected IS NULL OR p.provider IS DISTINCT FROM NEW.provider OR p.taxonomy_level IS DISTINCT FROM expected THEN
   RAISE EXCEPTION 'Provider taxonomy parent mismatch' USING ERRCODE='23514';
  END IF;
 ELSIF NEW.provider IS NULL AND NEW.parent_id IS NOT NULL THEN
  IF EXISTS(SELECT 1 FROM subjects WHERE id=NEW.parent_id AND provider IS NOT NULL) THEN
   RAISE EXCEPTION 'Provider taxonomy cannot parent canonical vocabulary' USING ERRCODE='23514';
  END IF;
 END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION catalog_assertion_selection_key(value uuid) RETURNS text LANGUAGE plpgsql STABLE AS $$
DECLARE claim journal_assertions; discriminator text;
BEGIN
 SELECT * INTO claim FROM journal_assertions WHERE id = $1;
 CASE claim.field
  WHEN 'fee' THEN SELECT fee_type INTO discriminator FROM journal_fees WHERE assertion_id = $1;
  WHEN 'requirement' THEN SELECT requirement_type INTO discriminator FROM journal_requirements WHERE assertion_id = $1;
  WHEN 'template' THEN SELECT format INTO discriminator FROM journal_templates WHERE assertion_id = $1;
  WHEN 'identifier' THEN SELECT normalized_value INTO discriminator FROM journal_identifiers WHERE assertion_id = $1;
  WHEN 'subject' THEN SELECT CASE WHEN s.provider IS NULL THEN s.id::text ELSE s.provider || '|' || s.external_id END INTO discriminator FROM journal_subjects js JOIN subjects s ON s.id=js.subject_id WHERE js.assertion_id = $1;
  WHEN 'language' THEN SELECT language_id::text INTO discriminator FROM journal_languages WHERE assertion_id = $1;
  WHEN 'article_type' THEN SELECT article_type_id::text INTO discriminator FROM journal_article_types WHERE assertion_id = $1;
  WHEN 'indexation' THEN SELECT indexation_service_id::text INTO discriminator FROM journal_indexations WHERE assertion_id = $1;
  ELSE discriminator := '';
 END CASE;
 IF discriminator IS NULL THEN RETURN NULL; END IF;
 RETURN concat(claim.field, ':', discriminator, ':', coalesce(claim.article_type_id::text, ''), ':', coalesce(claim.language_id::text, ''));
END;
$$;
CREATE TRIGGER subject_taxonomy_guard BEFORE INSERT OR UPDATE OR DELETE ON subjects FOR EACH ROW EXECUTE FUNCTION ingestion_subject_taxonomy_guard();
SQL);
    }

    public function down(): void
    {
        // Never silently drop taxonomy identity/history from populated catalogs.
        if (DB::table('subjects')->whereNotNull('provider')->exists()) {
            throw new LogicException('Provider taxonomy evidence must be preserved; rollback requires an empty provider vocabulary.');
        }
        DB::unprepared(<<<'SQL'
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
DROP TRIGGER subject_taxonomy_guard ON subjects;
DROP FUNCTION ingestion_subject_taxonomy_guard();
ALTER TABLE subjects DROP CONSTRAINT subject_provider_shape;
ALTER TABLE subjects DROP CONSTRAINT subject_provider_revision_unique;
DROP INDEX subject_name_unique;
ALTER TABLE subjects ADD CONSTRAINT subject_name_unique UNIQUE NULLS NOT DISTINCT (parent_id, normalized_name);
SQL);
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn(['provider', 'external_id', 'taxonomy_level', 'revision_fingerprint']));
    }
};
