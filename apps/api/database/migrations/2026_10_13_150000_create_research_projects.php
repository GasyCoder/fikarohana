<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('abstract')->nullable();
            $table->jsonb('keywords');
            $table->jsonb('subject_ids');
            $table->string('language')->nullable();
            $table->string('article_type')->nullable();
            $table->foreignUuid('selected_journal_id')->nullable()->constrained('journals')->restrictOnDelete();
            $table->timestampTz('journal_selected_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_projects');
    }
};
