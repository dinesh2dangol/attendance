<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HolidayCalendar
{
    public static function byDate(Employee $employee, Carbon $start, Carbon $end): Collection
    {
        return DB::table('holidays')
            ->whereBetween('holiday_date', [
                $start->copy()->startOfMonth()->toDateString(),
                $end->copy()->endOfMonth()->toDateString(),
            ])
            ->where(function ($query) use ($employee) {
                $query->whereNull('department_id');

                if ($employee->department_id !== null) {
                    $query->orWhere('department_id', $employee->department_id);
                }
            })
            ->orderByRaw('department_id IS NULL')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($holiday) => Carbon::parse($holiday->holiday_date)->format('Y-m-d'))
            ->map(fn ($holidays) => $holidays->first());
    }
}
