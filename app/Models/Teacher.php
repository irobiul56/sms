<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'institute_id',
        'employee_id',
        'name',
        'bn_name',
        'designation',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'joining_date',
        'qualification',
        'address',
        'photo',
        'status'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date'
    ];

    /**
     * Get the user that owns the teacher.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institute that owns the teacher.
     */
    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Get the class teachers for this teacher.
     */
    public function classTeachers()
    {
        return $this->hasMany(ClassTeacher::class);
    }

    /**
     * Get the classes assigned to this teacher.
     */
    public function classes()
    {
        return $this->belongsToMany(
            InstituteClass::class,
            'class_teachers',
            'teacher_id',
            'institute_class_id'
        )->withPivot('section_id', 'academic_year_id')
         ->withTimestamps()
         ->where('institute_classes.institute_id', $this->institute_id);
    }

    /**
     * Get the sections assigned to this teacher.
     */
    public function sections()
    {
        return $this->belongsToMany(
            Section::class,
            'class_teachers',
            'teacher_id',
            'section_id'
        )->withPivot('institute_class_id', 'academic_year_id')
         ->withTimestamps()
         ->where('sections.institute_id', $this->institute_id);
    }

    /**
     * Get the academic years assigned to this teacher.
     */
    public function academicYears()
    {
        return $this->belongsToMany(
            AcademicYear::class,
            'class_teachers',
            'teacher_id',
            'academic_year_id'
        )->withPivot('institute_class_id', 'section_id')
         ->withTimestamps();
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        $statuses = [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'retired' => 'Retired',
            'transferred' => 'Transferred'
        ];
        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute()
    {
        $classes = [
            'active' => 'bg-green-100 text-green-800',
            'inactive' => 'bg-gray-100 text-gray-800',
            'retired' => 'bg-yellow-100 text-yellow-800',
            'transferred' => 'bg-blue-100 text-blue-800'
        ];
        return $classes[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the gender label.
     */
    public function getGenderLabelAttribute()
    {
        $genders = [
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other'
        ];
        return $genders[$this->gender] ?? $this->gender;
    }

    /**
     * Get the photo URL.
     */
    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    /**
     * Scope a query to filter by institute.
     */
    public function scopeForInstitute($query, $instituteId)
    {
        return $query->where('institute_id', $instituteId);
    }

    /**
     * Scope a query to only include active teachers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

     /**
     * Get the subject assignments for this teacher.
     */
    public function subjectAssignments()
    {
        return $this->hasMany(TeacherSubject::class);
    }

    /**
     * Get the subjects assigned to this teacher.
     */
    public function subjects()
    {
        return $this->belongsToMany(
            InstituteSubject::class,
            'teacher_subjects',
            'teacher_id',
            'institute_subject_id'
        )->withPivot('institute_class_id', 'section_id', 'academic_year_id', 'is_primary', 'is_active', 'display_order', 'note')
         ->withTimestamps();
    }

    /**
     * Get the classes assigned to this teacher.
     */
    public function assignedClasses()
    {
        return $this->belongsToMany(
            InstituteClass::class,
            'teacher_subjects',
            'teacher_id',
            'institute_class_id'
        )->withPivot('institute_subject_id', 'section_id', 'academic_year_id', 'is_primary')
         ->withTimestamps()
         ->distinct();
    }

    /**
     * Get the primary subjects for this teacher.
     */
    public function primarySubjects()
    {
        return $this->subjects()->wherePivot('is_primary', true);
    }

    /**
     * Get the active subjects for this teacher.
     */
    public function activeSubjects()
    {
        return $this->subjects()->wherePivot('is_active', true);
    }

    /**
     * Get subjects for a specific academic year.
     */
    public function subjectsForYear($academicYearId)
    {
        return $this->subjects()->wherePivot('academic_year_id', $academicYearId);
    }

    /**
     * Get subjects for a specific class.
     */
    public function subjectsForClass($classId)
    {
        return $this->subjects()->wherePivot('institute_class_id', $classId);
    }

    /**
     * Get subjects for a specific class and year.
     */
    public function subjectsForClassAndYear($classId, $academicYearId)
    {
        return $this->subjects()
            ->wherePivot('institute_class_id', $classId)
            ->wherePivot('academic_year_id', $academicYearId);
    }

    /**
     * Check if teacher teaches a subject in a class.
     */
    public function teachesSubject($subjectId, $classId, $academicYearId = null)
    {
        $query = $this->subjects()
            ->wherePivot('institute_subject_id', $subjectId)
            ->wherePivot('institute_class_id', $classId);
        
        if ($academicYearId) {
            $query->wherePivot('academic_year_id', $academicYearId);
        }
        
        return $query->exists();
    }

    /**
     * Get teacher's subject load count.
     */
    public function getSubjectLoadCount($academicYearId = null)
    {
        $query = $this->subjectAssignments();
        
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }
        
        return $query->count();
    }

    /**
     * Get teacher's class load count.
     */
    public function getClassLoadCount($academicYearId = null)
    {
        $query = $this->subjectAssignments()->distinct('institute_class_id');
        
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }
        
        return $query->count('institute_class_id');
    }
}