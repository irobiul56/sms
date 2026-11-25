<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\CombinedResultController;
use App\Http\Controllers\ClassRoutineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\ExamBlogController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamSubjectController;
use App\Http\Controllers\ExamDocumentController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\HomeworkController;
use App\Http\Controllers\InstituteClassController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\MyQuestionController;
use App\Http\Controllers\InstituteController;
use App\Http\Controllers\InstituteSubjectController;
use App\Http\Controllers\InstitutePortalController;
use App\Http\Controllers\MarkController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentHomeworkController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherPermissionController;
use App\Http\Controllers\Auth\TeacherDashboardController;
use App\Http\Controllers\TeacherSubjectController;
use App\Models\Blog;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/public/marksheets/{exam}/{student}', [ExamDocumentController::class, 'publicMarksheet'])
    ->middleware('signed')
    ->name('public.marksheet');

Route::get('/', function () {
     $bloglist = Blog::with([
                'category',
                'questions' => function($query) {
                    $query->with([
                        'academicClass',
                        'subject',
                        'lavel',
                        'options'
                        ])
                        ->get();
                }
            ])->orderBy('id', 'desc')->get();

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'bloglist' => $bloglist
    ]);
})->name('home');

Route::get('/online-exam/{slug}', [ExamBlogController::class, 'onlineexamshow'])->name('onlineexamshow');
Route::get('/contact-us', [FrontendController::class, 'contact'])->name('contact');
Route::get('/about-us', [FrontendController::class, 'about'])->name('about');
Route::get('/terms-of-service', [FrontendController::class, 'tos'])->name('tos');
Route::get('/privacy-policy', [FrontendController::class, 'privacypolicy'])->name('privacypolicy');

