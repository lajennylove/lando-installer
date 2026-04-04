<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_sites', function (Blueprint $table) {
            $table->id();
            $table->string('remote_domain');
            $table->string('local_domain')->nullable();
            $table->string('ssh_server_ip');
            $table->string('ssh_user');
            $table->text('ssh_password')->nullable();
            $table->string('db_name');
            $table->string('db_user');
            $table->text('db_password')->nullable();
            $table->string('theme_name')->nullable();
            $table->string('repo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_sites');
    }
};
