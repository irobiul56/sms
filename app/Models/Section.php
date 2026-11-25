<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'institute_id',
        'institute_class_id',
        'name',
        'room_number',
        'capacity',
        'display_order'
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the institute that owns the section.
     */
    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Get the institute class that owns the section.
     */
    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    /**
     * Get the exams for this section.
     */
    public function exams()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the student academic info for this section.
     */
    public function studentAcademicInfos()
    {
        return $this->hasMany(StudentAcademicInfo::class);
    }

    /**
     * Get the students for this section through student_academic_infos.
     */
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            StudentAcademicInfo::class,
            'section_id',     // Foreign key on student_academic_infos
            'id',             // Foreign key on students
            'id',             // Local key on sections
            'student_id'      // Local key on student_academic_infos
        )->where('students.institute_id', $this->institute_id);
    }

    /**
     * Get the active students for this section.
     */
    public function activeStudents()
    {
        return $this->students()->where('students.status', 'active');
    }

    /**
     * Get the marks for this section.
     */
    public function marks()
    {
        return $this->hasMany(Mark::class, 'section_id');
    }

    /**
     * Get the results for this section.
     */
    public function results()
    {
        return $this->hasMany(Result::class, 'section_id');
    }

    /**
     * Get the attendances for this section.
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'section_id');
    }

    /**
     * Get the class teachers for this section.
     */
    public function classTeachers()
    {
        return $this->hasMany(ClassTeacher::class, 'section_id');
    }

    /**
     * Get the teacher for this section (current academic year).
     */
    public function teacher()
    {
        return $this->hasOneThrough(
            Teacher::class,
            ClassTeacher::class,
            'section_id',     // Foreign key on class_teachers
            'id',             // Foreign key on teachers
            'id',             // Local key on sections
            'teacher_id'      // Local key on class_teachers
        )->whereHas('academicYear', function($query) {
            $query->where('is_current', true);
        });
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope a query to filter by institute.
     */
    public function scopeForInstitute($query, $instituteId)
    {
        return $query->where('institute_id', $instituteId);
    }

    /**
     * Scope a query to filter by class.
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('institute_class_id', $classId);
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Scope a query to only include sections with capacity.
     */
    public function scopeHasCapacity($query)
    {
        return $query->where('capacity', '>', 0);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get the full section name with class.
     */
    public function getFullNameAttribute()
    {
        $className = $this->instituteClass->name ?? 'Unknown';
        return $className . ' - ' . $this->name;
    }

    /**
     * Get the student count.
     */
    public function getStudentCountAttribute()
    {
        return $this->students()->count();
    }

    /**
     * Get the active student count.
     */
    public function getActiveStudentCountAttribute()
    {
        return $this->activeStudents()->count();
    }

    /**
     * Get the capacity status.
     */
    public function getCapacityStatusAttribute()
    {
        $studentCount = $this->student_count;
        $capacity = $this->capacity ?? 50;
        
        if ($studentCount >= $capacity) {
            return 'Full';
        } elseif ($studentCount >= $capacity * 0.8) {
            return 'Almost Full';
        } elseif ($studentCount >= $capacity * 0.5) {
            return 'Half Full';
        } else {
            return 'Available';
        }
    }

    /**
     * Get the capacity status badge class.
     */
    public function getCapacityStatusBadgeClassAttribute()
    {
        $statuses = [
            'Full' => 'bg-red-100 text-red-800',
            'Almost Full' => 'bg-yellow-100 text-yellow-800',
            'Half Full' => 'bg-blue-100 text-blue-800',
            'Available' => 'bg-green-100 text-green-800'
        ];
        return $statuses[$this->capacity_status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the available seats.
     */
    public function getAvailableSeatsAttribute()
    {
        $capacity = $this->capacity ?? 50;
        $studentCount = $this->student_count;
        return max(0, $capacity - $studentCount);
    }

    /**
     * Get the result count for this section.
     */
    public function getResultCountAttribute()
    {
        return $this->results()->count();
    }

    /**
     * Get the pass count for this section.
     */
    public function getPassCountAttribute()
    {
        return $this->results()->where('gpa', '>=', 2.00)->count();
    }

    /**
     * Get the fail count for this section.
     */
    public function getFailCountAttribute()
    {
        return $this->results()->where('gpa', '<', 2.00)->count();
    }

    /**
     * Get the pass percentage.
     */
    public function getPassPercentageAttribute()
    {
        $total = $this->result_count;
        if ($total === 0) return 0;
        return round(($this->pass_count / $total) * 100, 2);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if section has students.
     */
    public function hasStudents()
    {
        return $this->students()->exists();
    }

    /**
     * Check if section has capacity.
     */
    public function hasCapacity()
    {
        return $this->student_count < ($this->capacity ?? 50);
    }

    /**
     * Get students for a specific academic year.
     */
    public function getStudentsForYear($academicYearId)
    {
        return $this->studentAcademicInfos()
                    ->where('academic_year_id', $academicYearId)
                    ->with('student')
                    ->get()
                    ->pluck('student');
    }

    /**
     * Get results for a specific exam.
     */
    public function getResultsForExam($examId)
    {
        return $this->results()->where('exam_id', $examId)->get();
    }

    /**
     * Get section statistics.
     */
    public function getStatistics()
    {
        return [
            'total_students' => $this->student_count,
            'active_students' => $this->active_student_count,
            'total_results' => $this->result_count,
            'passed' => $this->pass_count,
            'failed' => $this->fail_count,
            'pass_percentage' => $this->pass_percentage,
            'capacity' => $this->capacity,
            'available_seats' => $this->available_seats,
            'capacity_status' => $this->capacity_status
        ];
    }

    /**
     * Get subject-wise performance for this section.
     */

}