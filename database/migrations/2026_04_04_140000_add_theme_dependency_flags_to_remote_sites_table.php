<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remote_sites', function (Blueprint $table) {
            $table->boolean('install_composer_dependencies')->default(false)->after('repo_url');
            $table->boolean('install_node_dependencies')->default(false)->after('install_composer_dependencies');
        });
    }

    public function down(): void
    {
        Schema::table('remote_sites', function (Blueprint $table) {
            $table->dropColumn(['install_composer_dependencies', 'install_node_dependencies']);
        });
    }
};
