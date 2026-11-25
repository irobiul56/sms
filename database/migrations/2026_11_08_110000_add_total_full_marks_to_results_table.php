<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->decimal('total_full_marks', 8, 2)->default(0)->after('total_marks');
        });

        DB::table('results')->orderBy('id')->chunkById(200, function ($results) {
            foreach ($results as $result) {
                $fullMarks = DB::table('marks')
                    ->join('exam_institute_subjecs', function ($join) {
                        $join->on('exam_institute_subjecs.exam_id', '=', 'marks.exam_id')
                            ->on('exam_institute_subjecs.institute_subject_id', '=', 'marks.institute_subject_id');
                    })
                    ->where('marks.exam_id', $result->exam_id)
                    ->where('marks.student_id', $result->student_id)
                    ->sum('exam_institute_subjecs.full_marks');

                DB::table('results')
                    ->where('id', $result->id)
                    ->update(['total_full_marks' => $fullMarks]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('total_full_marks');
        });
    }
};
