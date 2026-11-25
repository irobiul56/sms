<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstituteSubject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'institute_subjects';

    protected $fillable = [
        'institute_id',
        'institute_class_id',
        'name',
        'bn_name',
        'code',
        'type',
        'full_marks',
        'pass_marks',
        'theory_marks',
        'theory_pass_marks',
        'practical_marks',
        'practical_pass_marks',
        'mcq_marks',
        'mcq_pass_marks',
        'display_order',
        'has_practical'
    ];

    protected $casts = [
        'full_marks' => 'integer',
        'pass_marks' => 'integer',
        'theory_marks' => 'integer',
        'theory_pass_marks' => 'decimal:2',
        'practical_marks' => 'integer',
        'practical_pass_marks' => 'decimal:2',
        'mcq_marks' => 'integer',
        'mcq_pass_marks' => 'decimal:2',
        'display_order' => 'integer',
        'has_practical' => 'boolean'
    ];

    /**
     * Get the institute that owns the subject.
     */
    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Get the classes for this subject.
     */
    public function classes()
    {
        return $this->belongsToMany(
            InstituteClass::class,
            'institute_class_institute_subjects',
            'institute_subject_id',
            'institute_class_id'
        )->withPivot('type', 'full_marks', 'pass_marks', 'is_group_based', 'group_name')
         ->withTimestamps();
    }

    /**
     * Get the marks for this subject.
     */
    public function marks()
    {
        return $this->hasMany(Mark::class, 'institute_subject_id');
    }

    /**
     * Get the exam subjects for this subject.
     */
    public function examSubjects()
    {
        return $this->hasMany(ExamInstituteSubjec::class, 'institute_subject_id');
    }

    /**
     * Get the type label in Bangla.
     */
    public function getTypeLabelAttribute()
    {
        $types = [
            'compulsory' => 'Compulsory (বাধ্যতামূলক)',
            'optional' => 'Optional (ঐচ্ছিক)',
            'elective' => 'Elective (ইলেকটিভ)',
            'additional' => 'Additional (অতিরিক্ত)'
        ];
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Get the type badge class.
     */
    public function getTypeBadgeClassAttribute()
    {
        $classes = [
            'compulsory' => 'bg-blue-100 text-blue-800',
            'optional' => 'bg-green-100 text-green-800',
            'elective' => 'bg-purple-100 text-purple-800',
            'additional' => 'bg-yellow-100 text-yellow-800'
        ];
        return $classes[$this->type] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the full marks display.
     */
    public function getFullMarksDisplayAttribute()
    {
        $marks = $this->full_marks;
        if ($this->has_practical) {
            $marks .= ' (Theory: ' . ($this->theory_marks ?? 0) . ', Practical: ' . ($this->practical_marks ?? 0) . ')';
        }
        if ($this->mcq_marks) {
            $marks .= ' (MCQ: ' . $this->mcq_marks . ')';
        }
        return $marks;
    }

    /**
     * Scope a query to filter by institute.
     */
    public function scopeForInstitute($query, $instituteId)
    {
        return $query->where('institute_id', $instituteId);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeForType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * Scope a query to only include active subjects.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}