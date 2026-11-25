<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentHomeworkCompletion extends Model
{
    protected $fillable = [
        'homework_id',
        'student_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function homework()
    {
        return $this->belongsTo(Homework::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
