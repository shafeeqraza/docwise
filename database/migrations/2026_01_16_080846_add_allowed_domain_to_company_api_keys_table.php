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
        Schema::table('company_api_keys', function (Blueprint $table) {
            $table->string('allowed_domain')->after('permissions');
            $table->index('allowed_domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_api_keys', function (Blueprint $table) {
            $table->dropIndex(['allowed_domain']);
            $table->dropColumn('allowed_domain');
        });
    }
};
