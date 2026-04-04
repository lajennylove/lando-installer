<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remote_sites', function (Blueprint $table) {
            $table->string('remote_path', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('remote_sites', function (Blueprint $table) {
            $table->dropColumn('remote_path');
        });
    }
};
