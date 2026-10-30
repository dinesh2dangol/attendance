<?php

namespace App\Support;

use App\Models\EmployeeGroup;
use App\Models\User;

class SupervisorGroupAccess
{
    public static function members(User $supervisor)
    {
        $employeeId = $supervisor->employee?->id;

        if (! $employeeId) {
            return collect();
        }

        return EmployeeGroup::query()
            ->where('supervisor_employee_id', $employeeId)
            ->with('members')
            ->get()
            ->flatMap(fn ($group) => $group->members)
            ->unique('id')
            ->sortBy('employee_name')
            ->values();
    }

    public static function memberUserIds(User $supervisor): array
    {
        return self::members($supervisor)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($userId) => (string) $userId)
            ->unique()
            ->values()
            ->all();
    }

    public static function canReview(User $supervisor, ?string $userId): bool
    {
        return $userId !== null
            && in_array((string) $userId, self::memberUserIds($supervisor), true);
    }
}
