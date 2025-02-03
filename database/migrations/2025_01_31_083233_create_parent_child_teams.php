<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('parent_id')
                  ->nullable()
                  ->after('description')
                  ->constrained('teams')
                  ->nullOnDelete();
            $table->string('type')->default('team'); // Could be 'department', 'division', etc.
            $table->integer('level')->default(0); // Hierarchy level
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'type', 'level', 'is_active']);
        });
    }
};