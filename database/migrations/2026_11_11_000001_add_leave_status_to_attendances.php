<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'holiday', 'leave'])->change();
        });
    }

    public function down(): void
    {
        DB::table('attendances')->where('status', 'leave')->update(['status' => 'absent']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'holiday'])->change();
        });
    }
};
