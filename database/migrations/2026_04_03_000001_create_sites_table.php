<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('admin_url')->nullable();
            $table->string('status')->default('unknown');
            $table->string('php_version')->default('8.3');
            $table->string('db_version')->default('10.6');
            $table->unsignedInteger('db_port')->nullable();
            $table->string('redis_version')->nullable();
            $table->string('admin_username')->nullable();
            $table->string('admin_email')->nullable();
            $table->string('theme_name')->nullable();
            $table->unsignedBigInteger('remote_site_id')->nullable();
            $table->text('last_error')->nullable();
            $table->string('log_file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
