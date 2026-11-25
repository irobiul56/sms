<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mark extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'institute_subject_id',
        'institute_class_id',
        'section_id',
        'teacher_id',
        'theory_marks',
        'practical_marks',
        'mcq_marks',
        'total_marks',
        'grade_point',
        'grade',
        'remarks',
        'is_absent',
        'is_reexamine'
    ];

    protected $casts = [
        'theory_marks' => 'decimal:2',
        'practical_marks' => 'decimal:2',
        'mcq_marks' => 'decimal:2',
        'total_marks' => 'decimal:2',
        'grade_point' => 'decimal:2',
        'is_absent' => 'boolean',
        'is_reexamine' => 'boolean'
    ];

    /**
     * Get the exam that owns the mark.
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the student that owns the mark.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the subject that owns the mark.
     */
    public function instituteSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'institute_subject_id');
    }

    /**
     * Get the class that owns the mark.
     */
    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    /**
     * Get the section that owns the mark.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the teacher that owns the mark.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the grade label.
     */
    public function getGradeLabelAttribute()
    {
        return $this->grade ?? 'N/A';
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        if ($this->is_absent) {
            return 'Absent';
        }
        if ($this->is_reexamine) {
            return 'Re-examine';
        }
        return 'Present';
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute()
    {
        if ($this->is_absent) {
            return 'bg-red-100 text-red-800';
        }
        if ($this->is_reexamine) {
            return 'bg-yellow-100 text-yellow-800';
        }
        return 'bg-green-100 text-green-800';
    }

    /**
     * Scope a query to filter by exam.
     */
    public function scopeForExam($query, $examId)
    {
        return $query->where('exam_id', $examId);
    }

    /**
     * Scope a query to filter by class.
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('institute_class_id', $classId);
    }

    /**
     * Scope a query to filter by subject.
     */
    public function scopeForSubject($query, $subjectId)
    {
        return $query->where('institute_subject_id', $subjectId);
    }

    /**
     * Scope a query to filter by student.
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Calculate total marks.
     */
     public static function calculateTotal($theory, $practical, $mcq)
    {
        $total = 0;
        if ($theory) $total += $theory;
        if ($practical) $total += $practical;
        if ($mcq) $total += $mcq;
        return $total;
    }
}