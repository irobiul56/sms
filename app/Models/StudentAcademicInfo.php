<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAcademicInfo extends Model
{
    use HasFactory;

    protected $table = 'student_academic_infos';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'institute_class_id',
        'section_id',
        'roll_number',
        'monthly_fee',
        'group',
        'elective_subject_id',
        'optional_subject_id',
        'additional_subject_id',
        'attendance_days',
        'is_promoted',
        'remarks'
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    // Belongs to a student
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Belongs to an academic year
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    // Belongs to a class
    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class);
    }

    // Belongs to a section
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function electiveSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'elective_subject_id');
    }

    public function optionalSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'optional_subject_id');
    }

    public function additionalSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'additional_subject_id');
    }
}