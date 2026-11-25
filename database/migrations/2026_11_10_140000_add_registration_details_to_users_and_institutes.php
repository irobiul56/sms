<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('mobile', 30)->nullable();
        });

        Schema::table('institutes', function (Blueprint $table) {
            $table->string('type')->nullable();
            $table->string('eiin', 6)->nullable()->unique();
            $table->string('logo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('institutes', function (Blueprint $table) {
            $table->dropUnique(['eiin']);
            $table->dropColumn(['type', 'eiin', 'logo']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'mobile']);
        });
    }
};
