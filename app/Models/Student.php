<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'institute_id',
        'student_id',
        'roll_no',
        'registration_no',
        'name',
        'bn_name',
        'father_name',
        'mother_name',
        'date_of_birth',
        'gender',
        'religion',
        'blood_group',
        'phone',
        'email',
        'address',
        'photo',
        'status'
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    // Student belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Student belongs to an institute
    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    // Student has many academic infos
    public function academicInfos()
    {
        return $this->hasMany(StudentAcademicInfo::class);
    }

    // Student has one current academic info (for current year)
    public function currentAcademicInfo()
    {
        return $this->hasOne(StudentAcademicInfo::class)
                    ->whereHas('academicYear', function($q) {
                        $q->where('is_current', true);
                    });
    }

    // Student has one current class (through academic info)
    public function currentClass()
    {
        return $this->hasOneThrough(
            InstituteClass::class,
            StudentAcademicInfo::class,
            'student_id',          // Foreign key on student_academic_infos
            'id',                  // Foreign key on institute_classes
            'id',                  // Local key on students
            'institute_class_id'   // Local key on student_academic_infos
        );
    }

    // Student has one current section (through academic info)
    public function currentSection()
    {
        return $this->hasOneThrough(
            Section::class,
            StudentAcademicInfo::class,
            'student_id',    // Foreign key on student_academic_infos
            'id',            // Foreign key on sections
            'id',            // Local key on students
            'section_id'     // Local key on student_academic_infos
        );
    }

    // Student has one current academic year (through academic info)
    public function currentAcademicYear()
    {
        return $this->hasOneThrough(
            AcademicYear::class,
            StudentAcademicInfo::class,
            'student_id',        // Foreign key on student_academic_infos
            'id',                // Foreign key on academic_years
            'id',                // Local key on students
            'academic_year_id'   // Local key on student_academic_infos
        );
    }

    // Student has many marks
    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    // Student has many results
    public function results()
    {
        return $this->hasMany(Result::class);
    }

    // Student has many attendances
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // ============================================
    // SCOPES (Query Helpers)
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForInstitute($query, $instituteId)
    {
        return $query->where('institute_id', $instituteId);
    }


   public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            // If photo is stored in storage/app/public/student-photos/
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    /**
     * Get the photo for display.
     */
    public function getPhotoDisplayAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }
}