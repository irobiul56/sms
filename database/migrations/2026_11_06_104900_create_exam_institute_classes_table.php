<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_institute_classes', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institute_class_id')->constrained()->cascadeOnDelete();
            $table->primary(['exam_id', 'institute_class_id']);
            $table->timestamps();
        });

        DB::table('exams')
            ->select(['id', 'institute_class_id'])
            ->orderBy('id')
            ->each(function ($exam) {
                DB::table('exam_institute_classes')->insert([
                    'exam_id' => $exam->id,
                    'institute_class_id' => $exam->institute_class_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_institute_classes');
    }
};
