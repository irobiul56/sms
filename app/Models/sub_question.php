<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class sub_question extends Model
{
    protected $table = 'sub_questions';

    protected $guarded = [];

    protected $casts = [
        'question_number' => 'integer',
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ============ Relationships ============
    
    /**
     * Get the parent question
     */
    public function parentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'parent_question_id');
    }

    /**
     * Get the stimulus for this sub-question
     */
    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(Stimulus::class);
    }

    /**
     * Get the options for this sub-question (MCQ)
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    /**
     * Get the CQ options for this sub-question
     */
    public function cqoptions(): HasMany
    {
        return $this->hasMany(Cqoption::class);
    }

    // ============ Accessors ============
    
    /**
     * Get formatted question number with dot
     */
    public function getFormattedNumberAttribute(): string
    {
        return $this->question_number . '. ';
    }

    /**
     * Get full question with number
     */
    public function getFullQuestionAttribute(): string
    {
        return $this->formatted_number . $this->question_text;
    }

    // ============ Scopes ============
    
    /**
     * Scope for MCQ sub-questions
     */
    public function scopeMcq($query)
    {
        return $query->where('format', 'mcq');
    }

    /**
     * Scope for CQ sub-questions
     */
    public function scopeCq($query)
    {
        return $query->where('format', 'cq');
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Scope to order by question number
     */
    public function scopeNumbered($query)
    {
        return $query->orderBy('question_number');
    }

    // ============ Helper Methods ============
    
    /**
     * Check if sub-question is MCQ
     */
    public function isMcq(): bool
    {
        return $this->format === 'mcq';
    }

    /**
     * Check if sub-question is CQ
     */
    public function isCq(): bool
    {
        return $this->format === 'cq';
    }

    /**
     * Get correct options for MCQ
     */
    public function getCorrectOptions()
    {
        if ($this->isMcq()) {
            return $this->options()->where('is_correct', true)->get();
        }
        return collect();
    }

    /**
     * Get total marks
     */
    public function getTotalMarks(): float
    {
        if ($this->isMcq()) {
            return (float) $this->mark;
        }
        
        return $this->cqoptions()->sum('mark');
    }
}