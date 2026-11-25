<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_infos', function (Blueprint $table) {
            $table->foreignId('optional_subject_id')
                ->nullable()
                ->after('elective_subject_id')
                ->constrained('institute_subjects')
                ->nullOnDelete();
            $table->foreignId('additional_subject_id')
                ->nullable()
                ->after('optional_subject_id')
                ->constrained('institute_subjects')
                ->nullOnDelete();
        });

        Schema::table('institute_class_institute_subjects', function (Blueprint $table) {
            $table->enum('type', ['compulsory', 'optional', 'elective', 'additional'])
                ->default('compulsory')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('institute_class_institute_subjects', function (Blueprint $table) {
            $table->enum('type', ['compulsory', 'optional', 'elective'])
                ->default('compulsory')
                ->change();
        });

        Schema::table('student_academic_infos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('additional_subject_id');
            $table->dropConstrainedForeignId('optional_subject_id');
        });
    }
};
