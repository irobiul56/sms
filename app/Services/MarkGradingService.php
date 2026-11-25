<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\InstituteSubject;
use App\Models\Mark;
use Illuminate\Support\Collection;

class MarkGradingService
{
    public function failsTheoryOrMcq(
        InstituteSubject $subject,
        float|int|null $theoryMarks,
        float|int|null $mcqMarks
    ): bool {
        return $this->failsMarkComponents($subject, [
            'theory_marks' => $theoryMarks,
            'mcq_marks' => $mcqMarks,
        ]);
    }

    public function failsMarkComponents(
        InstituteSubject $subject,
        array $marks,
        ?float $examFullMarks = null
    ): bool {
        foreach ([
            'theory_marks' => ['maximum' => 'theory_marks', 'pass' => 'theory_pass_marks'],
            'mcq_marks' => ['maximum' => 'mcq_marks', 'pass' => 'mcq_pass_marks'],
            'practical_marks' => ['maximum' => 'practical_marks', 'pass' => 'practical_pass_marks'],
        ] as $markField => ['maximum' => $maximumField, 'pass' => $passField]) {
            $maximum = (float) $subject->{$maximumField};
            if ($maximum <= 0) {
                continue;
            }

            if ($examFullMarks !== null && $examFullMarks > 0 && $maximum > $examFullMarks) {
                continue;
            }

            $required = $subject->{$passField};
            $required = $required === null ? $maximum * 0.33 : (float) $required;
            if ((float) ($marks[$markField] ?? 0) + 0.000001 < $required) {
                return true;
            }
        }

        return false;
    }

    public function failsMark(Mark $mark, InstituteSubject $subject, ?float $examFullMarks = null): bool
    {
        return $this->failsMarkComponents($subject, [
            'theory_marks' => $mark->theory_marks,
            'mcq_marks' => $mark->mcq_marks,
            'practical_marks' => $mark->practical_marks,
        ], $examFullMarks);
    }

    public function failsMarkForExam(Mark $mark, InstituteSubject $subject, ?float $examFullMarks = null): bool
    {
        $configuredComponents = collect([
            $subject->theory_marks,
            $subject->mcq_marks,
            $subject->practical_marks,
        ])->filter(fn ($maximum) => (float) $maximum > 0)
            ->filter(fn ($maximum) => $examFullMarks === null || $examFullMarks <= 0 || (float) $maximum <= (float) $examFullMarks)
            ->count();

        return $configuredComponents > 1 && $this->failsMark($mark, $subject, $examFullMarks);
    }

    public function gradeForExam(
        float|int $marks,
        float|int $fullMarks,
        float|int $passMarks,
        Collection $gradeRanges,
        bool $failsComponents = false
    ): array {
        if ($fullMarks <= 0 || $marks + 0.000001 < $passMarks || $failsComponents) {
            return ['grade' => 'F', 'point' => 0.00];
        }

        $percentage = min(100, ($marks / $fullMarks) * 100);
        $grade = $gradeRanges->first(
            fn ($range) => $percentage + 0.000001 >= (float) data_get($range, 'marks_from')
        );

        if (! $grade) {
            $grade = collect(Grade::getDefaultGrades())
                ->first(fn ($range) => $percentage + 0.000001 >= $range['marks_from']);
        }

        if (! $grade) {
            return ['grade' => 'F', 'point' => 0.00];
        }

        $name = data_get($grade, 'name');
        $point = (float) data_get($grade, 'point');

        return [
            'grade' => $name === 'F' ? 'F' : $name,
            'point' => $name === 'F' ? 0.00 : $point,
        ];
    }

    public function gradeRangesForInstitute(int $instituteId): Collection
    {
        $grades = Grade::forInstitute($instituteId)->orderByDesc('marks_from')->get();

        return $grades->isNotEmpty()
            ? $grades
            : collect(Grade::getDefaultGrades())->sortByDesc('marks_from')->values();
    }
}
