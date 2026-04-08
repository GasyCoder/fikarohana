<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author');
            $table->unsignedSmallInteger('year');
            $table->text('abstract')->nullable();
            $table->string('source_name');
            $table->text('source_url');
            $table->string('original_id')->nullable();
            $table->enum('type', ['thesis', 'article']);
            $table->string('domain');
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index('year');
            $table->index('domain');
            $table->index('source_name');
            $table->unique(['source_name', 'original_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
