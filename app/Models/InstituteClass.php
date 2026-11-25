<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstituteClass extends Model
{
    use HasFactory;

    protected $table = 'institute_classes';

    protected $fillable = [
        'institute_id',
        'name',
        'bn_name',
        'numeric_value',
        'group',
        'display_order',
        'has_elective'
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    // Belongs to an institute
    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    // Has many sections
    public function sections()
    {
        return $this->hasMany(Section::class)
            ->where('institute_id', $this->institute_id);
    }

    public function subjects()
    {
        return $this->belongsToMany(
            InstituteSubject::class,
            'institute_class_institute_subjects',
            'institute_class_id',
            'institute_subject_id'
        )->withPivot('type', 'full_marks', 'pass_marks', 'is_group_based', 'group_name')
            ->withTimestamps()
            ->where('institute_subjects.institute_id', $this->institute_id);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class, 'institute_class_id');
    }

    // Has many student academic infos
    public function studentAcademicInfos()
    {
        return $this->hasMany(StudentAcademicInfo::class);
    }

    // Has many students through academic infos
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            StudentAcademicInfo::class,
            'institute_class_id',  // Foreign key on student_academic_infos
            'id',                  // Foreign key on students
            'id',                  // Local key on institute_classes
            'student_id'           // Local key on student_academic_infos
        )->where('students.institute_id', $this->institute_id);
    }

    // Has many teachers through class_teacher pivot
    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'class_teacher')
            ->where('teachers.institute_id', $this->institute_id);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeForInstitute($query, $instituteId)
    {
        return $query->where('institute_id', $instituteId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('numeric_value');
    }
}