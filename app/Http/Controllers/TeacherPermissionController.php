<?php

namespace App\Http\Controllers;

use App\Models\ClassTeacher;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Services\TeacherPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeacherPermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned before managing teacher permissions.');

        $teachers = Teacher::forInstitute($instituteId)
            ->with([
                'classTeachers.instituteClass:id,name',
                'classTeachers.section:id,name',
                'classTeachers.academicYear:id,session',
                'subjectAssignments' => fn ($query) => $query->with([
                    'instituteClass:id,name',
                    'instituteSubject:id,name,code',
                    'section:id,name',
                    'academicYear:id,session',
                ]),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Teacher $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'employee_id' => $teacher->employee_id,
                'designation' => $teacher->designation,
                'status' => $teacher->status,
                'has_login' => $teacher->user_id !== null,
                'class_assignments' => $teacher->classTeachers->map(fn (ClassTeacher $assignment) => [
                    'id' => $assignment->id,
                    'class_name' => $assignment->instituteClass?->name ?? 'N/A',
                    'section_name' => $assignment->section?->name ?? 'All sections',
                    'academic_year' => $assignment->academicYear?->session ?? 'N/A',
                    'permissions' => $assignment->permissions ?? [],
                ])->values(),
                'subject_assignments' => $teacher->subjectAssignments->map(fn (TeacherSubject $assignment) => [
                    'id' => $assignment->id,
                    'class_name' => $assignment->instituteClass?->name ?? 'N/A',
                    'subject_name' => $assignment->instituteSubject?->name ?? 'N/A',
                    'section_name' => $assignment->section?->name ?? 'All sections',
                    'academic_year' => $assignment->academicYear?->session ?? 'N/A',
                    'is_active' => $assignment->is_active,
                    'permissions' => $assignment->permissions ?? [],
                ])->values(),
            ])->values();

        $selectedTeacher = $teachers->firstWhere('id', $request->integer('teacher_id'))
            ?? $teachers->first();

        return Inertia::render('Institute/TeacherPermissions/Index', [
            'teachers' => $teachers->map(fn ($teacher) => collect($teacher)->except([
                'class_assignments',
                'subject_assignments',
            ])->all()),
            'selectedTeacherId' => $selectedTeacher['id'] ?? null,
            'classAssignments' => $selectedTeacher['class_assignments'] ?? [],
            'subjectAssignments' => $selectedTeacher['subject_assignments'] ?? [],
            'classPermissionOptions' => TeacherPermissionService::CLASS_PERMISSIONS,
            'subjectPermissionOptions' => TeacherPermissionService::SUBJECT_PERMISSIONS,
        ]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $instituteId = (int) $request->user()->institute_id;
        abort_if(! $instituteId, 403, 'An institute must be assigned before managing teacher permissions.');
        $teacher = Teacher::forInstitute($instituteId)->findOrFail($teacher->id);

        $validated = $request->validate([
            'class_assignments' => ['present', 'array'],
            'class_assignments.*.id' => [
                'required',
                'integer',
                Rule::exists('class_teachers', 'id')->where('teacher_id', $teacher->id),
            ],
            'class_assignments.*.permissions' => ['present', 'array'],
            'class_assignments.*.permissions.*' => [
                'string',
                Rule::in(array_keys(TeacherPermissionService::CLASS_PERMISSIONS)),
            ],
            'subject_assignments' => ['present', 'array'],
            'subject_assignments.*.id' => [
                'required',
                'integer',
                Rule::exists('teacher_subjects', 'id')->where('teacher_id', $teacher->id),
            ],
            'subject_assignments.*.permissions' => ['present', 'array'],
            'subject_assignments.*.permissions.*' => [
                'string',
                Rule::in(array_keys(TeacherPermissionService::SUBJECT_PERMISSIONS)),
            ],
        ]);

        DB::transaction(function () use ($validated, $teacher, $instituteId) {
            foreach ($validated['class_assignments'] as $assignment) {
                ClassTeacher::query()
                    ->where('teacher_id', $teacher->id)
                    ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
                    ->findOrFail($assignment['id'])
                    ->update(['permissions' => array_values(array_unique($assignment['permissions']))]);
            }

            foreach ($validated['subject_assignments'] as $assignment) {
                TeacherSubject::forInstitute($instituteId)
                    ->where('teacher_id', $teacher->id)
                    ->findOrFail($assignment['id'])
                    ->update(['permissions' => array_values(array_unique($assignment['permissions']))]);
            }
        });

        return redirect()
            ->route('teacher-permissions.index', ['teacher_id' => $teacher->id])
            ->with('success', 'Teacher role permissions updated successfully.');
    }
}
