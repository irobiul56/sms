<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamInstituteSubjec extends Model
{
    use HasFactory;

    protected $table = 'exam_institute_subjecs';

    protected $fillable = [
        'exam_id',
        'institute_subject_id',
        'institute_class_id',
        'exam_date',
        'start_time',
        'end_time',
        'full_marks',
        'pass_marks',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'full_marks' => 'integer',
        'pass_marks' => 'integer',
    ];

    /**
     * Get the exam that owns the exam subject.
     */
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the institute subject.
     */
    public function instituteSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'institute_subject_id');
    }

    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class);
    }

    /**
     * Get the marks for this exam subject.
     */
    public function marks()
    {
        return $this->hasMany(Mark::class, 'exam_id', 'exam_id')
            ->where('institute_subject_id', $this->institute_subject_id)
            ->where('institute_class_id', $this->institute_class_id);
    }

    /**
     * Get formatted time range.
     */
    public function getTimeRangeAttribute()
    {
        return $this->start_time->format('h:i A') . ' - ' . $this->end_time->format('h:i A');
    }

    /**
     * Get formatted date.
     */
    public function getFormattedDateAttribute()
    {
        return $this->exam_date->format('d M, Y');
    }

    /**
     * ✅ Get duration in hours and minutes.
     */
    public function getDurationAttribute()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        $diffInMinutes = $start->diffInMinutes($end);
        
        $hours = floor($diffInMinutes / 60);
        $minutes = $diffInMinutes % 60;
        
        $duration = '';
        if ($hours > 0) {
            $duration .= $hours . 'h ';
        }
        if ($minutes > 0) {
            $duration .= $minutes . 'm';
        }
        if (empty($duration)) {
            $duration = '0m';
        }
        
        return trim($duration);
    }

    /**
     * ✅ Get duration in minutes.
     */
    public function getDurationInMinutesAttribute()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $start->diffInMinutes($end);
    }

    /**
     * ✅ Get duration in hours (decimal).
     */
    public function getDurationInHoursAttribute()
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return round($start->diffInMinutes($end) / 60, 2);
    }

    /**
     * Scope a query to filter by exam.
     */
    public function scopeForExam($query, $examId)
    {
        return $query->where('exam_id', $examId);
    }
}