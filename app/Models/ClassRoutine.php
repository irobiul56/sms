<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassRoutine extends Model
{
    protected $fillable = [
        'institute_id',
        'academic_year_id',
        'institute_class_id',
        'section_id',
        'institute_subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'classroom',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function instituteSubject()
    {
        return $this->belongsTo(InstituteSubject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
