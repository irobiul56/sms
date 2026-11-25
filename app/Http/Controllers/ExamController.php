<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\AcademicYear;
use App\Models\InstituteClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instituteId = Auth::user()->institute_id;

        $exams = Exam::with(['academicYear', 'instituteClass', 'classes'])
            ->whereHas('instituteClass', function($query) use ($instituteId) {
                $query->where('institute_id', $instituteId);
            })
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function($exam) {
                return [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'bn_name' => $exam->bn_name,
                    'type' => $exam->type,
                    'type_label' => $exam->type_label,
                    'type_badge_class' => $exam->type_badge_class,
                    'start_date' => $exam->start_date,
                    'end_date' => $exam->end_date,
                    'is_published' => $exam->is_published,
                    'status_label' => $exam->status_label,
                    'status_badge_class' => $exam->status_badge_class,
                    'academic_year' => [
                        'id' => $exam->academicYear->id,
                        'session' => $exam->academicYear->session,
                    ],
                    'institute_class_id' => $exam->institute_class_id,
                    'class_ids' => $exam->classIds(),
                    'class_name' => $exam->classes->isNotEmpty()
                        ? $exam->classes->pluck('name')->implode(', ')
                        : ($exam->instituteClass->name ?? 'N/A'),
                    'note' => $exam->note,
                    'created_at' => $exam->created_at,
                    'updated_at' => $exam->updated_at
                ];
            });

        // Get dropdown data
        $academicYears = AcademicYear::forInstitute($instituteId)
            ->orderBy('year', 'desc')
            ->get(['id', 'session', 'is_current']);

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        $typeOptions = [
            ['value' => 'monthly', 'label' => 'Monthly (মাসিক)'],
            ['value' => 'term', 'label' => 'Term (টার্ম)'],
            ['value' => 'half-yearly', 'label' => 'Half-Yearly (অর্ধ-বার্ষিক)'],
            ['value' => 'final', 'label' => 'Final (বার্ষিক)'],
            ['value' => 'test', 'label' => 'Test (টেস্ট)'],
            ['value' => 'pre-test', 'label' => 'Pre-Test (প্রাক-পরীক্ষা)']
        ];

        $stats = [
            'total' => $exams->count(),
            'upcoming' => $exams->where('status_label', 'Upcoming')->count(),
            'ongoing' => $exams->where('status_label', 'Ongoing')->count(),
            'completed' => $exams->where('status_label', 'Completed')->count(),
            'published' => $exams->where('is_published', true)->count()
        ];

        return Inertia::render('Institute/Exams/Index', [
            'exams' => $exams,
            'academicYears' => $academicYears,
            'classes' => $classes,
            'typeOptions' => $typeOptions,
            'stats' => $stats
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $instituteId = Auth::user()->institute_id;
        $validator = Validator::make($request->all(), [
            'academic_year_id' => [
                'required',
                Rule::exists('academic_years', 'id')->where('institute_id', $instituteId),
            ],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'type' => 'required|in:monthly,term,half-yearly,final,test,pre-test',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string',
            'is_published' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for duplicate exam
        $exists = Exam::where('academic_year_id', $request->academic_year_id)
            ->where('name', $request->name)
            ->where(function ($query) use ($request) {
                $query->whereIn('institute_class_id', $request->class_ids)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereIn('institute_classes.id', $request->class_ids));
            })
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This exam already exists for the selected class and academic year.')
                ->withInput();
        }

        $exam = Exam::create([
            'academic_year_id' => $request->academic_year_id,
            'institute_class_id' => $request->class_ids[0],
            'name' => $request->name,
            'bn_name' => $request->bn_name,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'note' => $request->note,
            'is_published' => $request->is_published ?? false
        ]);
        $exam->classes()->sync($request->class_ids);

        return redirect()->route('exams.index')
            ->with('success', 'Exam created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $instituteId = Auth::user()->institute_id;
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', $instituteId))
            ->with('classes')
            ->findOrFail($exam->id);

        $validator = Validator::make($request->all(), [
            'academic_year_id' => [
                'required',
                Rule::exists('academic_years', 'id')->where('institute_id', $instituteId),
            ],
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'name' => 'required|string|max:255',
            'bn_name' => 'nullable|string|max:255',
            'type' => 'required|in:monthly,term,half-yearly,final,test,pre-test',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string',
            'is_published' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for duplicate exam (excluding current)
        $exists = Exam::where('academic_year_id', $request->academic_year_id)
            ->where('name', $request->name)
            ->where(function ($query) use ($request) {
                $query->whereIn('institute_class_id', $request->class_ids)
                    ->orWhereHas('classes', fn ($classes) => $classes->whereIn('institute_classes.id', $request->class_ids));
            })
            ->where('id', '!=', $exam->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This exam already exists for the selected class and academic year.')
                ->withInput();
        }

        $exam->update([
            'academic_year_id' => $request->academic_year_id,
            'institute_class_id' => $request->class_ids[0],
            'name' => $request->name,
            'bn_name' => $request->bn_name,
            'type' => $request->type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'note' => $request->note,
            'is_published' => $request->is_published ?? false
        ]);
        $exam->classes()->sync($request->class_ids);

        return redirect()->route('exams.index')
            ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam)
    {
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', Auth::user()->institute_id))
            ->findOrFail($exam->id);

        // Check if exam has marks
        if ($exam->marks()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete this exam. It has marks recorded.');
        }

        // Check if exam has results
        if ($exam->results()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete this exam. It has results recorded.');
        }

        $exam->delete();

        return redirect()->route('exams.index')
            ->with('success', 'Exam deleted successfully.');
    }

    /**
     * Toggle exam publish status.
     */
    public function togglePublish(Exam $exam)
    {
        $exam = Exam::query()
            ->whereHas('instituteClass', fn ($query) => $query->where('institute_id', Auth::user()->institute_id))
            ->findOrFail($exam->id);

        $exam->update([
            'is_published' => !$exam->is_published
        ]);

        $status = $exam->is_published ? 'published' : 'unpublished';

        return redirect()->back()
            ->with('success', "Exam has been {$status} successfully.");
    }
}