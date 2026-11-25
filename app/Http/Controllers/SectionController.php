<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\InstituteClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instituteId = Auth::user()->institute_id;

        $classes = InstituteClass::forInstitute($instituteId)
            ->ordered()
            ->get(['id', 'name', 'numeric_value', 'group']);

        // FIX: Using withCount for students through the relationship
        $sections = Section::with(['instituteClass'])
            ->forInstitute($instituteId)
            ->ordered()
            ->withCount(['students']) // This will count students through the relationship
            ->get()
            ->map(function($section) {
                return [
                    'id' => $section->id,
                    'institute_id' => $section->institute_id,
                    'institute_class_id' => $section->institute_class_id,
                    'name' => $section->name,
                    'room_number' => $section->room_number,
                    'capacity' => $section->capacity,
                    'display_order' => $section->display_order,
                    'class_name' => $section->instituteClass->name ?? 'Unknown',
                    'full_name' => $section->full_name,
                    'student_count' => $section->students_count, // Using withCount
                    'capacity_status' => $section->capacity_status,
                    'capacity_status_badge_class' => $section->capacity_status_badge_class,
                    'available_seats' => $section->available_seats,
                    'created_at' => $section->created_at,
                    'updated_at' => $section->updated_at
                ];
            });

        $stats = [
            'total' => $sections->count(),
            'total_students' => $sections->sum('student_count'),
            'full' => $sections->where('capacity_status', 'Full')->count(),
            'almost_full' => $sections->where('capacity_status', 'Almost Full')->count(),
            'available' => $sections->where('capacity_status', 'Available')->count()
        ];

        return Inertia::render('Institute/Sections/Index', [
            'sections' => $sections,
            'classes' => $classes,
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
            'institute_class_id' => [
                'required',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'name' => 'required|string|max:255',
            'room_number' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:500',
            'display_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for duplicate section in same class
        $exists = Section::forInstitute($instituteId)
            ->where('institute_class_id', $request->institute_class_id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This section already exists for the selected class.')
                ->withInput();
        }

        Section::create([
            'institute_id' => $instituteId,
            'institute_class_id' => $request->institute_class_id,
            'name' => $request->name,
            'room_number' => $request->room_number,
            'capacity' => $request->capacity ?? 50,
            'display_order' => $request->display_order ?? 0
        ]);

        return redirect()->route('sections.index')
            ->with('success', 'Section created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        $instituteId = Auth::user()->institute_id;
        $section = Section::forInstitute($instituteId)->findOrFail($section->id);

        $validator = Validator::make($request->all(), [
            'institute_class_id' => [
                'required',
                Rule::exists('institute_classes', 'id')->where('institute_id', $instituteId),
            ],
            'name' => 'required|string|max:255',
            'room_number' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1|max:500',
            'display_order' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Check for duplicate section in same class (excluding current)
        $exists = Section::forInstitute($instituteId)
            ->where('id', '!=', $section->id)
            ->where('institute_class_id', $request->institute_class_id)
            ->where('name', $request->name)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'This section already exists for the selected class.')
                ->withInput();
        }

        $section->update([
            'institute_class_id' => $request->institute_class_id,
            'name' => $request->name,
            'room_number' => $request->room_number,
            'capacity' => $request->capacity ?? 50,
            'display_order' => $request->display_order ?? 0
        ]);

        return redirect()->route('sections.index')
            ->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        $section = Section::forInstitute(Auth::user()->institute_id)->findOrFail($section->id);

        // Check if section has students
        if ($section->students()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete this section. It has ' . $section->students()->count() . ' enrolled students.');
        }

        // Check if section has exams
        if ($section->exams()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete this section. It has associated exams.');
        }

        // Check if section has results
        if ($section->results()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete this section. It has associated results.');
        }

        $section->delete();

        return redirect()->route('sections.index')
            ->with('success', 'Section deleted successfully.');
    }

    /**
     * Get sections for a class (API).
     */
    public function getSectionsByClass($classId)
    {
        $instituteId = Auth::user()->institute_id;

        $sections = Section::forInstitute($instituteId)
            ->where('institute_class_id', $classId)
            ->ordered()
            ->get(['id', 'name', 'capacity', 'room_number']);

        return response()->json([
            'success' => true,
            'data' => $sections
        ]);
    }

    /**
     * Get section details (API).
     */
    public function getSectionDetails($id)
    {
        $section = Section::forInstitute(Auth::user()->institute_id)
            ->with(['instituteClass', 'students'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $section->id,
                'name' => $section->name,
                'class_name' => $section->instituteClass->name ?? 'Unknown',
                'room_number' => $section->room_number,
                'capacity' => $section->capacity,
                'student_count' => $section->student_count,
                'available_seats' => $section->available_seats,
                'capacity_status' => $section->capacity_status,
                'students' => $section->students->take(10)
            ]
        ]);
    }

    /**
     * Get section statistics (API).
     */
    public function getStats()
    {
        $instituteId = Auth::user()->institute_id;

        $sections = Section::forInstitute($instituteId)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $sections->count(),
                'total_students' => $sections->sum('student_count'),
                'full' => $sections->where('capacity_status', 'Full')->count(),
                'almost_full' => $sections->where('capacity_status', 'Almost Full')->count(),
                'available' => $sections->where('capacity_status', 'Available')->count()
            ]
        ]);
    }
}