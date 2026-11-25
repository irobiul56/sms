<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'academic_year_id',
        'institute_class_id',
        'name',
        'bn_name',
        'type',
        'start_date',
        'end_date',
        'is_published',
        'note'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_published' => 'boolean'
    ];

    /**
     * Get the academic year that owns the exam.
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the institute class that owns the exam.
     */
    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    public function classes()
    {
        return $this->belongsToMany(InstituteClass::class, 'exam_institute_classes')
            ->withTimestamps();
    }

    public function classIds(): array
    {
        $classIds = $this->relationLoaded('classes')
            ? $this->classes->pluck('id')
            : $this->classes()->pluck('institute_classes.id');

        return $classIds
            ->push($this->institute_class_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the exam subjects.
     */
    public function examSubjects()
    {
        return $this->hasMany(ExamInstituteSubjec::class);
    }

    /**
     * Get the marks for this exam.
     */
    public function marks()
    {
        return $this->hasMany(Mark::class);
    }

    /**
     * Get the results for this exam.
     */
    public function results()
    {
        return $this->hasMany(Result::class);
    }

    /**
     * Get the type label in Bangla.
     */
    public function getTypeLabelAttribute()
    {
        $types = [
            'monthly' => 'Monthly (মাসিক)',
            'term' => 'Term (টার্ম)',
            'half-yearly' => 'Half-Yearly (অর্ধ-বার্ষিক)',
            'final' => 'Final (বার্ষিক)',
            'test' => 'Test (টেস্ট)',
            'pre-test' => 'Pre-Test (প্রাক-পরীক্ষা)'
        ];
        return $types[$this->type] ?? $this->type;
    }

    /**
     * Get the type badge class for UI.
     */
    public function getTypeBadgeClassAttribute()
    {
        $classes = [
            'monthly' => 'bg-blue-100 text-blue-800',
            'term' => 'bg-green-100 text-green-800',
            'half-yearly' => 'bg-yellow-100 text-yellow-800',
            'final' => 'bg-purple-100 text-purple-800',
            'test' => 'bg-gray-100 text-gray-800',
            'pre-test' => 'bg-red-100 text-red-800'
        ];
        return $classes[$this->type] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        $today = now()->toDateString();

        if ($this->is_published) {
            return 'Published';
        } elseif ($this->end_date < $today) {
            return 'Completed';
        } elseif ($this->start_date <= $today && $this->end_date >= $today) {
            return 'Ongoing';
        } else {
            return 'Upcoming';
        }
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute()
    {
        $today = now()->toDateString();

        if ($this->is_published) {
            return 'bg-green-100 text-green-800';
        } elseif ($this->end_date < $today) {
            return 'bg-gray-100 text-gray-800';
        } elseif ($this->start_date <= $today && $this->end_date >= $today) {
            return 'bg-yellow-100 text-yellow-800';
        } else {
            return 'bg-blue-100 text-blue-800';
        }
    }

    /**
     * Scope a query to filter by academic year.
     */
    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Scope a query to filter by class.
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('institute_class_id', $classId);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeForType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include published exams.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to only include upcoming exams.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now()->toDateString());
    }

    /**
     * Scope a query to only include ongoing exams.
     */
    public function scopeOngoing($query)
    {
        $today = now()->toDateString();
        return $query->where('start_date', '<=', $today)
                     ->where('end_date', '>=', $today);
    }

    /**
     * Scope a query to only include completed exams.
     */
    public function scopeCompleted($query)
    {
        return $query->where('end_date', '<', now()->toDateString());
    }
}