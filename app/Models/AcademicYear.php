<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AcademicYear extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'institute_id',
        'name',
        'year',
        'session',
        'start_date',
        'end_date',
        'is_current'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the institute that owns the academic year.
     */
    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Get the student academic infos for the academic year.
     */
    public function studentAcademicInfos(): HasMany
    {
        return $this->hasMany(StudentAcademicInfo::class);
    }

    /**
     * Get the students through academic infos.
     */
    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            StudentAcademicInfo::class,
            'academic_year_id',  // Foreign key on student_academic_infos
            'id',                // Foreign key on students
            'id',                // Local key on academic_years
            'student_id'         // Local key on student_academic_infos
        );
    }

    /**
     * Get the classes associated with this academic year.
     * Add this if you have a classes table with academic_year_id
     */
    public function classes(): HasMany
    {
        return $this->hasMany(InstituteClass::class);
    }
    
    public function instituteClasses(): HasMany
    {
        return $this->hasMany(InstituteClass::class);
    }

    /**
     * Get the exams associated with this academic year.
     * Add this if you have an exams table with academic_year_id
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * Get the fees associated with this academic year.
     * Add this if you have a fees table with academic_year_id
     */


    /**
     * Get the attendances associated with this academic year.
     * Add this if you have an attendances table with academic_year_id
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the admissions associated with this academic year.
     * Add this if you have an admissions table with academic_year_id
     */

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope a query to only include current academic year.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope a query to only include academic years for a specific institute.
     */
    public function scopeForInstitute($query, $instituteId)
    {
        return $query->where('institute_id', $instituteId);
    }

    /**
     * Scope a query to only include active academic years (based on dates).
     */
    public function scopeActive($query)
    {
        return $query->where('is_current', true)
            ->orWhere(function ($q) {
                $q->whereDate('start_date', '<=', now())
                  ->whereDate('end_date', '>=', now());
            });
    }

    /**
     * Scope a query to get academic years by session.
     */
    public function scopeBySession($query, $session)
    {
        return $query->where('session', $session);
    }

    /**
     * Scope a query to get academic years by year.
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if this academic year is currently active based on dates.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_current) {
            return false;
        }

        $now = now();
        return $now->between($this->start_date, $this->end_date);
    }

    /**
     * Get the formatted date range for display.
     */
    public function getDateRangeAttribute(): string
    {
        return $this->start_date->format('M d, Y') . ' - ' . $this->end_date->format('M d, Y');
    }

    /**
     * Get the formatted date range with year.
     */
    public function getDateRangeWithYearAttribute(): string
    {
        return $this->start_date->format('d/m/Y') . ' - ' . $this->end_date->format('d/m/Y');
    }

    /**
     * Get the duration in months.
     */
    public function getDurationInMonthsAttribute(): int
    {
        return $this->start_date->diffInMonths($this->end_date);
    }

    /**
     * Get the duration in days.
     */
    public function getDurationInDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->is_current) {
            return 'Current';
        } elseif (now()->between($this->start_date, $this->end_date)) {
            return 'Active';
        } elseif (now()->lt($this->start_date)) {
            return 'Upcoming';
        } else {
            return 'Completed';
        }
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColorAttribute(): string
    {
        if ($this->is_current) {
            return 'green';
        } elseif (now()->between($this->start_date, $this->end_date)) {
            return 'blue';
        } elseif (now()->lt($this->start_date)) {
            return 'yellow';
        } else {
            return 'gray';
        }
    }

    /**
     * Check if this academic year has any related records.
     */
    public function hasRelatedRecords(): bool
    {
        return $this->studentAcademicInfos()->exists() ||
               $this->classes()->exists() ||
               $this->exams()->exists() ||
               $this->fees()->exists() ||
               $this->attendances()->exists() ||
               $this->admissions()->exists();
    }

    /**
     * Get all related record counts for display.
     */
    public function getRelatedCountsAttribute(): array
    {
        return [
            'students' => $this->studentAcademicInfos()->count(),
            'classes' => $this->classes()->count(),
            'exams' => $this->exams()->count(),
            'fees' => $this->fees()->count(),
            'attendances' => $this->attendances()->count(),
            'admissions' => $this->admissions()->count(),
        ];
    }

    /**
     * Get the session display.
     */
    public function getSessionDisplayAttribute(): string
    {
        return $this->session ?? $this->year . '-' . ($this->year + 1);
    }

    /**
     * Get the academic year code (session without dash).
     */
    public function getCodeAttribute(): string
    {
        return str_replace('-', '', $this->session);
    }

    /**
     * Check if a date falls within this academic year.
     */
    public function isDateInRange($date): bool
    {
        $date = $date instanceof \DateTime ? $date : new \DateTime($date);
        return $date >= $this->start_date && $date <= $this->end_date;
    }

    // ============================================
    // BOOT METHOD
    // ============================================

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent deletion of the current academic year
        static::deleting(function ($academicYear) {
            if ($academicYear->is_current) {
                throw new \Exception('Cannot delete the current academic year. Please set another year as current first.');
            }
        });

        // Ensure only one current academic year per institute
        static::saving(function ($academicYear) {
            if ($academicYear->is_current) {
                AcademicYear::where('institute_id', $academicYear->institute_id)
                    ->where('id', '!=', $academicYear->id)
                    ->update(['is_current' => false]);
            }
        });

        // Validate that end_date is after start_date
        static::creating(function ($academicYear) {
            if ($academicYear->end_date <= $academicYear->start_date) {
                throw new \Exception('End date must be after start date.');
            }
        });

        static::updating(function ($academicYear) {
            if ($academicYear->end_date <= $academicYear->start_date) {
                throw new \Exception('End date must be after start date.');
            }
        });
    }

    // ============================================
    // ACCESSORS & MUTATORS
    // ============================================

    /**
     * Set the session attribute.
     */
    public function setSessionAttribute($value)
    {
        $this->attributes['session'] = $value;
    }

    /**
     * Get the name with session.
     */
    public function getNameWithSessionAttribute(): string
    {
        return $this->name . ' (' . $this->session . ')';
    }

    /**
     * Get the full title for display.
     */
    public function getFullTitleAttribute(): string
    {
        return $this->name . ' - ' . $this->session;
    }
}