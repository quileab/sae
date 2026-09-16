<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class AcademicCycle
{
    public const START_MONTH = 2;

    public const START_DAY = 1;

    public const Q1_END_MONTH = 7;

    public const Q1_END_DAY = 15;

    public const Q2_END_MONTH = 11;

    public const Q2_END_DAY = 30;

    public static function startDate(int $cycle): Carbon
    {
        return Carbon::create($cycle, self::START_MONTH, self::START_DAY)->startOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function firstQuarter(int $cycle): array
    {
        return [
            self::startDate($cycle),
            Carbon::create($cycle, self::Q1_END_MONTH, self::Q1_END_DAY)->startOfDay(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function secondQuarter(int $cycle): array
    {
        return [
            Carbon::create($cycle, self::Q1_END_MONTH, self::Q1_END_DAY + 1)->startOfDay(),
            Carbon::create($cycle, self::Q2_END_MONTH, self::Q2_END_DAY)->startOfDay(),
        ];
    }
}
