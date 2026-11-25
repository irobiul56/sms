<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_institute_subjecs', function (Blueprint $table) {
            $table->foreignId('institute_class_id')
                ->nullable()
                ->after('institute_subject_id')
                ->constrained()
                ->nullOnDelete();
        });

        DB::table('exam_institute_subjecs')
            ->orderBy('id')
            ->get()
            ->each(function ($assignment) {
                $examClassIds = DB::table('exam_institute_classes')
                    ->where('exam_id', $assignment->exam_id)
                    ->pluck('institute_class_id');

                $subjectClassIds = DB::table('institute_class_institute_subjects')
                    ->whereIn('institute_class_id', $examClassIds)
                    ->where('institute_subject_id', $assignment->institute_subject_id)
                    ->pluck('institute_class_id');

                $classIds = $subjectClassIds->isNotEmpty()
                    ? $subjectClassIds
                    : collect([DB::table('exams')
                        ->where('id', $assignment->exam_id)
                        ->value('institute_class_id')]);

                DB::table('exam_institute_subjecs')
                    ->where('id', $assignment->id)
                    ->update(['institute_class_id' => $classIds->first()]);

                foreach ($classIds->skip(1) as $classId) {
                    DB::table('exam_institute_subjecs')->insert([
                        'exam_id' => $assignment->exam_id,
                        'institute_subject_id' => $assignment->institute_subject_id,
                        'institute_class_id' => $classId,
                        'exam_date' => $assignment->exam_date,
                        'start_time' => $assignment->start_time,
                        'end_time' => $assignment->end_time,
                        'full_marks' => $assignment->full_marks,
                        'pass_marks' => $assignment->pass_marks,
                        'created_at' => $assignment->created_at,
                        'updated_at' => $assignment->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('exam_institute_subjecs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institute_class_id');
        });
    }
};
