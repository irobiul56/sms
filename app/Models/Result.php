<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'institute_class_id',
        'section_id',
        'total_subjects',
        'total_marks',
        'total_full_marks',
        'total_point',
        'gpa',
        'final_grade',
        'total_absent',
        'position',
        'class_position',
        'remarks',
        'is_published',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'total_full_marks' => 'decimal:2',
        'total_point' => 'decimal:2',
        'gpa' => 'decimal:2',
        'is_published' => 'boolean',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
    
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
    
    public function class()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }
    
    public function marks()
    {
        return $this->hasMany(Mark::class, 'student_id', 'student_id')
            ->where('exam_id', $this->exam_id);
    }
}
