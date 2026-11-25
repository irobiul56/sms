<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassTeacher extends Model
{
    use HasFactory;

    protected $table = 'class_teachers';

    protected $fillable = [
        'teacher_id',
        'institute_class_id',
        'section_id',
        'academic_year_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the teacher.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the institute class.
     */
    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    /**
     * Get the section.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the academic year.
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}