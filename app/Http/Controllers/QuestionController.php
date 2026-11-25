<?php

namespace App\Http\Controllers;

use App\Models\AcademicClass;
use App\Models\board;
use App\Models\Chapter;
use App\Models\Cqoption;
use App\Models\Education;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Stimuli;
use App\Models\Stimulus;
use App\Models\sub_question;
use App\Models\SubQuestion;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get filter values from request
        $educationId = $request->input('education');
        $classId = $request->input('class');
        $subjectId = $request->input('subject');
        $chapterId = $request->input('chapter');
        $topicId = $request->input('topic');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        // Build query with eager loading
        $query = Question::with([
            'topic:id,name',
            'type:id,name',
            'board:id,name',
            'options:id,question_id,option_text,is_correct',
            'cqoptions:id,question_id,cq_text,mark',
            'subQuestions:id,parent_question_id,format,question_text,explanation,mark,question_number',
            'subQuestions.options:id,sub_question_id,option_text,is_correct',
            'subQuestions.cqoptions:id,sub_question_id,cq_text,mark',
            'stimuli:id,question_id,stimulus_type,content,media_url,caption'
        ]);

        // Apply filters
        if ($educationId) {
            $query->whereHas('academicClass', function ($q) use ($educationId) {
                $q->where('education_id', $educationId);
            });
        }

        if ($classId) {
            $query->where('academic_classes_id', $classId);
        }

        if ($subjectId) {
            $query->whereHas('chapter', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            });
        }

        if ($chapterId) {
            $query->where('chapter_id', $chapterId);
        }

        if ($topicId) {
            $query->where('topic_id', $topicId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('question_text', 'LIKE', "%{$search}%")
                  ->orWhere('explanation', 'LIKE', "%{$search}%")
                  ->orWhereHas('subQuestions', function ($sq) use ($search) {
                      $sq->where('question_text', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Order by latest
        $query->orderBy('created_at', 'desc');

        // Get paginated results
        $questions = $query->paginate($perPage, ['id', 'academic_classes_id', 'subject_id', 'chapter_id', 'topic_id', 'level_id', 'board_id', 'format', 'question_type', 'question_text', 'explanation', 'mark', 'display_order', 'created_at'], 'page', $page);

        // Get filter data (cached with smaller data)
        $filterData = $this->getFilterData();

        return Inertia::render('Question/Index', [
            'questions' => $questions->items(),
            'pagination' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
            ],
            'filters' => [
                'education' => $educationId,
                'class' => $classId,
                'subject' => $subjectId,
                'chapter' => $chapterId,
                'topic' => $topicId,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'education' => $filterData['education'],
            'classes' => $filterData['classes'],
            'subjects' => $filterData['subjects'],
            'chapters' => $filterData['chapters'],
            'topics' => $filterData['topics'],
            'levels' => $filterData['levels'],
            'types' => $filterData['types'],
            'boards' => $filterData['boards'],
        ]);
    }

    /**
     * Get filter data with caching
     */
    private function getFilterData()
    {
        // Try to get from cache
        $cacheKey = 'question_filter_data_v2';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // If not in cache, fetch and store
        $data = [
            'education' => Education::select('id', 'name')->get(),
            'classes' => AcademicClass::select('id', 'name', 'education_id')->get(),
            'subjects' => Subject::select('id', 'name', 'academic_classes_id')->get(),
            'chapters' => Chapter::select('id', 'name', 'subject_id')->get(),
            'topics' => Topic::select('id', 'name', 'chapter_id')->get(),
            'levels' => Level::select('id', 'name')->get(),
            'types' => Type::select('id', 'name')->get(),
            'boards' => board::select('id', 'name')->get(),
        ];

        // Store in cache with compression
        Cache::put($cacheKey, $data, 3600);

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
        return Inertia::render('Question/Form', [
            'topics' => Topic::all(),
            'levels' => Level::all(),
            'types' => Type::all(),
            'boards' => board::all(),
            'chapters' => Chapter::all(),
            'subjects' => Subject::all(),
            'classes' => AcademicClass::all(),
            'education' => Education::all()
        ]);
    } catch (\Exception $e) {
        \Log::error('Create page error: ' . $e->getMessage());
        return redirect()->route('question.index')
            ->withErrors(['error' => 'Failed to load create page: ' . $e->getMessage()]);
    }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validation rules
            $rules = $this->getValidationRules($request);
            
            // Manually validate with custom error handling
            $validator = validator($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $validated = $validator->validated();

            DB::beginTransaction();

            // Create the question
            $questionData = $this->prepareQuestionData($validated);
            $question = Question::create($questionData);
            
            // Attach types
            if (isset($validated['type_id'])) {
                $question->type()->attach($validated['type_id']);
            }

            // Handle based on question type
            if ($validated['question_type'] === 'standalone') {
                $this->storeStandaloneQuestion($question, $validated);
            } else {
                $this->storeStimulusBasedQuestion($question, $validated);
            }

            DB::commit();

            // Clear cache after new question
            Cache::forget('question_filter_data_v2');

            return redirect()->back()->with('message', 'Question created successfully!');

        } catch (ValidationException $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Question creation failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create question: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Get validation rules based on question type
     */
    private function getValidationRules($request)
    {
        $rules = [
            'class_id' => 'required|exists:academic_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'chapter_id' => 'required|exists:chapters,id',
            'topic_id' => 'required|exists:topics,id',
            'type_id' => 'required|array|min:1',
            'type_id.*' => 'exists:types,id',
            'level_id' => 'required|exists:levels,id',
            'board_id' => 'nullable|exists:boards,id',
            'question_type' => 'required|in:standalone,stimulus_based',
            'format' => 'required|in:mcq,cq,mix',
            'display_order' => 'nullable|integer|min:0',
        ];

        if ($request->question_type === 'standalone') {
            $rules['question_text'] = 'required|string';
            $rules['explanation'] = 'nullable|string';
            
            // MCQ format - has options
            if ($request->format === 'mcq') {
                $rules['options'] = 'required|array|min:2';
                $rules['options.*.option_text'] = 'required|string|max:500';
                $rules['options.*.is_correct'] = 'required|boolean';
            }
            
            // CQ format - has cq options
            if ($request->format === 'cq') {
                $rules['cq'] = 'required|array|min:1';
                $rules['cq.*.cq_text'] = 'required|string|max:500';
                $rules['cq.*.mark'] = 'required|numeric|min:0';
            }
            
            // MIX format - only question_text and explanation
            if ($request->format === 'mix') {
                $rules['options'] = 'nullable|array';
                $rules['cq'] = 'nullable|array';
                $rules['mark'] = 'nullable|numeric|min:0';
            }
        } else {
            // Stimulus based validation
            $rules['stimuli'] = 'required|array|min:1';
            $rules['stimuli.*.stimulus_type'] = 'required|in:text,image,audio,video,table,passage';
            $rules['stimuli.*.content'] = 'required|string';
            $rules['stimuli.*.media_url'] = 'nullable|string';
            $rules['stimuli.*.caption'] = 'nullable|string';
            
            $rules['sub_questions'] = 'required|array|min:1';
            $rules['sub_questions.*.format'] = 'required|in:mcq,cq';
            $rules['sub_questions.*.question_text'] = 'required|string';
            $rules['sub_questions.*.explanation'] = 'nullable|string';
            $rules['sub_questions.*.mark'] = 'nullable|numeric|min:0';
            $rules['sub_questions.*.question_number'] = 'required|integer|min:1';
            
            $rules['sub_questions.*.options'] = 'nullable|array|required_if:sub_questions.*.format,mcq|min:2';
            $rules['sub_questions.*.options.*.option_text'] = 'nullable|string|required_with:sub_questions.*.options';
            $rules['sub_questions.*.options.*.is_correct'] = 'nullable|boolean';
            
            $rules['sub_questions.*.cq_options'] = 'nullable|array|required_if:sub_questions.*.format,cq';
            $rules['sub_questions.*.cq_options.*.cq_text'] = 'nullable|string|required_with:sub_questions.*.cq_options';
            $rules['sub_questions.*.cq_options.*.mark'] = 'nullable|numeric|min:0';
        }

        return $rules;
    }

    /**
     * Prepare question data for creation/update
     */
    private function prepareQuestionData($validated)
    {
        $data = [
            'academic_classes_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'chapter_id' => $validated['chapter_id'],
            'topic_id' => $validated['topic_id'],
            'level_id' => $validated['level_id'],
            'board_id' => $validated['board_id'] ?? null,
            'format' => $validated['format'],
            'question_type' => $validated['question_type'],
            'display_order' => $validated['display_order'] ?? 0,
        ];

        if ($validated['question_type'] === 'standalone') {
            $data['question_text'] = $validated['question_text'] ?? null;
            $data['explanation'] = $validated['explanation'] ?? null;
            $data['mark'] = $validated['mark'] ?? null;
        } else {
            $data['question_text'] = null;
            $data['explanation'] = null;
            $data['mark'] = null;
        }

        return $data;
    }

    /**
     * Store standalone question
     */
    private function storeStandaloneQuestion($question, $validated)
    {
        try {
            // Create sub-question
            $subQuestion = sub_question::create([
                'parent_question_id' => $question->id,
                'stimulus_id' => null,
                'format' => $validated['format'] === 'mix' ? 'mcq' : $validated['format'],
                'question_text' => $validated['question_text'] ?? '',
                'explanation' => $validated['explanation'] ?? null,
                'mark' => $validated['mark'] ?? null,
                'question_number' => 1,
                'display_order' => 0
            ]);

            // For MCQ format - create options
            if ($validated['format'] === 'mcq' && isset($validated['options'])) {
                foreach ($validated['options'] as $option) {
                    if (!empty($option['option_text'])) {
                        QuestionOption::create([
                            'sub_question_id' => $subQuestion->id,
                            'option_text' => $option['option_text'],
                            'is_correct' => $option['is_correct'] ?? false,
                            'display_order' => 0
                        ]);
                    }
                }
            }

            // For CQ format - create cq options
            if ($validated['format'] === 'cq' && isset($validated['cq'])) {
                foreach ($validated['cq'] as $cq) {
                    if (!empty($cq['cq_text'])) {
                        Cqoption::create([
                            'sub_question_id' => $subQuestion->id,
                            'cq_text' => $cq['cq_text'],
                            'mark' => $cq['mark'] ?? 0,
                            'display_order' => 0
                        ]);
                    }
                }
            }

            // For MIX format - only question_text and explanation, no options or cq_options
            // Nothing to create for mix format

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store stimulus based question
     */
    private function storeStimulusBasedQuestion($question, $validated)
    {
        try {
            // Create stimuli
            $stimuliIds = [];
            foreach ($validated['stimuli'] as $stimulusData) {
                $stimulus = Stimulus::create([
                    'question_id' => $question->id,
                    'stimulus_type' => $stimulusData['stimulus_type'],
                    'content' => $stimulusData['content'],
                    'media_url' => $stimulusData['media_url'] ?? null,
                    'caption' => $stimulusData['caption'] ?? null,
                    'display_order' => $stimulusData['display_order'] ?? 0
                ]);
                $stimuliIds[] = $stimulus->id;
            }

            // Create sub-questions
            foreach ($validated['sub_questions'] as $subQData) {
                $subQuestion = sub_question::create([
                    'parent_question_id' => $question->id,
                    'stimulus_id' => $subQData['stimulus_id'] ?? ($stimuliIds[0] ?? null),
                    'format' => $subQData['format'],
                    'question_text' => $subQData['question_text'],
                    'explanation' => $subQData['explanation'] ?? null,
                    'mark' => $subQData['mark'] ?? null,
                    'question_number' => $subQData['question_number'] ?? 1,
                    'display_order' => $subQData['display_order'] ?? 0
                ]);

                // Create options for MCQ sub-question
                if ($subQData['format'] === 'mcq' && isset($subQData['options'])) {
                    foreach ($subQData['options'] as $option) {
                        if (!empty($option['option_text'])) {
                            QuestionOption::create([
                                'sub_question_id' => $subQuestion->id,
                                'option_text' => $option['option_text'],
                                'is_correct' => $option['is_correct'] ?? false,
                                'display_order' => $option['display_order'] ?? 0
                            ]);
                        }
                    }
                }

                // Create CQ options for CQ sub-question
                if ($subQData['format'] === 'cq' && isset($subQData['cq_options'])) {
                    foreach ($subQData['cq_options'] as $cq) {
                        if (!empty($cq['cq_text'])) {
                            Cqoption::create([
                                'sub_question_id' => $subQuestion->id,
                                'cq_text' => $cq['cq_text'],
                                'mark' => $cq['mark'] ?? 0,
                                'display_order' => $cq['display_order'] ?? 0
                            ]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $question = Question::with([
                'topic',
                'type',
                'board',
                'options',
                'cqoptions',
                'subQuestions.options',
                'subQuestions.cqoptions',
                'stimuli'
            ])->findOrFail($id);

            return Inertia::render('Question/Show', [
                'question' => $question
            ]);

        } catch (\Exception $e) {
            Log::error('Question show error: ' . $e->getMessage());
            return redirect()->route('question.index')
                ->withErrors(['error' => 'Question not found']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Question $question)
    {
        $question->load([
            'topic',
            'options',
            'cqoptions',
            'type',
            'subQuestions.options',
            'subQuestions.cqoptions',
            'stimuli'
        ]);

        return Inertia::render('Question/Form', [
            'question' => $question,
            'topics' => Topic::all(),
            'levels' => Level::all(),
            'types' => Type::all(),
            'boards' => board::all(),
            'chapters' => Chapter::all(),
            'subjects' => Subject::all(),
            'classes' => AcademicClass::all(),
            'education' => Education::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Question $question)
    {
        try {
            // Validation rules
            $rules = $this->getValidationRules($request);
            
            // Manually validate with custom error handling
            $validator = validator($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput();
            }

            $validated = $validator->validated();

            DB::beginTransaction();

            // Update question
            $questionData = $this->prepareQuestionData($validated);
            $question->update($questionData);
            
            // Sync types
            if (isset($validated['type_id'])) {
                $question->type()->sync($validated['type_id']);
            }

            // Delete existing sub-questions and stimuli
            $question->subQuestions()->delete();
            $question->stimuli()->delete();

            // Handle based on question type
            if ($validated['question_type'] === 'standalone') {
                $this->storeStandaloneQuestion($question, $validated);
            } else {
                $this->storeStimulusBasedQuestion($question, $validated);
            }

            DB::commit();

            // Clear cache after update
            Cache::forget('question_filter_data_v2');

            return redirect()->route('question.index')
                ->with('message', 'Question updated successfully!');

        } catch (ValidationException $e) {
            DB::rollBack();
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Question update failed: ' . $e->getMessage());
            
            return redirect()->back()
                ->withErrors(['error' => 'Failed to update question: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Question $question)
    {
        try {
            DB::beginTransaction();

            // Delete related data
            $question->subQuestions()->delete();
            $question->stimuli()->delete();
            $question->options()->delete();
            $question->cqoptions()->delete();
            $question->type()->detach();
            
            // Delete the question
            $question->delete();

            DB::commit();

            // Clear cache after deletion
            Cache::forget('question_filter_data_v2');

            return redirect()->route('question.index')
                ->with('message', 'Question deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Question deletion failed: ' . $e->getMessage());
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete question: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk delete questions
     */
    public function bulkDelete(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:questions,id'
            ]);

            DB::beginTransaction();

            foreach ($request->ids as $id) {
                $question = Question::find($id);
                if ($question) {
                    $question->subQuestions()->delete();
                    $question->stimuli()->delete();
                    $question->options()->delete();
                    $question->cqoptions()->delete();
                    $question->type()->detach();
                    $question->delete();
                }
            }

            DB::commit();

            // Clear cache after bulk delete
            Cache::forget('question_filter_data_v2');

            return redirect()->route('question.index')
                ->with('message', 'Questions deleted successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk delete failed: ' . $e->getMessage());
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete questions: ' . $e->getMessage()]);
        }
    }

    /**
     * Duplicate a question
     */
    public function duplicate(Question $question)
    {
        try {
            DB::beginTransaction();

            // Load all relationships
            $question->load([
                'type',
                'subQuestions.options',
                'subQuestions.cqoptions',
                'stimuli'
            ]);

            // Create new question
            $newQuestion = $question->replicate();
            $newQuestion->question_text = $question->question_type === 'standalone' 
                ? $question->question_text . ' (Copy)' 
                : null;
            $newQuestion->save();

            // Copy types
            if ($question->type->isNotEmpty()) {
                $newQuestion->type()->attach($question->type->pluck('id')->toArray());
            }

            // Copy sub-questions
            foreach ($question->subQuestions as $subQ) {
                $newSubQ = $subQ->replicate();
                $newSubQ->parent_question_id = $newQuestion->id;
                $newSubQ->save();

                // Copy MCQ options
                foreach ($subQ->options as $option) {
                    $newOption = $option->replicate();
                    $newOption->sub_question_id = $newSubQ->id;
                    $newOption->save();
                }

                // Copy CQ options
                foreach ($subQ->cqoptions as $cq) {
                    $newCq = $cq->replicate();
                    $newCq->sub_question_id = $newSubQ->id;
                    $newCq->save();
                }
            }

            // Copy stimuli
            foreach ($question->stimuli as $stimulus) {
                $newStimulus = $stimulus->replicate();
                $newStimulus->question_id = $newQuestion->id;
                $newStimulus->save();
            }

            DB::commit();

            // Clear cache after duplication
            Cache::forget('question_filter_data_v2');

            return redirect()->route('question.index')
                ->with('message', 'Question duplicated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Question duplication failed: ' . $e->getMessage());
            return redirect()->back()
                ->withErrors(['error' => 'Failed to duplicate question: ' . $e->getMessage()]);
        }
    }

    /**
     * Get questions by class and subject for API
     */
    public function getQuestionsBySubject(Request $request)
    {
        try {
            $request->validate([
                'class_id' => 'required|exists:academic_classes,id',
                'subject_id' => 'required|exists:subjects,id'
            ]);

            $questions = Question::with([
                'subQuestions.options',
                'subQuestions.cqoptions',
                'stimuli'
            ])
            ->where('academic_classes_id', $request->class_id)
            ->where('subject_id', $request->subject_id)
            ->select('id', 'question_text', 'format', 'question_type')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $questions
            ]);

        } catch (\Exception $e) {
            Log::error('API error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch questions'
            ], 500);
        }
    }

    /**
     * Clear cache manually
     */
    public function clearCache()
    {
        try {
            Cache::forget('question_filter_data_v2');
            return redirect()->back()
                ->with('message', 'Cache cleared successfully!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to clear cache: ' . $e->getMessage()]);
        }
    }
}