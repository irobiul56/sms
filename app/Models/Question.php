<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $guarded = [];

    protected $casts = [
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ============ Existing Relationships ============
    
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_classes_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function lavel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id');
    }

    public function type()
    {
        return $this->belongsToMany(Type::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(board::class);
    }

    // ============ 🔥 NEW: Level Accessor ============
    /**
     * Get level data with properly cast is_inline
     */
    public function getLevelDataAttribute()
    {
        if (!$this->relationLoaded('lavel') && !$this->lavel) {
            return null;
        }
        
        $level = $this->lavel;
        if (!$level) {
            return null;
        }
        
        return [
            'id' => $level->id,
            'name' => $level->name,
            'is_inline' => (bool) $level->is_inline, // 🔥 Explicit boolean cast
            'created_at' => $level->created_at,
            'updated_at' => $level->updated_at,
        ];
    }

    // ============ 🔥 NEW: Override toArray method ============
    public function toArray()
    {
        $array = parent::toArray();
        
        // Add level_data to the array
        $array['level_data'] = $this->getLevelDataAttribute();
        
        // Also keep the original lavel relationship data but ensure is_inline is boolean
        if (isset($array['lavel'])) {
            $array['lavel']['is_inline'] = (bool) $array['lavel']['is_inline'];
        }
        
        return $array;
    }

    // ============ 🔥 NEW: Override toJson method ============
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    // ============ Existing Relationships (Modified for Backward Compatibility) ============
    
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)
            ->whereNull('sub_question_id');
    }

    public function cqoptions(): HasMany
    {
        return $this->hasMany(Cqoption::class)
            ->whereNull('sub_question_id');
    }

    public function savedQuestions()
    {
        return $this->belongsToMany(SavedQuestion::class, 'question_savedquestion');
    }

    // ============ New Relationships ============
    
    public function subQuestions(): HasMany
    {
        return $this->hasMany(sub_question::class, 'parent_question_id');
    }

    public function stimuli(): HasMany
    {
        return $this->hasMany(Stimulus::class);
    }

    public function allOptions()
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function allCqOptions()
    {
        return $this->hasMany(Cqoption::class);
    }

    // ============ Accessors & Mutators ============
    
    public function getQuestionTextAttribute($value)
    {
        if ($this->subQuestions()->exists()) {
            $subQuestion = $this->subQuestions()->first();
            return $subQuestion ? $subQuestion->question_text : $value;
        }
        return $value;
    }

    public function getExplanationAttribute($value)
    {
        if ($this->subQuestions()->exists()) {
            $subQuestion = $this->subQuestions()->first();
            return $subQuestion ? $subQuestion->explanation : $value;
        }
        return $value;
    }

    public function getMarkAttribute($value)
    {
        if ($this->subQuestions()->exists()) {
            $subQuestion = $this->subQuestions()->first();
            return $subQuestion ? $subQuestion->mark : $value;
        }
        return $value;
    }

    // ============ Scopes ============
    
    public function scopeStandalone($query)
    {
        return $query->where('question_type', 'standalone');
    }

    public function scopeStimulusBased($query)
    {
        return $query->where('question_type', 'stimulus_based');
    }

    public function scopeMcq($query)
    {
        return $query->where('format', 'mcq');
    }

    public function scopeCq($query)
    {
        return $query->where('format', 'cq');
    }

    public function scopeMix($query)
    {
        return $query->where('format', 'mix');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // ============ Helper Methods ============
    
    public function isStandalone(): bool
    {
        return $this->question_type === 'standalone';
    }

    public function isStimulusBased(): bool
    {
        return $this->question_type === 'stimulus_based';
    }

    public function getDisplayOptions()
    {
        if ($this->isStimulusBased() || $this->subQuestions()->exists()) {
            $subQuestion = $this->subQuestions()->first();
            if ($subQuestion) {
                return $subQuestion->options;
            }
        }
        return $this->options;
    }

    public function getDisplayCqOptions()
    {
        if ($this->isStimulusBased() || $this->subQuestions()->exists()) {
            $subQuestion = $this->subQuestions()->first();
            if ($subQuestion) {
                return $subQuestion->cqoptions;
            }
        }
        return $this->cqoptions;
    }

    public function toApiResponse(): array
    {
        $data = $this->toArray();
        
        if ($this->isStimulusBased()) {
            $data['stimuli'] = $this->stimuli()->ordered()->get();
            $data['sub_questions'] = $this->subQuestions()
                ->with(['options', 'cqoptions'])
                ->ordered()
                ->get();
        } else {
            $subQuestion = $this->subQuestions()->first();
            if ($subQuestion) {
                $data['question_text'] = $subQuestion->question_text;
                $data['explanation'] = $subQuestion->explanation;
                $data['mark'] = $subQuestion->mark;
                $data['options'] = $subQuestion->options;
                $data['cqoptions'] = $subQuestion->cqoptions;
            }
        }
        
        return $data;
    }
}