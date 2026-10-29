<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManualAttendanceCategories
{
    public const REASONS = ['WFH', 'Forgot', 'Others'];

    public static function byDate(string $userId, Carbon $start, Carbon $end): Collection
    {
        return DB::table('ManualAttendance')
            ->where('user_id', $userId)
            ->whereBetween('timestamp', [
                $start->copy()->startOfDay()->toDateTimeString(),
                $end->copy()->endOfDay()->toDateTimeString(),
            ])
            ->select('timestamp', 'category', 'remarks')
            ->orderBy('timestamp')
            ->get()
            ->groupBy(fn ($record) => Carbon::parse($record->timestamp)->format('Y-m-d'))
            ->map(function ($records) {
                foreach ($records as $record) {
                    if (in_array($record->category, self::REASONS, true)) {
                        return $record->category;
                    }

                    if (preg_match('/\AReason: (WFH|Forgot|Others)(?:\r?\n|\z)/', (string) $record->remarks, $matches)) {
                        return $matches[1];
                    }
                }

                return null;
            });
    }
}
