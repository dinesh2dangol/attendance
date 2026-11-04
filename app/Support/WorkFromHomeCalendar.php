<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        return self::datesByStatus($userId, $start, $end, 'approved');
    }

    public static function pendingDatesByDate(string $userId, Carbon $start, Carbon $end): Collection
    {
        return self::datesByStatus($userId, $start, $end, 'pending');
    }

    public static function statusesByUserForDate(Collection $userIds, Carbon $date): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return DB::table('work_from_home_requests')
            ->whereIn('user_id', $userIds)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->whereIn('status', ['approved', 'pending'])
            ->get(['user_id', 'status'])
            ->groupBy('user_id')
            ->map(fn ($requests) => $requests->contains(fn ($request) => $request->status === 'approved')
                ? 'Approved'
                : 'Pending');
    }

    public static function approvedAttendanceKeys(Collection $attendanceRecords): Collection
    {
        if ($attendanceRecords->isEmpty()) {
            return collect();
        }

        $userIds = $attendanceRecords->pluck('user_id')->unique()->values();
        $dates = $attendanceRecords->map(
            fn ($attendance) => Carbon::parse($attendance->attendance_date)->toDateString()
        );
        $requestsByUser = DB::table('work_from_home_requests')
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dates->max())
            ->whereDate('end_date', '>=', $dates->min())
            ->get(['user_id', 'start_date', 'end_date'])
            ->groupBy('user_id');

        return $attendanceRecords
            ->filter(function ($attendance) use ($requestsByUser) {
                if (! in_array($attendance->attendance_status, ['Present', 'Remote'], true)) {
                    return false;
                }

                $date = Carbon::parse($attendance->attendance_date)->toDateString();

                return $requestsByUser->get($attendance->user_id, collect())
                    ->contains(fn ($request) => $request->start_date <= $date && $request->end_date >= $date);
            })
            ->mapWithKeys(fn ($attendance) => [
                $attendance->user_id.'|'.Carbon::parse($attendance->attendance_date)->toDateString() => true,
            ]);
    }

    private static function datesByStatus(string $userId, Carbon $start, Carbon $end, string $status): Collection
    {
        $requests = DB::table('work_from_home_requests')
            ->where('user_id', $userId)
            ->where('status', $status)
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
