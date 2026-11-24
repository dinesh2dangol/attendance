<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use App\Models\MeetingRoomBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeetingRoomBookingController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'view' => ['sometimes', 'required', 'in:day,week,month'],
            'room_id' => ['nullable', 'integer', 'exists:meeting_rooms,id'],
        ]);
        $date = Carbon::createFromFormat(
            'Y-m-d',
            $validated['date'] ?? Carbon::today()->toDateString()
        )->startOfDay();
        $calendarView = $validated['view'] ?? 'week';

        [$rangeStart, $rangeEnd] = match ($calendarView) {
            'day' => [$date->copy(), $date->copy()->addDay()],
            'week' => [
                $date->copy()->startOfWeek(Carbon::MONDAY),
                $date->copy()->startOfWeek(Carbon::MONDAY)->addWeek(),
            ],
            'month' => [
                $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY)->addDay(),
            ],
        };
        $rooms = MeetingRoom::query()->where('is_active', true)->orderBy('name')->get();
        $selectedRoomId = $validated['room_id'] ?? null;
        if ($selectedRoomId && ! $rooms->contains('id', (int) $selectedRoomId)) {
            abort(404);
        }

        $bookings = MeetingRoomBooking::query()
            ->with('room')
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $rangeEnd)
            ->where('ends_at', '>', $rangeStart)
            ->when($selectedRoomId, fn ($query) => $query->where('meeting_room_id', $selectedRoomId))
            ->orderBy('starts_at')
            ->get();
        $calendarDates = collect();
        for ($calendarDate = $rangeStart->copy(); $calendarDate->lt($rangeEnd); $calendarDate->addDay()) {
            $calendarDates->push($calendarDate->copy());
        }
        $bookingsByDate = $calendarDates->mapWithKeys(function (Carbon $calendarDay) use ($bookings): array {
            $dayStart = $calendarDay->copy()->startOfDay();
            $dayEnd = $dayStart->copy()->addDay();

            return [
                $calendarDay->toDateString() => $bookings->filter(
                    fn (MeetingRoomBooking $booking) => $booking->starts_at->lt($dayEnd)
                        && $booking->ends_at->gt($dayStart)
                ),
            ];
        });
        $myBookings = MeetingRoomBooking::query()
            ->with('room')
            ->where('user_id', $request->user()->id)
            ->whereNull('cancelled_at')
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->limit(20)
            ->get();
        $defaultStart = $date->copy()->setTime(9, 0);
        if ($defaultStart->lessThanOrEqualTo(now())) {
            $defaultStart = now()->addHour()->startOfHour();
        }
        $defaultEnd = $defaultStart->copy()->addHour();
        $previousDate = match ($calendarView) {
            'day' => $date->copy()->subDay(),
            'week' => $date->copy()->subWeek(),
            'month' => $date->copy()->subMonth(),
        };
        $nextDate = match ($calendarView) {
            'day' => $date->copy()->addDay(),
            'week' => $date->copy()->addWeek(),
            'month' => $date->copy()->addMonth(),
        };

        return view('meeting-rooms.bookings', compact(
            'bookings',
            'bookingsByDate',
            'calendarDates',
            'calendarView',
            'date',
            'myBookings',
            'previousDate',
            'nextDate',
            'rooms',
            'selectedRoomId',
            'defaultStart',
            'defaultEnd'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'meeting_room_id' => ['required', 'integer', 'exists:meeting_rooms,id'],
            'title' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
        ], [
            'starts_at.after_or_equal' => 'Meeting room bookings must start in the future.',
            'ends_at.after' => 'The end time must be after the start time.',
        ]);

        $startsAt = Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], config('app.timezone'));
        $endsAt = Carbon::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], config('app.timezone'));
        $user = $request->user();

        DB::transaction(function () use ($validated, $startsAt, $endsAt, $user): void {
            $room = MeetingRoom::query()
                ->whereKey($validated['meeting_room_id'])
                ->lockForUpdate()
                ->firstOrFail();
            if (! $room->is_active) {
                throw ValidationException::withMessages([
                    'meeting_room_id' => 'This meeting room is not available for booking.',
                ]);
            }

            $hasConflict = MeetingRoomBooking::query()
                ->where('meeting_room_id', $room->id)
                ->whereNull('cancelled_at')
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->exists();
            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'starts_at' => 'This room is already booked during that time. Choose another room or time.',
                ]);
            }

            MeetingRoomBooking::create([
                'meeting_room_id' => $room->id,
                'user_id' => $user->id,
                'booked_by_name' => $user->employee?->employee_name ?: $user->name,
                'title' => $validated['title'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        });

        return redirect()->route('meeting-rooms.index', [
            'date' => $startsAt->toDateString(),
            'view' => 'day',
        ])->with('success', 'Meeting room booked.');
    }

    public function cancel(Request $request, MeetingRoomBooking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        abort_if($booking->cancelled_at || $booking->starts_at->isPast(), 404);

        $booking->update(['cancelled_at' => now()]);

        return back()->with('success', 'Meeting room booking cancelled.');
    }
}
