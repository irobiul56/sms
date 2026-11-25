<?php

namespace App\Http\Middleware;

use App\Models\Exam;
use App\Models\InstituteClass;
use App\Models\InstituteSubject;
use App\Models\Result;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Services\TeacherPermissionService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'teacherPermissions' => fn () => $request->user()
                && strtolower((string) $request->user()->usertype) === 'teacher'
                    ? app(TeacherPermissionService::class)->sidebarPermissions($request->user())
                    : [],
            'instituteSidebarStats' => fn () => $this->instituteSidebarStats($request),
        ];
    }

    /**
     * Get database-backed counts for the institute navigation badges.
     *
     * @return array<string, int>
     */
    private function instituteSidebarStats(Request $request): array
    {
        $user = $request->user();
        $instituteId = $user?->institute_id;

        if (!$instituteId || strtolower((string) $user->usertype) === 'teacher') {
            return [];
        }

        $exams = fn () => Exam::query()->whereHas('instituteClass', fn ($query) =>
            $query->where('institute_id', $instituteId)
        );
        $results = fn () => Result::query()->whereHas('student', fn ($query) =>
            $query->where('institute_id', $instituteId)
        );

        return [
            'students' => Student::forInstitute($instituteId)->count(),
            'teachers' => Teacher::forInstitute($instituteId)->count(),
            'subjects' => InstituteSubject::forInstitute($instituteId)->count(),
            'classes' => InstituteClass::forInstitute($instituteId)->count(),
            'upcoming_exams' => $exams()->where('start_date', '>', today())->count(),
            'pending_marks' => $exams()->where('end_date', '<', today())
                ->whereDoesntHave('marks')
                ->count(),
            'results' => $results()->count(),
            'pending_results' => $results()->where('is_published', false)
                ->whereHas('exam', fn ($query) => $query->where('end_date', '<', today()))
                ->count(),
        ];
    }
}
