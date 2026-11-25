<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'teacher_subjects';

    protected $fillable = [
        'teacher_id',
        'institute_subject_id',
        'institute_class_id',
        'section_id',
        'academic_year_id',
        'is_primary',
        'is_active',
        'permissions',
        'display_order',
        'note'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'permissions' => 'array',
        'display_order' => 'integer'
    ];

    /**
     * Get the teacher.
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the subject.
     */
    public function instituteSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'institute_subject_id');
    }

    /**
     * Get the class.
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

    /**
     * Scope a query to filter by institute.
     */
    public function scopeForInstitute($query, $instituteId)
    {
        return $query->whereHas('teacher', function($q) use ($instituteId) {
            $q->where('institute_id', $instituteId);
        });
    }

    /**
     * Scope a query to filter by academic year.
     */
    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Scope a query to filter by teacher.
     */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
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
     * Scope a query to only include active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include primary teachers.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Get the display name for the assignment.
     */
    public function getDisplayNameAttribute()
    {
        $name = $this->teacher->name . ' - ' . $this->instituteSubject->name;
        $name .= ' (' . $this->instituteClass->name;
        if ($this->section) {
            $name .= ' - ' . $this->section->name;
        }
        $name .= ')';
        return $name;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute()
    {
        return $this->is_active 
            ? 'bg-green-100 text-green-800' 
            : 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute()
    {
        return $this->is_primary ? 'Primary' : 'Secondary';
    }

    /**
     * Get the type badge class.
     */
    public function getTypeBadgeClassAttribute()
    {
        return $this->is_primary 
            ? 'bg-yellow-100 text-yellow-800' 
            : 'bg-gray-100 text-gray-800';
    }
}