<?php

namespace App\Http\Controllers;

use App\Models\Homework;
use App\Models\Student;
use App\Models\StudentAcademicInfo;
use App\Models\StudentHomeworkCompletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class StudentHomeworkController extends Controller
{
    public function toggle(Homework $homework): RedirectResponse
    {
        $student = Student::query()
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $academicInfo = StudentAcademicInfo::query()
            ->where('student_id', $student->id)
            ->whereHas('academicYear', fn ($query) => $query->where('is_current', true))
            ->first();

        if (! $academicInfo) {
            $academicInfo = StudentAcademicInfo::query()
                ->where('student_id', $student->id)
                ->latest('academic_year_id')
                ->first();
        }

        abort_unless($academicInfo, 404);

        $homework = Homework::query()
            ->where('id', $homework->id)
            ->where('institute_id', $student->institute_id)
            ->where('academic_year_id', $academicInfo->academic_year_id)
            ->where('institute_class_id', $academicInfo->institute_class_id)
            ->where('is_published', true)
            ->where(function ($query) use ($academicInfo) {
                $query->whereNull('section_id');
                if ($academicInfo->section_id) {
                    $query->orWhere('section_id', $academicInfo->section_id);
                }
            })
            ->firstOrFail();

        $completion = StudentHomeworkCompletion::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $student->id)
            ->first();

        if ($completion) {
            $completion->delete();
        } else {
            StudentHomeworkCompletion::create([
                'homework_id' => $homework->id,
                'student_id' => $student->id,
                'completed_at' => now(),
            ]);
        }

        return back();
    }
}
