<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutedClassInstituteSubject extends Model
{
    use HasFactory;

    protected $table = 'institute_class_institute_subjects';

    protected $fillable = [
        'institute_class_id',
        'institute_subject_id',
        'type',
        'full_marks',
        'pass_marks',
        'is_group_based',
        'group_name'
    ];

    protected $casts = [
        'full_marks' => 'integer',
        'pass_marks' => 'integer',
        'is_group_based' => 'boolean'
    ];

    /**
     * Get the institute class.
     */
    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    /**
     * Get the institute subject.
     */
    public function instituteSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'institute_subject_id');
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute()
    {
        $types = [
            'compulsory' => 'Compulsory',
            'optional' => 'Optional',
            'elective' => 'Elective'
        ];
        return $types[$this->type] ?? $this->type;
    }
}