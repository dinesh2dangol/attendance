<?php

namespace App\Support;

use App\Models\EmployeeGroup;
use App\Models\User;

class SupervisorGroupAccess
{
    public static function memberUserIds(User $supervisor): array
    {
        $employeeId = $supervisor->employee?->id;

        if (! $employeeId) {
            return [];
        }

        return EmployeeGroup::query()
            ->where('supervisor_employee_id', $employeeId)
            ->with('members')
            ->get()
            ->flatMap(fn ($group) => $group->members->pluck('user_id'))
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
