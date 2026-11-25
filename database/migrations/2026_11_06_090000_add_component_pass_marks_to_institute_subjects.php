<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pass thresholds are shared by every exam using this subject.
        Schema::table('institute_subjects', function (Blueprint $table) {
            $table->decimal('theory_pass_marks', 7, 2)->nullable()->after('theory_marks');
            $table->decimal('mcq_pass_marks', 7, 2)->nullable()->after('mcq_marks');
            $table->decimal('practical_pass_marks', 7, 2)->nullable()->after('practical_marks');
        });
    }

    public function down(): void
    {
        Schema::table('institute_subjects', function (Blueprint $table) {
            $table->dropColumn(['theory_pass_marks', 'mcq_pass_marks', 'practical_pass_marks']);
        });
    }
};
