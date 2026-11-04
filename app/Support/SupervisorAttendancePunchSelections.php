<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SupervisorAttendancePunchSelections
{
    private const PUNCH_FIELDS = [
        'check_in_punch' => 'first_punch',
        'lunch_out_punch' => 'lunch_out',
        'lunch_in_punch' => 'lunch_in',
        'check_out_punch' => 'last_punch',
    ];

    public static function apply(Collection $attendanceRecords, Collection $selections): void
    {
        $selectionsByAttendance = $selections->keyBy(
            fn ($selection) => self::key($selection->user_id, $selection->attendance_date)
        );

        foreach ($attendanceRecords as $record) {
            $selection = $selectionsByAttendance->get(self::key($record->user_id, $record->attendance_date));

            if (! $selection) {
                continue;
            }

            foreach (self::PUNCH_FIELDS as $selectionField => $attendanceField) {
                $punchNumber = $selection->{$selectionField};
                $record->{$attendanceField} = $punchNumber
                    ? data_get($record, 'punch_'.$punchNumber)
                    : null;
            }

            $record->supervisor_punch_selection_applied = true;
        }
    }

    public static function suggestedPunchNumbers(object $attendance): array
    {
        return [
            'check_in_punch' => self::punchNumberFor($attendance, data_get($attendance, 'first_punch')),
            'lunch_out_punch' => self::punchNumberFor(
                $attendance,
                data_get($attendance, 'lunch_out') ?? data_get($attendance, 'punch_2')
            ),
            'lunch_in_punch' => self::punchNumberFor(
                $attendance,
                data_get($attendance, 'punch_3') ?? data_get($attendance, 'lunch_in')
            ),
            'check_out_punch' => self::punchNumberFor($attendance, data_get($attendance, 'last_punch')),
        ];
    }

    private static function punchNumberFor(object $attendance, mixed $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        foreach (range(1, 5) as $punchNumber) {
            if ((string) data_get($attendance, 'punch_'.$punchNumber) === (string) $time) {
                return $punchNumber;
            }
        }

        return null;
    }

    private static function key(string $userId, mixed $attendanceDate): string
    {
        return $userId.'|'.Carbon::parse($attendanceDate)->toDateString();
    }
}
