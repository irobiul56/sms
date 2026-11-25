<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homework';

    protected $fillable = [
        'institute_id',
        'academic_year_id',
        'institute_class_id',
        'section_id',
        'institute_subject_id',
        'teacher_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_published' => 'boolean',
        ];
    }

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function instituteClass()
    {
        return $this->belongsTo(InstituteClass::class, 'institute_class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function instituteSubject()
    {
        return $this->belongsTo(InstituteSubject::class, 'institute_subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
