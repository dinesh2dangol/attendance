<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class WorkFromHomeCalendar
{
    public static function matchAttendanceUserId(Builder $query): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $query->whereRaw(
                'work_from_home_requests.user_id COLLATE utf8mb4_0900_ai_ci = daily_attendance_step3.user_id COLLATE utf8mb4_0900_ai_ci'
            );

            return;
        }

        $query->whereColumn('work_from_home_requests.user_id', 'daily_attendance_step3.user_id');
    }

    public static function approvedDatesByDate(string $userId, Carbon $start, Carbon $end): Collection
    {
        $requests = DB::table('work_from_home_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date']);

        $dates = collect();

        foreach ($requests as $request) {
            $requestStart = Carbon::parse($request->start_date);
            $requestEnd = Carbon::parse($request->end_date);
            $date = $requestStart->greaterThan($start) ? $requestStart : $start->copy();
            $lastDate = $requestEnd->lessThan($end) ? $requestEnd : $end->copy();
            $date->startOfDay();
            $lastDate->startOfDay();

            while ($date->lessThanOrEqualTo($lastDate)) {
                $dates->put($date->toDateString(), true);
                $date->addDay();
            }
        }

        return $dates;
    }
}
