<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_teachers', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('class_teachers')->whereNull('section_id')->exists()) {
            throw new RuntimeException('Cannot make class_teachers.section_id required while class-wide assignments exist.');
        }

        Schema::table('class_teachers', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable(false)->change();
        });
    }
};
