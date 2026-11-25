<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubjectGroupService
{
    public function forClass(int $classId, Collection $subjectIds): Collection
    {
        $assignments = DB::table('institute_class_institute_subjects')
            ->where('institute_class_id', $classId)
            ->whereIn('institute_subject_id', $subjectIds)
            ->get(['institute_subject_id', 'is_group_based', 'group_name'])
            ->keyBy('institute_subject_id');

        return $subjectIds
            ->unique()
            ->groupBy(function ($subjectId) use ($assignments) {
                $assignment = $assignments->get($subjectId);
                $groupName = trim((string) ($assignment->group_name ?? ''));

                return (bool) ($assignment->is_group_based ?? false) && $groupName !== ''
                    ? "group:{$groupName}"
                    : "subject:{$subjectId}";
            })
            ->map(function (Collection $ids, string $groupKey) {
                $groupName = str_starts_with($groupKey, 'group:')
                    ? substr($groupKey, strlen('group:'))
                    : null;
                $ids = $ids->map(fn ($id) => (int) $id)->values();

                return [
                    'key' => 'subjects-'.implode('-', $ids->all()),
                    'name' => $groupName,
                    'subject_ids' => $ids,
                ];
            })
            ->values();
    }
}