Route::middleware(['auth','admin']) -> group(function () {
        Route::prefix('settings')->group(function () {
        Route::resource('board', BoardController::class);
        Route::resource('category', CategoryController::class);
        Route::resource('level', LevelController::class);
        Route::resource('type', TypeController::class);
        Route::resource('education', EducationLevelController::class);
        Route::resource('class', ClassController::class);
        Route::put('/class/update/{education}', [ClassController::class, 'update'])->name('class.bulk-update');
        Route::resource('subject', SubjectController::class);
        Route::get('/classes/by-education/{id}', [SubjectController::class, 'getClassesByEducation'])->name('classes.by-education');
        Route::resource('chapter', ChapterController::class);
        Route::resource('topic', TopicController::class);
        Route::resource('question', QuestionController::class);
        });
        
        Route::get('/blog-post-form', [ExamBlogController::class, 'blogexamform'])->name('blogexamform');
        Route::get('/selected-question-blog', [ExamBlogController::class, 'sltquestionblog'])->name('sltquestionblog');
        Route::post('/save-questions-blog', [ExamBlogController::class, 'saveQuestionsBlog'])->name('save.questions.blog');
        Route::get('blog-list', [ExamBlogController::class, 'BlogList'])->name('blog.list');
    });
    

      // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard')->middleware(['auth','admin']);
    

    Route::middleware(['auth', 'user'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    Route::get('/teacher/dashboard', TeacherDashboardController::class)
        ->middleware(['auth', 'teacher'])
        ->name('teacher.dashboard');

    Route::get('/student/dashboard', StudentDashboardController::class)
        ->middleware(['auth', 'student'])
        ->name('student.dashboard');
    Route::post('/student/homework/{homework}/toggle', [StudentHomeworkController::class, 'toggle'])
        ->middleware(['auth', 'student'])
        ->name('student.homework.toggle');

    Route::middleware(['auth', 'user'])->prefix('user')->group(function(){
    //Dashboard
    Route::get('/dashboard', [FrontendController::class, 'userdashboard'])->name('userdashboard');
    Route::put('/institute/update', [FrontendController::class, 'updateIn'])->name('institute.update');
    Route::get('/institute', [InstituteController::class, 'institute'])->name('institute');

    Route::get('/institute/dashboard', [DashboardController::class, 'index'])->name('institute.dashboard');
    Route::get('/institute/dashboard/stats', [DashboardController::class, 'stats'])->name('institute.dashboard.stats');
    Route::get('/institute/dashboard/chart-data', [DashboardController::class, 'chartData'])->name('institute.dashboard.chart-data');
    Route::get('/institute/dashboard/recent-activities', [DashboardController::class, 'recentActivities'])->name('institute.dashboard.recent-activities');
    Route::get('/institute/dashboard/notifications', [DashboardController::class, 'notifications'])
        ->name('institute.dashboard.notifications');

    Route::get('/results', [InstitutePortalController::class, 'results'])
        ->name('results.index');
    Route::get('/combined-results', [CombinedResultController::class, 'index'])
        ->name('combined-results.index');
    Route::post('/combined-results', [CombinedResultController::class, 'generate'])
        ->name('combined-results.generate');
    Route::post('/combined-results/finalize', [CombinedResultController::class, 'finalize'])
        ->name('combined-results.finalize');
    Route::get('/results/publish', [InstitutePortalController::class, 'publishResults'])
        ->name('results.publish');
    Route::post('/results/{result}/toggle-publish', [InstitutePortalController::class, 'toggleResultPublish'])
        ->name('results.toggle-publish');
    Route::post('/results/generate/{exam}', [InstitutePortalController::class, 'generateResults'])
        ->name('results.generate');
    Route::get('/exam-documents', [ExamDocumentController::class, 'index'])
        ->name('exam-documents.index');
    Route::get('/exam-documents/{exam}/marksheet/{student}', [ExamDocumentController::class, 'marksheet'])
        ->name('exam-documents.marksheet');
    Route::get('/exam-documents/{exam}/marksheets', [ExamDocumentController::class, 'classMarksheets'])
        ->name('exam-documents.class-marksheets');
    Route::get('/exam-documents/{exam}/tabulation-sheet', [ExamDocumentController::class, 'classTabulationSheet'])
        ->name('exam-documents.class-tabulation-sheet');
    Route::get('/exam-documents/{exam}/admit-card/{student}', [ExamDocumentController::class, 'admitCard'])
        ->name('exam-documents.admit-card');
    Route::get('/exam-documents/{exam}/admit-cards', [ExamDocumentController::class, 'classAdmitCards'])
        ->name('exam-documents.class-admit-cards');
    Route::get('/exam-documents/{exam}/seat-plan', [ExamDocumentController::class, 'seatPlan'])
        ->name('exam-documents.seat-plan');
    Route::get('/grades', [InstitutePortalController::class, 'grades'])
        ->name('grades.index')
        ->middleware(['auth', 'verified']);
    Route::post('/grades', [InstitutePortalController::class, 'storeGrade'])
        ->name('grades.store')
        ->middleware(['auth', 'verified']);
    Route::post('/grades/defaults', [InstitutePortalController::class, 'seedDefaultGrades'])
        ->name('grades.defaults')
        ->middleware(['auth', 'verified']);
    Route::put('/grades/{grade}', [InstitutePortalController::class, 'updateGrade'])
        ->name('grades.update')
        ->middleware(['auth', 'verified']);
    Route::delete('/grades/{grade}', [InstitutePortalController::class, 'destroyGrade'])
        ->name('grades.destroy')
        ->middleware(['auth', 'verified']);
    Route::get('/attendance', [AttendanceController::class, 'index'])
        ->name('attendance.index')
        ->middleware(['auth', 'verified']);
    Route::get('/attendance/report', [AttendanceController::class, 'report'])
        ->name('attendance.report')
        ->middleware(['auth', 'verified']);
    Route::get('/attendance/roster', [AttendanceController::class, 'roster'])
        ->name('attendance.roster')
        ->middleware(['auth', 'verified']);
    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->name('attendance.store')
        ->middleware(['auth', 'verified']);
    Route::get('/homework', [HomeworkController::class, 'index'])
        ->name('homework.index')
        ->middleware(['auth', 'verified']);
    Route::post('/homework', [HomeworkController::class, 'store'])
        ->name('homework.store')
        ->middleware(['auth', 'verified']);
    Route::put('/homework/{homework}', [HomeworkController::class, 'update'])
        ->name('homework.update')
        ->middleware(['auth', 'verified']);
    Route::delete('/homework/{homework}', [HomeworkController::class, 'destroy'])
        ->name('homework.destroy')
        ->middleware(['auth', 'verified']);
    Route::resource('class-routines', ClassRoutineController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware(['auth', 'verified']);

    // Question routes
    Route::get('/question-making', [FrontendController::class, 'qstIndex'])->name('qstIndex');
    Route::get('/selected-question', [FrontendController::class, 'sltquestion'])->name('sltquestion');
    Route::post('/save-questions', [FrontendController::class, 'saveQuestions'])->name('save.questions');
    Route::get('/question-setting/{exam_id?}', [FrontendController::class, 'qstSetting'])->name('qstSetting');
    Route::get('/question-show/{id}', [FrontendController::class, 'qstshow'])->name('qstshow');
    Route::resource('my-questions', MyQuestionController::class);
    Route::get('/contact-us', [ContactUsController::class, 'contactus'])->name('contactus');
    Route::get('/opinion', [ContactUsController::class, 'opinion'])->name('opinion');


    // Result Management System Route
    // Academic Years Routes
    Route::resource('academic-years', AcademicYearController::class);

    // Additional routes
    Route::prefix('academic-years')->name('academic-years.')->middleware(['auth', 'verified'])->group(function () {
        Route::post('/{academicYear}/set-current', [AcademicYearController::class, 'setCurrent'])
            ->name('set-current');
        Route::get('/current', [AcademicYearController::class, 'getCurrent'])
            ->name('get-current');
        Route::get('/list', [AcademicYearController::class, 'getYears'])
            ->name('list');

    });

    // Classes Routes
    Route::prefix('classes')->name('classes.')->middleware(['auth', 'verified'])->group(function () {
        Route::get('/get-classes', [InstituteClassController::class, 'getClasses'])
            ->name('get-classes');
        Route::get('/{classId}/sections', [InstituteClassController::class, 'getSections'])
            ->name('get-sections');
        Route::get('/stats', [InstituteClassController::class, 'getStats'])
            ->name('stats');
    });
    Route::resource('classes', InstituteClassController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->middleware(['auth', 'verified']);

// Sections Routes
    Route::resource('sections', SectionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware(['auth', 'verified']);

    // Additional routes
    Route::prefix('sections')->name('sections.')->middleware(['auth', 'verified'])->group(function () {
        Route::get('/by-class/{classId}', [SectionController::class, 'getSectionsByClass'])
            ->name('by-class');
        Route::get('/details/{id}', [SectionController::class, 'getSectionDetails'])
            ->name('details');
        Route::get('/stats', [SectionController::class, 'getStats'])
            ->name('stats');
        });

    Route::get('/students/by-class-section', [StudentController::class, 'getByClassSection'])
        ->name('students.by-class-section')
        ->middleware(['auth', 'verified']);

    // Students Routes
    Route::get('/students/print', [StudentController::class, 'print'])
        ->name('students.print')
        ->middleware(['auth', 'verified']);
    Route::get('/students/import', [BulkImportController::class, 'students'])
        ->name('students.import')
        ->middleware(['auth', 'verified']);
    Route::post('/students/import', [BulkImportController::class, 'importStudents'])
        ->name('students.import.store')
        ->middleware(['auth', 'verified']);

    Route::resource('students', StudentController::class)
        ->middleware(['auth', 'verified']);

    // Additional routes
    Route::prefix('students')->name('students.')->middleware(['auth', 'verified'])->group(function () {
        Route::get('/by-class/{classId}', [StudentController::class, 'getStudentsByClass'])
            ->name('by-class');
        Route::post('/{student}/toggle-status', [StudentController::class, 'toggleStatus'])
            ->name('toggle-status');
   
    });

    // Subject Routes
    Route::get('/subjects/import', [BulkImportController::class, 'subjects'])
        ->name('subjects.import')
        ->middleware(['auth', 'verified']);
    Route::post('/subjects/import', [BulkImportController::class, 'importSubjects'])
        ->name('subjects.import.store')
        ->middleware(['auth', 'verified']);
    Route::resource('subjects', InstituteSubjectController::class)
        ->middleware(['auth', 'verified']);

    // Additional routes
    Route::prefix('subjects')->name('subjects.')->middleware(['auth', 'verified'])->group(function () {
        Route::post('/combine-papers', [InstituteSubjectController::class, 'combinePapers'])
            ->name('combine-papers');
        Route::get('/by-class/{classId}', [InstituteSubjectController::class, 'getSubjectsByClass'])
            ->name('by-class');
    });

    // Teacher Routes
    Route::get('/teachers/import', [BulkImportController::class, 'teachers'])
        ->name('teachers.import')
        ->middleware(['auth', 'verified']);
    Route::post('/teachers/import', [BulkImportController::class, 'importTeachers'])
        ->name('teachers.import.store')
        ->middleware(['auth', 'verified']);

    Route::resource('teachers', TeacherController::class)
        ->middleware(['auth', 'verified']);

    Route::get('/teacher-permissions', [TeacherPermissionController::class, 'index'])
        ->name('teacher-permissions.index');
    Route::put('/teacher-permissions/{teacher}', [TeacherPermissionController::class, 'update'])
        ->name('teacher-permissions.update');

    // Additional routes
    Route::prefix('teachers')->name('teachers.')->middleware(['auth', 'verified'])->group(function () {
        Route::post('/{teacher}/toggle-status', [TeacherController::class, 'toggleStatus'])
            ->name('toggle-status');
    });

    // ✅ Teacher Subjects Index Page (Main Entry Point)
    Route::get('/teacher-subjects', [TeacherSubjectController::class, 'index'])
        ->name('teacher-subjects.index');
    
    // ✅ View assignments for a specific teacher
    Route::get('/teacher-subjects/{teacher}', [TeacherSubjectController::class, 'show'])
        ->name('teacher-subjects.show');
    
    // ✅ Create new assignment
    Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store'])
        ->name('teacher-subjects.store');
    
    // ✅ Update assignment
    Route::put('/teacher-subjects/{teacherSubject}', [TeacherSubjectController::class, 'update'])
        ->name('teacher-subjects.update');
    
    // ✅ Delete assignment
    Route::delete('/teacher-subjects/{teacherSubject}', [TeacherSubjectController::class, 'destroy'])
        ->name('teacher-subjects.destroy');
    
    // ✅ Toggle status
    Route::post('/teacher-subjects/{teacherSubject}/toggle-status', [TeacherSubjectController::class, 'toggleStatus'])
        ->name('teacher-subjects.toggle-status');
    
    // ✅ Get assignments for a teacher (AJAX)
    Route::get('/teacher-subjects/{teacher}/assignments', [TeacherSubjectController::class, 'getAssignments'])
        ->name('teacher-subjects.assignments');
    
    // ✅ Get teachers for dropdown (AJAX)
    Route::get('/teachers/list', [TeacherController::class, 'getTeachersList'])
        ->name('teachers.list');


    // Exam Routes
    Route::resource('exams', ExamController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware(['auth', 'verified']);

    // Additional routes
    Route::prefix('exams')->name('exams.')->middleware(['auth', 'verified'])->group(function () {
        Route::post('/{exam}/toggle-publish', [ExamController::class, 'togglePublish'])
            ->name('toggle-publish');
    });

    // Exam Subjects Routes
        Route::resource('exam-subjects', ExamSubjectController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware(['auth', 'verified']);

        // Additional routes
        Route::prefix('exam-subjects')->name('exam-subjects.')->middleware(['auth', 'verified'])->group(function () {
            Route::get('/routine/print', [ExamSubjectController::class, 'routinePrint'])
                ->name('routine-print');
            Route::get('/by-exam/{examId}', [ExamSubjectController::class, 'getSubjectsByExam'])
                ->name('by-exam');
        });

       // Marks Routes
   // Marks Routes - IMPORTANT: Keep this order
    Route::prefix('marks')->name('marks.')->group(function () {
        Route::get('/get-students', [MarkController::class, 'getStudents'])
            ->name('get-students');
        Route::get('/entry', [MarkController::class, 'create'])
            ->name('entry');
    });
    
    // Resource route must be after the custom routes
    Route::resource('marks', MarkController::class)->except(['create']);



});
require __DIR__.'/auth.php';
