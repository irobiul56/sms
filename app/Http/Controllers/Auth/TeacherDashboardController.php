<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $teacher = $user->teacher()
            ->where('institute_id', $user->institute_id)
            ->firstOrFail();

        $classes = $teacher->classTeachers()
            ->with(['instituteClass:id,name', 'section:id,name', 'academicYear:id,session,year'])
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $user->institute_id))
            ->get()
            ->map(fn ($assignment) => [
                'class' => $assignment->instituteClass?->name,
                'section' => $assignment->section?->name,
                'academic_year' => $assignment->academicYear?->session,
                'permissions' => $assignment->permissions ?? [],
            ]);

        $subjects = $teacher->subjectAssignments()
            ->active()
            ->with(['instituteSubject:id,name', 'instituteClass:id,name', 'section:id,name', 'academicYear:id,session'])
            ->whereHas('instituteSubject', fn ($query) => $query->where('institute_id', $user->institute_id))
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $user->institute_id))
            ->get()
            ->map(fn ($assignment) => [
                'subject' => $assignment->instituteSubject?->name,
                'class' => $assignment->instituteClass?->name,
                'section' => $assignment->section?->name,
                'academic_year' => $assignment->academicYear?->session,
                'permissions' => $assignment->permissions ?? [],
            ]);

        return Inertia::render('Teacher/Dashboard', [
            'teacherName' => $teacher->name,
            'classes' => $classes,
            'subjects' => $subjects,
        ]);
    }
}
