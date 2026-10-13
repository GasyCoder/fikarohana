<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_projects', function (Blueprint $table): void {
            $table->boolean('no_apc_only')->default(false);
            $table->jsonb('indexation_services')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('research_projects', function (Blueprint $table): void {
            $table->dropColumn(['no_apc_only', 'indexation_services']);
        });
    }
};
