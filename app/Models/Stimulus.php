<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stimulus extends Model
{
    protected $guarded = [];

    protected $casts = [
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ============ Relationships ============
    
    /**
     * Get the question that owns this stimulus
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get all sub-questions for this stimulus
     */
    public function subQuestions(): HasMany
    {
        return $this->hasMany(sub_question::class);
    }

    // ============ Accessors ============
    
    /**
     * Get formatted content with HTML
     */
    public function getFormattedContentAttribute(): string
    {
        return nl2br(e($this->content));
    }

    /**
     * Check if stimulus has media
     */
    public function getHasMediaAttribute(): bool
    {
        return !empty($this->media_url);
    }

    /**
     * Get full media URL
     */
    public function getMediaUrlAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        
        return asset('storage/' . $value);
    }

    // ============ Scopes ============
    
    /**
     * Scope for text stimuli
     */
    public function scopeText($query)
    {
        return $query->where('stimulus_type', 'text');
    }

    /**
     * Scope for image stimuli
     */
    public function scopeImage($query)
    {
        return $query->where('stimulus_type', 'image');
    }

    /**
     * Scope for passage stimuli
     */
    public function scopePassage($query)
    {
        return $query->where('stimulus_type', 'passage');
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // ============ Helper Methods ============
    
    /**
     * Get stimulus type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            'text' => 'Text',
            'image' => 'Image',
            'audio' => 'Audio',
            'video' => 'Video',
            'table' => 'Table',
            'passage' => 'Passage'
        ];
        
        return $labels[$this->stimulus_type] ?? ucfirst($this->stimulus_type);
    }
}