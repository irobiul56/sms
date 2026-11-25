<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\InstituteClass;
use App\Models\Section;

class InstituteRegistrationSetup
{
    private const CLASS_RANGES = [
        'school' => [1, 10],
        'college' => [11, 12],
        'madrasa' => [1, 12],
    ];

    private const BANGLA_CLASS_NAMES = [
        1 => '১ম শ্রেণি',
        2 => '২য় শ্রেণি',
        3 => '৩য় শ্রেণি',
        4 => '৪র্থ শ্রেণি',
        5 => '৫ম শ্রেণি',
        6 => '৬ষ্ঠ শ্রেণি',
        7 => '৭ম শ্রেণি',
        8 => '৮ম শ্রেণি',
        9 => '৯ম শ্রেণি',
        10 => '১০ম শ্রেণি',
        11 => '১১তম শ্রেণি',
        12 => '১২তম শ্রেণি',
    ];

    public function createDefaults(Institute $institute): void
    {
        $year = now()->year;

        AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => "{$year}-".($year + 1),
            'year' => $year,
            'session' => "{$year}-".($year + 1),
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_current' => true,
        ]);

        $range = self::CLASS_RANGES[$institute->type] ?? null;

        if ($range === null) {
            return;
        }

        for ($number = $range[0]; $number <= $range[1]; $number++) {
            $class = InstituteClass::create([
                'institute_id' => $institute->id,
                'name' => "Class {$number}",
                'bn_name' => self::BANGLA_CLASS_NAMES[$number],
                'numeric_value' => $number,
                'display_order' => $number,
                'has_elective' => $institute->type === 'college' || (
                    in_array($institute->type, ['school', 'madrasa'], true) && $number >= 9
                ),
            ]);

            Section::create([
                'institute_id' => $institute->id,
                'institute_class_id' => $class->id,
                'name' => 'A',
            ]);
        }
    }
}
