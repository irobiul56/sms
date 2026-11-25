<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombinedResult extends Model
{
    protected $fillable = [
        'institute_id',
        'institute_class_id',
        'academic_year_id',
        'section_id',
        'selection_key',
        'report',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'report' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
