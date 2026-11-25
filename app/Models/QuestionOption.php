<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_correct' => 'boolean',
        'display_order' => 'integer'
    ];

    // ============ Existing Relationships ============
    
    /**
     * Get the question that owns this option (legacy)
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    // ============ New Relationships ============
    
    /**
     * Get the sub-question that owns this option
     */
    public function subQuestion(): BelongsTo
    {
        return $this->belongsTo(sub_question::class);
    }

    // ============ Scopes ============
    
    /**
     * Scope for correct options
     */
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    /**
     * Scope for incorrect options
     */
    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // ============ Accessors ============
    
    /**
     * Get option label (A, B, C, D...)
     */
    public function getOptionLabelAttribute(): string
    {
        $labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $index = $this->display_order ?? ($this->id - 1);
        
        return $labels[$index] ?? '?';
    }

    /**
     * Get formatted option (e.g., "A. Option text")
     */
    public function getFormattedOptionAttribute(): string
    {
        return $this->option_label . '. ' . $this->option_text;
    }
}