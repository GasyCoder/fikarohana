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
            throw new RuntimeException('The journal catalog requires PostgreSQL.');
        }

        Schema::create('publishers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('website_url', 2048)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->timestampsTz();
        });
        Schema::create('journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publisher_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('normalized_title')->index();
            $table->string('short_title')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('official_website_url', 2048)->nullable();
            $table->boolean('active')->default(false)->index();
            $table->timestampsTz();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('normalized_name');
            $table->uuid('parent_id')->nullable();
            $table->timestampsTz();
        });
        foreach (['languages', 'article_types', 'indexation_services'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('normalized_name')->index();
                $table->timestampsTz();
            });
        }
        Schema::create('journal_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->string('name');
            $table->string('base_url', 2048)->nullable();
            $table->timestampsTz();
        });
        Schema::create('source_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_source_id')->constrained()->restrictOnDelete();
            $table->string('external_id');
            $table->string('revision_fingerprint', 128);
            $table->string('source_url', 2048);
            $table->timestampTz('retrieved_at');
            $table->string('content_hash', 64)->nullable();
            $table->string('raw_metadata_reference', 1024)->nullable();
            $table->string('status', 24)->default('captured');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['journal_source_id', 'external_id', 'revision_fingerprint'], 'source_record_version_unique');
        });
        Schema::create('journal_assertions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('source_record_id')->constrained()->restrictOnDelete();
            $table->string('field', 32);
            $table->string('availability', 24)->default('known');
            $table->text('text_value')->nullable();
            $table->foreignUuid('publisher_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('article_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('language_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_locator', 512)->nullable();
            $table->text('original_wording')->nullable();
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_until')->nullable();
            $table->uuid('supersedes_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['id', 'journal_id']);
            $table->unique(['id', 'journal_id', 'field']);
            $table->index(['journal_id', 'field']);
        });
        Schema::table('subjects', fn (Blueprint $table) => $table->foreign('parent_id')->references('id')->on('subjects')->restrictOnDelete());
        Schema::table('journal_assertions', fn (Blueprint $table) => $table->foreign(['supersedes_id', 'journal_id', 'field'])->references(['id', 'journal_id', 'field'])->on('journal_assertions')->restrictOnDelete());
        Schema::create('assertion_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assertion_id')->constrained('journal_assertions')->restrictOnDelete();
            $table->string('status', 24);
            $table->string('method', 64);
            $table->string('reviewer_reference', 128)->nullable();
            $table->timestampTz('reviewed_at');
            $table->timestampTz('verified_at')->nullable();
            $table->text('reason')->nullable();
            $table->unique(['id', 'assertion_id']);
        });
        DB::statement('ALTER TABLE assertion_reviews ADD COLUMN sequence bigserial UNIQUE');
        DB::statement('CREATE INDEX assertion_review_latest ON assertion_reviews (assertion_id, sequence DESC)');

        Schema::create('journal_identifiers', function (Blueprint $table) {
            $this->factColumns($table, 'identifier');
            $table->string('type', 16);
            $table->string('value', 32);
            $table->string('normalized_value', 9)->unique();
        });
        foreach (['journal_subjects' => 'subject', 'journal_languages' => 'language', 'journal_article_types' => 'article_type', 'journal_indexations' => 'indexation'] as $name => $field) {
            Schema::create($name, function (Blueprint $table) use ($field) {
                $this->factColumns($table, $field);
                $table->foreignUuid(match ($field) {
                    'indexation' => 'indexation_service_id',
                    default => $field.'_id',
                })->constrained(match ($field) {
                    'subject' => 'subjects', 'language' => 'languages', 'article_type' => 'article_types',
                    default => 'indexation_services',
                })->restrictOnDelete();
                if (in_array($field, ['language', 'article_type', 'indexation'], true)) {
                    $table->boolean('supported');
                }
            });
        }
        Schema::create('journal_fees', function (Blueprint $table) {
            $this->factColumns($table, 'fee');
            $table->string('fee_type', 24);
            $table->decimal('amount', 14, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('basis', 64);
            $table->text('applicability');
            $table->text('notes')->nullable();
        });
        Schema::create('journal_requirements', function (Blueprint $table) {
            $this->factColumns($table, 'requirement');
            $table->string('requirement_type', 32);
            $table->string('operator', 16);
            $table->unsignedInteger('value_number')->nullable();
            $table->text('value_text')->nullable();
            $table->jsonb('value_items')->nullable();
            $table->text('notes')->nullable();
        });
        Schema::create('journal_templates', function (Blueprint $table) {
            $this->factColumns($table, 'template');
            $table->string('format', 24);
            $table->string('official_url', 2048);
            $table->string('label');
        });
        Schema::create('journal_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();
            $table->unique(['journal_id', 'version']);
            $table->unique(['id', 'journal_id']);
        });
        Schema::create('journal_revision_assertions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_revision_id');
            $table->uuid('journal_id');
            $table->uuid('assertion_id');
            $table->uuid('review_id');
            $table->string('selection_key', 255);
            $table->unique(['journal_revision_id', 'assertion_id'], 'revision_assertion_unique');
            $table->unique(['journal_revision_id', 'selection_key'], 'revision_selection_unique');
            $table->foreign(['journal_revision_id', 'journal_id'], 'revision_selection_journal_fk')->references(['id', 'journal_id'])->on('journal_revisions')->restrictOnDelete();
            $table->foreign(['assertion_id', 'journal_id'], 'revision_selection_assertion_fk')->references(['id', 'journal_id'])->on('journal_assertions')->restrictOnDelete();
            $table->foreign(['review_id', 'assertion_id'], 'revision_selection_review_fk')->references(['id', 'assertion_id'])->on('assertion_reviews')->restrictOnDelete();
        });
        Schema::create('catalog_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();
        });
        DB::statement('ALTER TABLE catalog_revisions ADD COLUMN version bigserial UNIQUE');
        Schema::create('catalog_revision_journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('catalog_revision_id')->constrained()->restrictOnDelete();
            $table->uuid('journal_id');
            $table->uuid('journal_revision_id');
            $table->unique(['catalog_revision_id', 'journal_id'], 'catalog_journal_unique');
            $table->foreign(['journal_revision_id', 'journal_id'], 'catalog_journal_revision_fk')->references(['id', 'journal_id'])->on('journal_revisions')->restrictOnDelete();
        });

        $this->constraints();
        $this->historyGuards();
    }

    private function factColumns(Blueprint $table, string $field): void
    {
        $table->uuid('id')->primary();
        $table->uuid('journal_id');
        $table->uuid('assertion_id')->unique();
        $table->string('field', 32)->default($field);
        $table->timestampTz('created_at')->useCurrent();
        $table->foreign(['assertion_id', 'journal_id', 'field'])->references(['id', 'journal_id', 'field'])->on('journal_assertions')->restrictOnDelete();
        $table->index('journal_id');
    }

    private function constraints(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION catalog_valid_url(value text) RETURNS boolean LANGUAGE sql IMMUTABLE AS $$
 SELECT value IS NULL OR (value ~ '^https?://[^[:space:]/?#@:]+(:[0-9]+)?([/?#][^[:space:]]*)?$');
$$;
CREATE OR REPLACE FUNCTION catalog_valid_string_list(value jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS $$
 SELECT CASE WHEN value IS NULL OR jsonb_typeof(value) <> 'array' THEN false
 ELSE jsonb_array_length(value) > 0 AND NOT EXISTS (
  SELECT 1 FROM jsonb_array_elements(value) item WHERE jsonb_typeof(item) <> 'string' OR btrim(item #>> '{}') = ''
 ) END;
$$;
CREATE OR REPLACE FUNCTION catalog_valid_issn(value text) RETURNS boolean LANGUAGE plpgsql IMMUTABLE AS $$
DECLARE compact text; total integer := 0; expected integer;
BEGIN
 IF value !~ '^[0-9]{4}-[0-9]{3}[0-9X]$' THEN RETURN false; END IF;
 compact := replace(value, '-', '');
 FOR i IN 1..7 LOOP total := total + substr(compact, i, 1)::integer * (9 - i); END LOOP;
 expected := (11 - (total % 11)) % 11;
 RETURN substr(compact, 8, 1) = CASE WHEN expected = 10 THEN 'X' ELSE expected::text END;
END;
$$;
ALTER TABLE subjects ADD CONSTRAINT subject_name_unique UNIQUE NULLS NOT DISTINCT (parent_id, normalized_name);
ALTER TABLE subjects ADD CONSTRAINT subject_not_self CHECK (parent_id IS DISTINCT FROM id);
ALTER TABLE languages ADD CONSTRAINT language_code_valid CHECK (code ~ '^[a-z]{2,3}(-[A-Z]{2})?$');
ALTER TABLE journal_sources ADD CONSTRAINT source_type_valid CHECK (type IN ('official_journal','official_publisher','doaj','openalex','crossref','index_registry','other'));
ALTER TABLE source_records ADD CONSTRAINT record_status_valid CHECK (status IN ('captured','quarantined'));
ALTER TABLE source_records ADD CONSTRAINT record_hash_valid CHECK (content_hash IS NULL OR content_hash ~ '^[0-9a-f]{64}$');
ALTER TABLE source_records ADD CONSTRAINT record_keys_nonempty CHECK (btrim(external_id) <> '' AND btrim(revision_fingerprint) <> '');
ALTER TABLE journal_assertions ADD CONSTRAINT assertion_field_valid CHECK (field IN ('title','publisher','country','website','submission_url','scope','identifier','subject','language','article_type','indexation','fee','requirement','template'));
ALTER TABLE journal_assertions ADD CONSTRAINT availability_valid CHECK (availability IN ('known','unknown','not_applicable'));
ALTER TABLE journal_assertions ADD CONSTRAINT assertion_dates_valid CHECK (effective_until IS NULL OR effective_from IS NULL OR effective_until >= effective_from);
ALTER TABLE journal_assertions ADD CONSTRAINT assertion_not_self CHECK (supersedes_id IS DISTINCT FROM id);
ALTER TABLE journal_assertions ADD CONSTRAINT assertion_scalar_value CHECK (
 (field IN ('title','country','website','submission_url','scope') AND publisher_id IS NULL AND
   ((availability = 'known' AND text_value IS NOT NULL AND btrim(text_value) <> '') OR (availability <> 'known' AND text_value IS NULL))) OR
 (field = 'publisher' AND text_value IS NULL AND ((availability = 'known' AND publisher_id IS NOT NULL) OR (availability <> 'known' AND publisher_id IS NULL))) OR
 (field IN ('identifier','subject','language','article_type','indexation','fee','requirement','template') AND text_value IS NULL AND publisher_id IS NULL));
ALTER TABLE journal_assertions ADD CONSTRAINT assertion_country CHECK (field <> 'country' OR text_value IS NULL OR text_value ~ '^[A-Z]{2}$');
ALTER TABLE journal_assertions ADD CONSTRAINT assertion_url CHECK (field NOT IN ('website','submission_url') OR catalog_valid_url(text_value));
ALTER TABLE assertion_reviews ADD CONSTRAINT review_status_valid CHECK (status IN ('pending','verified','stale','conflicting','rejected'));
ALTER TABLE assertion_reviews ADD CONSTRAINT review_method_valid CHECK (btrim(method) <> '');
ALTER TABLE assertion_reviews ADD CONSTRAINT review_verification_dates CHECK ((status = 'verified' AND verified_at IS NOT NULL AND verified_at <= reviewed_at) OR (status <> 'verified' AND verified_at IS NULL));
ALTER TABLE journal_identifiers ADD CONSTRAINT identifier_type_valid CHECK (type IN ('issn','eissn'));
ALTER TABLE journal_identifiers ADD CONSTRAINT identifier_checksum_valid CHECK (catalog_valid_issn(normalized_value));
ALTER TABLE journal_identifiers ADD CONSTRAINT identifier_normalization_valid CHECK (upper(replace(replace(btrim(value), '-', ''), ' ', '')) = replace(normalized_value, '-', ''));
ALTER TABLE journal_fees ADD CONSTRAINT fee_type_valid CHECK (fee_type IN ('apc','submission','page','color','other'));
ALTER TABLE journal_fees ADD CONSTRAINT fee_amount_currency_valid CHECK ((amount IS NULL AND currency IS NULL) OR (amount IS NOT NULL AND amount >= 0 AND amount NOT IN ('NaN'::numeric, 'Infinity'::numeric, '-Infinity'::numeric) AND currency IS NOT NULL AND currency ~ '^[A-Z]{3}$'));
ALTER TABLE journal_fees ADD CONSTRAINT fee_context_nonempty CHECK (btrim(basis) <> '' AND btrim(applicability) <> '');
ALTER TABLE journal_templates ADD CONSTRAINT template_format_valid CHECK (format IN ('word','latex','pdf_guide','other'));
ALTER TABLE journal_templates ADD CONSTRAINT template_url_valid CHECK (catalog_valid_url(official_url));
ALTER TABLE journal_requirements ADD CONSTRAINT requirement_value_valid CHECK (
 (requirement_type IN ('word_count','abstract_words','keyword_count','figure_count','table_count') AND operator IN ('min','max','exact') AND value_number IS NOT NULL AND value_number >= 0 AND value_text IS NULL AND value_items IS NULL) OR
 (requirement_type IN ('reference_style','figure_guidance','table_guidance','structure','declaration') AND operator = 'text' AND value_text IS NOT NULL AND btrim(value_text) <> '' AND value_number IS NULL AND value_items IS NULL) OR
 (requirement_type = 'file_formats' AND operator = 'list' AND value_number IS NULL AND value_text IS NULL AND catalog_valid_string_list(value_items)));
ALTER TABLE journal_revisions ADD CONSTRAINT journal_version_positive CHECK (version > 0);
SQL);
        foreach (['publishers' => ['website_url'], 'journals' => ['official_website_url'], 'journal_sources' => ['base_url'], 'source_records' => ['source_url']] as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ADD CHECK (catalog_valid_url({$column}))");
            }
        }
        foreach (['publishers', 'subjects', 'languages', 'article_types', 'indexation_services'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CHECK (btrim(name) <> '' AND btrim(normalized_name) <> '')");
        }
        DB::statement("ALTER TABLE journals ADD CHECK (btrim(title) <> '' AND btrim(normalized_title) <> '')");
        foreach (['publishers', 'journals'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$')");
        }
        foreach (['journal_identifiers' => 'identifier', 'journal_subjects' => 'subject', 'journal_languages' => 'language', 'journal_article_types' => 'article_type', 'journal_indexations' => 'indexation', 'journal_fees' => 'fee', 'journal_requirements' => 'requirement', 'journal_templates' => 'template'] as $table => $field) {
            DB::statement("ALTER TABLE {$table} ADD CHECK (field = '{$field}')");
        }
    }

    private function historyGuards(): void
    {
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION catalog_no_history_change() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Catalog evidence is append-only; create a replacement or review' USING ERRCODE = '23514'; END;
$$;
CREATE OR REPLACE FUNCTION catalog_check_subject_parent() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 PERFORM pg_advisory_xact_lock(170002);
 IF EXISTS (WITH RECURSIVE ancestors AS (
  SELECT id, parent_id FROM subjects WHERE id = NEW.parent_id
  UNION SELECT s.id, s.parent_id FROM subjects s JOIN ancestors a ON s.id = a.parent_id
 ) SELECT 1 FROM ancestors WHERE id = NEW.id) THEN
  RAISE EXCEPTION 'Subject hierarchy cannot contain cycles' USING ERRCODE = '23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER subject_parent_guard BEFORE INSERT OR UPDATE ON subjects FOR EACH ROW EXECUTE FUNCTION catalog_check_subject_parent();
CREATE OR REPLACE FUNCTION catalog_check_review() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE observation timestamptz;
BEGIN
 SELECT r.retrieved_at INTO observation FROM source_records r JOIN journal_assertions a ON a.source_record_id = r.id WHERE a.id = NEW.assertion_id;
 IF NEW.reviewed_at < observation OR (NEW.verified_at IS NOT NULL AND NEW.verified_at < observation) THEN
  RAISE EXCEPTION 'Review cannot predate retrieval' USING ERRCODE = '23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER review_time_guard BEFORE INSERT ON assertion_reviews FOR EACH ROW EXECUTE FUNCTION catalog_check_review();
CREATE OR REPLACE FUNCTION catalog_check_fact() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE claim journal_assertions; source_type text;
BEGIN
 SELECT * INTO claim FROM journal_assertions WHERE id = NEW.assertion_id;
 IF TG_TABLE_NAME = 'journal_fees' THEN
  IF (claim.availability = 'known') IS DISTINCT FROM (NEW.amount IS NOT NULL) THEN
   RAISE EXCEPTION 'Unknown/not-applicable fee cannot contain an amount' USING ERRCODE = '23514';
  END IF;
 ELSIF claim.availability <> 'known' THEN
  RAISE EXCEPTION 'This relationship requires a known assertion value; missing relationship stays unknown' USING ERRCODE = '23514';
 END IF;
 IF TG_TABLE_NAME = 'journal_templates' THEN
  SELECT s.type INTO source_type FROM journal_sources s JOIN source_records r ON r.journal_source_id = s.id WHERE r.id = claim.source_record_id;
  IF source_type NOT IN ('official_journal','official_publisher') THEN
   RAISE EXCEPTION 'Official template requires official source evidence' USING ERRCODE = '23514';
  END IF;
 END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION catalog_assertion_selection_key(value uuid) RETURNS text LANGUAGE plpgsql STABLE AS $$
DECLARE claim journal_assertions; discriminator text;
BEGIN
 SELECT * INTO claim FROM journal_assertions WHERE id = value;
 CASE claim.field
  WHEN 'fee' THEN SELECT fee_type INTO discriminator FROM journal_fees WHERE assertion_id = value;
  WHEN 'requirement' THEN SELECT requirement_type INTO discriminator FROM journal_requirements WHERE assertion_id = value;
  WHEN 'template' THEN SELECT format INTO discriminator FROM journal_templates WHERE assertion_id = value;
  WHEN 'identifier' THEN SELECT normalized_value INTO discriminator FROM journal_identifiers WHERE assertion_id = value;
  WHEN 'subject' THEN SELECT subject_id::text INTO discriminator FROM journal_subjects WHERE assertion_id = value;
  WHEN 'language' THEN SELECT language_id::text INTO discriminator FROM journal_languages WHERE assertion_id = value;
  WHEN 'article_type' THEN SELECT article_type_id::text INTO discriminator FROM journal_article_types WHERE assertion_id = value;
  WHEN 'indexation' THEN SELECT indexation_service_id::text INTO discriminator FROM journal_indexations WHERE assertion_id = value;
  ELSE discriminator := '';
 END CASE;
 IF discriminator IS NULL THEN RETURN NULL; END IF;
 RETURN concat(claim.field, ':', discriminator, ':', coalesce(claim.article_type_id::text, ''), ':', coalesce(claim.language_id::text, ''));
END;
$$;
CREATE OR REPLACE FUNCTION catalog_seal_revision() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE selection record; latest assertion_reviews;
BEGIN
 IF TG_OP = 'INSERT' THEN
  IF NEW.published_at IS NOT NULL THEN RAISE EXCEPTION 'A revision must be assembled before publication' USING ERRCODE = '23514'; END IF;
  RETURN NEW;
 END IF;
 IF TG_OP = 'DELETE' OR OLD.published_at IS NOT NULL OR NEW.published_at IS NULL
    OR (to_jsonb(NEW) - 'published_at') IS DISTINCT FROM (to_jsonb(OLD) - 'published_at') THEN
  RAISE EXCEPTION 'Revision is immutable; only its initial publication is allowed' USING ERRCODE = '23514';
 END IF;
 IF TG_TABLE_NAME = 'journal_revisions' THEN
  FOR selection IN SELECT * FROM journal_revision_assertions WHERE journal_revision_id = NEW.id LOOP
   SELECT * INTO latest FROM assertion_reviews WHERE assertion_id = selection.assertion_id ORDER BY sequence DESC LIMIT 1;
   IF EXISTS (SELECT 1 FROM journal_assertions a WHERE a.id = selection.assertion_id
      AND ((a.effective_from IS NOT NULL AND a.effective_from > NEW.published_at)
      OR (a.effective_until IS NOT NULL AND a.effective_until <= NEW.published_at)))
      OR latest.verified_at > NEW.published_at
      OR latest.id IS DISTINCT FROM selection.review_id OR latest.status IS DISTINCT FROM 'verified'
      OR selection.selection_key IS DISTINCT FROM catalog_assertion_selection_key(selection.assertion_id) THEN
    RAISE EXCEPTION 'Evidence changed before revision publication' USING ERRCODE = '23514';
   END IF;
  END LOOP;
 END IF;
 IF TG_TABLE_NAME = 'journal_revisions' AND NOT EXISTS (
  SELECT 1 FROM journal_revision_assertions l JOIN journal_assertions a ON a.id = l.assertion_id
  WHERE l.journal_revision_id = NEW.id AND a.field = 'title' AND a.availability = 'known'
 ) THEN RAISE EXCEPTION 'Published journal revision requires sourced title' USING ERRCODE = '23514'; END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION catalog_check_selection() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE latest assertion_reviews; claim journal_assertions;
BEGIN
 PERFORM 1 FROM journal_revisions WHERE id = NEW.journal_revision_id AND published_at IS NULL FOR UPDATE;
 IF NOT FOUND THEN RAISE EXCEPTION 'Cannot add facts to a published revision' USING ERRCODE = '23514'; END IF;
 SELECT * INTO latest FROM assertion_reviews WHERE assertion_id = NEW.assertion_id ORDER BY sequence DESC LIMIT 1;
 SELECT * INTO claim FROM journal_assertions WHERE id = NEW.assertion_id;
 IF NEW.selection_key IS DISTINCT FROM catalog_assertion_selection_key(NEW.assertion_id) THEN
  RAISE EXCEPTION 'Selection key must match the typed fact context' USING ERRCODE = '23514';
 END IF;
 IF NOT EXISTS (SELECT 1 FROM source_records WHERE id = claim.source_record_id AND status = 'captured') THEN
  RAISE EXCEPTION 'Quarantined observations cannot support publication' USING ERRCODE = '23514';
 END IF;
 IF latest.id IS DISTINCT FROM NEW.review_id OR latest.status IS DISTINCT FROM 'verified' OR claim.availability = 'unknown'
    OR (claim.effective_from IS NOT NULL AND claim.effective_from > CURRENT_TIMESTAMP)
    OR (claim.effective_until IS NOT NULL AND claim.effective_until <= CURRENT_TIMESTAMP) THEN
  RAISE EXCEPTION 'Publication requires current applicable verified evidence' USING ERRCODE = '23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION catalog_vocabulary_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE used boolean := false;
BEGIN
 CASE TG_TABLE_NAME
  WHEN 'languages' THEN used := EXISTS (SELECT 1 FROM journal_languages WHERE language_id = OLD.id) OR EXISTS (SELECT 1 FROM journal_assertions WHERE language_id = OLD.id);
  WHEN 'article_types' THEN used := EXISTS (SELECT 1 FROM journal_article_types WHERE article_type_id = OLD.id) OR EXISTS (SELECT 1 FROM journal_assertions WHERE article_type_id = OLD.id);
  WHEN 'indexation_services' THEN used := EXISTS (SELECT 1 FROM journal_indexations WHERE indexation_service_id = OLD.id);
  WHEN 'publishers' THEN used := EXISTS (SELECT 1 FROM journal_assertions WHERE publisher_id = OLD.id);
  WHEN 'subjects' THEN used := EXISTS (
   WITH RECURSIVE descendants AS (
    SELECT id FROM subjects WHERE id = OLD.id
    UNION SELECT s.id FROM subjects s JOIN descendants d ON s.parent_id = d.id
   ) SELECT 1 FROM journal_subjects WHERE subject_id IN (SELECT id FROM descendants)
  );
 END CASE;
 IF used THEN RAISE EXCEPTION 'Referenced catalog vocabulary identity is immutable' USING ERRCODE = '23514'; END IF;
 RETURN NEW;
END;
$$;
CREATE OR REPLACE FUNCTION catalog_source_identity_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS (SELECT 1 FROM source_records WHERE journal_source_id = OLD.id) THEN
  RAISE EXCEPTION 'Source identity supporting evidence cannot be edited' USING ERRCODE = '23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER source_identity_guard BEFORE UPDATE ON journal_sources FOR EACH ROW EXECUTE FUNCTION catalog_source_identity_guard();
CREATE OR REPLACE FUNCTION catalog_check_snapshot() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 PERFORM 1 FROM catalog_revisions WHERE id = NEW.catalog_revision_id AND published_at IS NULL FOR UPDATE;
 IF NOT FOUND OR NOT EXISTS (SELECT 1 FROM journal_revisions WHERE id = NEW.journal_revision_id AND published_at IS NOT NULL) THEN
  RAISE EXCEPTION 'Catalog membership requires a draft catalog and published journal revision' USING ERRCODE = '23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER journal_revision_seal BEFORE INSERT OR UPDATE OR DELETE ON journal_revisions FOR EACH ROW EXECUTE FUNCTION catalog_seal_revision();
CREATE TRIGGER catalog_revision_seal BEFORE INSERT OR UPDATE OR DELETE ON catalog_revisions FOR EACH ROW EXECUTE FUNCTION catalog_seal_revision();
CREATE TRIGGER revision_selection_guard BEFORE INSERT ON journal_revision_assertions FOR EACH ROW EXECUTE FUNCTION catalog_check_selection();
CREATE TRIGGER catalog_snapshot_guard BEFORE INSERT ON catalog_revision_journals FOR EACH ROW EXECUTE FUNCTION catalog_check_snapshot();
SQL);
        foreach (['languages', 'article_types', 'indexation_services', 'subjects', 'publishers'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_identity_guard BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION catalog_vocabulary_guard()");
        }
        $facts = ['journal_identifiers', 'journal_subjects', 'journal_languages', 'journal_article_types', 'journal_indexations', 'journal_fees', 'journal_requirements', 'journal_templates'];
        foreach ($facts as $table) {
            DB::statement("CREATE TRIGGER {$table}_value_guard BEFORE INSERT ON {$table} FOR EACH ROW EXECUTE FUNCTION catalog_check_fact()");
        }
        foreach (array_merge($facts, ['source_records', 'journal_assertions', 'assertion_reviews', 'journal_revision_assertions', 'catalog_revision_journals']) as $table) {
            DB::statement("CREATE TRIGGER {$table}_history_guard BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION catalog_no_history_change()");
        }
    }

    public function down(): void
    {
        foreach (['catalog_revision_journals', 'catalog_revisions', 'journal_revision_assertions', 'journal_revisions', 'journal_templates', 'journal_requirements', 'journal_fees', 'journal_indexations', 'journal_article_types', 'journal_languages', 'journal_subjects', 'journal_identifiers', 'assertion_reviews', 'journal_assertions', 'source_records', 'journal_sources', 'indexation_services', 'article_types', 'languages', 'subjects', 'journals', 'publishers'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['catalog_no_history_change', 'catalog_check_subject_parent', 'catalog_check_review', 'catalog_check_fact', 'catalog_seal_revision', 'catalog_check_selection', 'catalog_check_snapshot', 'catalog_source_identity_guard', 'catalog_vocabulary_guard'] as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}()");
        }
        DB::statement('DROP FUNCTION IF EXISTS catalog_assertion_selection_key(uuid)');
        DB::statement('DROP FUNCTION IF EXISTS catalog_valid_url(text)');
        DB::statement('DROP FUNCTION IF EXISTS catalog_valid_issn(text)');
        DB::statement('DROP FUNCTION IF EXISTS catalog_valid_string_list(jsonb)');
    }
};
