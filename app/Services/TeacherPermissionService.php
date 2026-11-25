<?php

namespace App\Services;

use App\Models\ClassTeacher;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Result;
use App\Models\StudentAcademicInfo;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TeacherPermissionService
{
    public const CLASS_PERMISSIONS = [
        'attendance.view' => 'View attendance',
        'attendance.manage' => 'Take and update attendance',
        'admit_cards.print' => 'Admit card print',
        'seat_plan.print' => 'Seat plan print',
        'results.publish' => 'Publish results',
        'marksheets.print' => 'Marksheet print',
        'tabulation_sheet.print' => 'Tabulation sheet print',
    ];

    public const SUBJECT_PERMISSIONS = [
        'marks.view' => 'View marks',
        'marks.enter' => 'Enter marks',
        'marks.update' => 'Update marks',
        'marks.delete' => 'Delete marks',
        'homework.manage' => 'Create and manage homework',
    ];

    public function hasClassPermission(
        User $user,
        string $permission,
        int $classId,
        ?int $sectionId = null,
        ?int $academicYearId = null
    ): bool {
        $teacher = $this->activeTeacher($user);
        if (! $teacher) {
            return false;
        }

        return ClassTeacher::query()
            ->where('teacher_id', $teacher->id)
            ->where('institute_class_id', $classId)
            ->whereJsonContains('permissions', $permission)
            ->where(function ($query) use ($sectionId) {
                $query->whereNull('section_id');
                if ($sectionId !== null) {
                    $query->orWhere('section_id', $sectionId);
                }
            })
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->exists();
    }

    public function hasSubjectPermission(
        User $user,
        string $permission,
        int $classId,
        int $subjectId,
        ?int $sectionId = null,
        ?int $academicYearId = null
    ): bool {
        $teacher = $this->activeTeacher($user);
        if (! $teacher) {
            return false;
        }

        return TeacherSubject::query()
            ->active()
            ->where('teacher_id', $teacher->id)
            ->where('institute_class_id', $classId)
            ->where('institute_subject_id', $subjectId)
            ->whereJsonContains('permissions', $permission)
            ->where(function ($query) use ($sectionId) {
                $query->whereNull('section_id');
                if ($sectionId !== null) {
                    $query->orWhere('section_id', $sectionId);
                }
            })
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->exists();
    }

    public function hasAnyClassPermission(User $user, string $permission, ?int $academicYearId = null): bool
    {
        $teacher = $this->activeTeacher($user);

        return $teacher !== null && ClassTeacher::query()
            ->where('teacher_id', $teacher->id)
            ->whereJsonContains('permissions', $permission)
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->exists();
    }

    public function hasAnyClassPermissionForClass(
        User $user,
        string $permission,
        int $classId,
        ?int $academicYearId = null
    ): bool {
        $teacher = $this->activeTeacher($user);

        return $teacher !== null && ClassTeacher::query()
            ->where('teacher_id', $teacher->id)
            ->where('institute_class_id', $classId)
            ->whereJsonContains('permissions', $permission)
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->exists();
    }

    public function classAssignmentsForPermission(
        User $user,
        string $permission,
        ?int $academicYearId = null
    ) {
        $teacher = $this->activeTeacher($user);
        if (! $teacher) {
            return collect();
        }

        return ClassTeacher::query()
            ->where('teacher_id', $teacher->id)
            ->whereJsonContains('permissions', $permission)
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->get(['institute_class_id', 'section_id', 'academic_year_id']);
    }

    public function hasAnySubjectPermission(User $user, string $permission, ?int $academicYearId = null): bool
    {
        $teacher = $this->activeTeacher($user);

        return $teacher !== null && TeacherSubject::query()
            ->active()
            ->where('teacher_id', $teacher->id)
            ->whereJsonContains('permissions', $permission)
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->exists();
    }

    public function sidebarPermissions(User $user): array
    {
        $teacher = $this->activeTeacher($user);
        $academicYearId = AcademicYear::query()
            ->where('institute_id', $user->institute_id)
            ->where('is_current', true)
            ->value('id');

        if (! $teacher || ! $academicYearId) {
            return [];
        }

        $classPermissions = ClassTeacher::query()
            ->where('teacher_id', $teacher->id)
            ->where('academic_year_id', $academicYearId)
            ->get(['permissions'])
            ->flatMap(fn (ClassTeacher $assignment) => $assignment->permissions ?? [])
            ->unique();
        $subjectPermissions = TeacherSubject::query()
            ->active()
            ->where('teacher_id', $teacher->id)
            ->where('academic_year_id', $academicYearId)
            ->get(['permissions'])
            ->flatMap(fn (TeacherSubject $assignment) => $assignment->permissions ?? [])
            ->unique();
        $permissions = [];

        foreach (self::CLASS_PERMISSIONS as $permission => $label) {
            $permissions[$permission] = $classPermissions->contains($permission);
        }
        foreach (self::SUBJECT_PERMISSIONS as $permission => $label) {
            $permissions[$permission] = $subjectPermissions->contains($permission);
        }

        return $permissions;
    }

    public function allowsRequest(Request $request): bool
    {
        $user = $request->user();
        if (! $user || strtolower((string) $user->usertype) !== 'teacher') {
            return false;
        }

        $routeName = (string) $request->route()?->getName();
        if (in_array($routeName, ['profile.edit', 'profile.update'], true)) {
            return true;
        }

        if ($routeName === 'marks.index') {
            return $this->hasAnySubjectPermission($user, 'marks.view');
        }
        if ($routeName === 'marks.entry') {
            return $this->hasAnySubjectPermission($user, 'marks.enter');
        }
        if ($routeName === 'marks.get-students' || $routeName === 'marks.store') {
            $exam = Exam::query()->find($request->input('exam_id'));
            $permission = $routeName === 'marks.store' ? 'marks.enter' : 'marks.enter';

            return $exam !== null && $this->hasSubjectPermission(
                $user,
                $permission,
                (int) $request->input('class_id', $request->input('institute_class_id')),
                (int) $request->input('subject_id', $request->input('institute_subject_id')),
                $request->filled('section_id') ? (int) $request->input('section_id') : null,
                (int) $exam->academic_year_id
            );
        }
        if (in_array($routeName, ['marks.show', 'marks.edit', 'marks.update', 'marks.destroy'], true)) {
            $mark = $request->route('mark');
            if (! $mark instanceof Mark) {
                $mark = Mark::query()->find($mark);
            }
            if (! $mark) {
                return false;
            }

            $permission = match ($routeName) {
                'marks.show' => 'marks.view',
                'marks.edit', 'marks.update' => 'marks.update',
                default => 'marks.delete',
            };

            return $this->hasSubjectPermission(
                $user,
                $permission,
                (int) $mark->institute_class_id,
                (int) $mark->institute_subject_id,
                (int) $mark->section_id,
                (int) $mark->exam()->value('academic_year_id')
            );
        }
        if ($routeName === 'attendance.index') {
            return $this->hasAnyClassPermission($user, 'attendance.view')
                || $this->hasAnyClassPermission($user, 'attendance.manage');
        }
        if ($routeName === 'homework.index') {
            return $this->hasAnySubjectPermission($user, 'homework.manage');
        }
        if (in_array($routeName, ['homework.store', 'homework.update', 'homework.destroy'], true)) {
            if ($routeName === 'homework.store') {
                return $this->hasSubjectPermission(
                    $user,
                    'homework.manage',
                    (int) $request->input('institute_class_id'),
                    (int) $request->input('institute_subject_id'),
                    $request->filled('section_id') ? (int) $request->input('section_id') : null,
                    (int) $request->input('academic_year_id')
                );
            }

            $homework = $request->route('homework');
            if (! $homework instanceof \App\Models\Homework) {
                $homework = \App\Models\Homework::query()->find($homework);
            }

            return $homework !== null
                && (int) $homework->institute_id === (int) $user->institute_id
                && (int) $homework->teacher_id === (int) $user->teacher?->id
                && $this->hasSubjectPermission(
                    $user,
                    'homework.manage',
                    (int) $homework->institute_class_id,
                    (int) $homework->institute_subject_id,
                    $homework->section_id ? (int) $homework->section_id : null,
                    (int) $homework->academic_year_id
                );
        }
        if (in_array($routeName, ['attendance.roster', 'attendance.store'], true)) {
            return $this->hasClassPermission(
                $user,
                'attendance.manage',
                (int) $request->input('class_id'),
                $request->filled('section_id') ? (int) $request->input('section_id') : null
            );
        }

        if ($routeName === 'results.publish') {
            return $this->hasAnyClassPermission($user, 'results.publish');
        }
        if ($routeName === 'results.toggle-publish') {
            $result = $request->route('result');
            if (! $result instanceof Result) {
                $result = Result::query()->find($result);
            }

            return $result !== null && $this->hasClassPermission(
                $user,
                'results.publish',
                (int) $result->institute_class_id,
                (int) $result->section_id,
                (int) $result->exam()->value('academic_year_id')
            );
        }
        if ($routeName === 'results.generate') {
            $exam = $this->examFromRequest($request);

            return $exam !== null && $this->hasEveryExamClassPermission($user, $exam, 'results.publish');
        }

        $documentPermission = match ($routeName) {
            'exam-documents.admit-card', 'exam-documents.class-admit-cards' => 'admit_cards.print',
            'exam-documents.seat-plan' => 'seat_plan.print',
            'exam-documents.marksheet', 'exam-documents.class-marksheets' => 'marksheets.print',
            'exam-documents.class-tabulation-sheet' => 'tabulation_sheet.print',
            default => null,
        };
        if ($documentPermission !== null) {
            return $this->allowsExamDocumentRequest($request, $user, $documentPermission);
        }
        if ($routeName === 'exam-documents.index') {
            return $this->allowsExamDocumentIndex($request, $user);
        }

        return false;
    }

    private function allowsExamDocumentIndex(Request $request, User $user): bool
    {
        $permissions = match ($request->query('document_type')) {
            'admit-card' => ['admit_cards.print'],
            'seat-plan' => ['seat_plan.print'],
            'marksheet' => ['marksheets.print'],
            default => ['admit_cards.print', 'seat_plan.print', 'marksheets.print', 'tabulation_sheet.print'],
        };
        $exam = $this->examFromRequest($request);
        $classId = $request->integer('class_id');

        foreach ($permissions as $permission) {
            if ($classId > 0) {
                if ($this->hasAnyClassPermissionForClass(
                    $user,
                    $permission,
                    $classId,
                    $exam?->academic_year_id ? (int) $exam->academic_year_id : null
                )) {
                    return true;
                }
            } elseif ($exam !== null) {
                if ($this->hasAnyExamClassPermission($user, $exam, $permission)) {
                    return true;
                }
            } elseif ($this->hasAnyClassPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function allowsExamDocumentRequest(Request $request, User $user, string $permission): bool
    {
        $exam = $this->examFromRequest($request);
        if (! $exam) {
            return false;
        }

        if ($request->route()?->getName() === 'exam-documents.seat-plan'
            && $request->has('class_ids')) {
            $classIds = collect($request->input('class_ids'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            if ($classIds->isEmpty() || $classIds->diff($exam->classIds())->isNotEmpty()) {
                return false;
            }

            return $classIds->every(fn (int $classId) => $this->hasClassPermission(
                $user,
                $permission,
                $classId,
                $classId === (int) $request->query('class_id') && $request->filled('section_id')
                    ? (int) $request->query('section_id')
                    : null,
                (int) $exam->academic_year_id
            ));
        }

        $classId = $request->filled('class_id')
            ? (int) $request->query('class_id')
            : (int) $exam->institute_class_id;
        if (! in_array($classId, $exam->classIds(), true)) {
            return false;
        }

        $sectionId = $request->filled('section_id') ? (int) $request->query('section_id') : null;
        if (in_array($request->route()?->getName(), ['exam-documents.admit-card', 'exam-documents.marksheet'], true)) {
            $studentId = (int) $request->route('student');
            $studentInfo = StudentAcademicInfo::query()
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('institute_class_id', $classId)
                ->where('student_id', $studentId)
                ->first();
            if (! $studentInfo) {
                return false;
            }
            $sectionId = (int) $studentInfo->section_id;
        } elseif ($request->route()?->getName() === 'exam-documents.seat-plan'
            && $request->filled('student_id')) {
            $studentInfo = StudentAcademicInfo::query()
                ->where('academic_year_id', $exam->academic_year_id)
                ->where('institute_class_id', $classId)
                ->where('student_id', (int) $request->query('student_id'))
                ->first();
            if (! $studentInfo) {
                return false;
            }
            $sectionId = (int) $studentInfo->section_id;
        }

        return $this->hasClassPermission(
            $user,
            $permission,
            $classId,
            $sectionId,
            (int) $exam->academic_year_id
        );
    }

    private function hasAnyExamClassPermission(User $user, Exam $exam, string $permission): bool
    {
        foreach ($exam->classIds() as $classId) {
            if ($this->hasAnyClassPermissionForClass($user, $permission, $classId, (int) $exam->academic_year_id)) {
                return true;
            }
        }

        return false;
    }

    private function hasEveryExamClassPermission(User $user, Exam $exam, string $permission): bool
    {
        $classIds = $exam->classIds();

        return $classIds !== [] && collect($classIds)->every(
            fn (int $classId) => $this->hasClassPermission(
                $user,
                $permission,
                $classId,
                null,
                (int) $exam->academic_year_id
            )
        );
    }

    private function examFromRequest(Request $request): ?Exam
    {
        $exam = $request->route('exam');
        if ($exam instanceof Exam) {
            return $exam->loadMissing('classes');
        }

        return is_numeric($exam) ? Exam::query()->with('classes')->find((int) $exam) : null;
    }

    public function restrictMarksToPermission(Builder $query, User $user, string $permission): Builder
    {
        $teacher = $this->activeTeacher($user);
        if (! $teacher) {
            return $query->whereRaw('1 = 0');
        }

        $assignments = TeacherSubject::query()
            ->active()
            ->where('teacher_id', $teacher->id)
            ->where('permissions', 'like', '%"'.$permission.'"%')
            ->get(['institute_class_id', 'institute_subject_id', 'section_id', 'academic_year_id']);

        if ($assignments->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($marks) use ($assignments) {
            foreach ($assignments as $assignment) {
                $marks->orWhere(function ($match) use ($assignment) {
                    $match->where('institute_class_id', $assignment->institute_class_id)
                        ->where('institute_subject_id', $assignment->institute_subject_id)
                        ->whereHas('exam', fn ($exam) => $exam->where('academic_year_id', $assignment->academic_year_id));
                    if ($assignment->section_id !== null) {
                        $match->where('section_id', $assignment->section_id);
                    }
                });
            }
        });
    }

    private function activeTeacher(User $user): ?Teacher
    {
        return $user->teacher()
            ->where('institute_id', $user->institute_id)
            ->where('status', 'active')
            ->first();
    }
}
