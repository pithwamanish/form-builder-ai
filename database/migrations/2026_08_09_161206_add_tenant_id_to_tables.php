<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('tenant_id', 64)->default('default')->after('id')->index();
        });

        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('tenant_id', 64)->default('default')->after('id')->index();
        });

        Schema::table('ai_generation_logs', function (Blueprint $table) {
            $table->string('tenant_id', 64)->default('default')->after('id')->index();
        });

        Schema::table('document_import_logs', function (Blueprint $table) {
            $table->string('tenant_id', 64)->default('default')->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('ai_generation_logs', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::table('document_import_logs', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
