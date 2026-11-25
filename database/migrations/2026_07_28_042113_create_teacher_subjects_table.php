<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('institute_subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('institute_class_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
            
            // Additional Info
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->text('note')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint - one teacher can teach same subject in same class once
            $table->unique(['teacher_id', 'institute_subject_id', 'institute_class_id', 'section_id', 'academic_year_id'], 'unique_teacher_subject_assignment');
            
            // Indexes
            $table->index(['teacher_id', 'academic_year_id']);
            $table->index(['institute_subject_id', 'institute_class_id']);
            $table->index(['teacher_id', 'institute_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subjects');
    }
};