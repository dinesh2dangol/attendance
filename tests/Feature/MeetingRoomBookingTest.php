<?php

namespace Tests\Feature;

use App\Models\MeetingRoom;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MeetingRoomBookingTest extends TestCase
{
    public function test_employees_can_browse_day_week_and_month_room_calendars(): void
    {
        $this->createMeetingRoomSchema();
        $employee = $this->createUser('employee', 'employee');
        $room = MeetingRoom::create(['name' => 'Board Room', 'is_active' => true]);
        $start = Carbon::tomorrow()->setTime(10, 0);
        DB::table('meeting_room_bookings')->insert([
            'meeting_room_id' => $room->id,
            'user_id' => $employee->id,
            'booked_by_name' => 'Employee',
            'title' => 'Planning',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['day', 'week', 'month'] as $view) {
            $this->actingAs($employee)
                ->get(route('meeting-rooms.index', [
                    'date' => $start->toDateString(),
                    'view' => $view,
                ]))
                ->assertOk()
                ->assertSee('Board Room')
                ->assertSee('Planning')
                ->assertSee('Employee')
                ->assertSee('Book a room');
        }
    }

    public function test_employee_booking_rejects_overlaps_and_allows_adjacent_times(): void
    {
        $this->createMeetingRoomSchema();
        $employee = $this->createUser('employee', 'employee');
        $room = MeetingRoom::create(['name' => 'Board Room', 'is_active' => true]);
        $start = Carbon::tomorrow()->setTime(10, 0);
        $end = $start->copy()->addHour();
        $bookingData = [
            'meeting_room_id' => $room->id,
            'title' => 'Planning',
            'starts_at' => $start->format('Y-m-d\TH:i'),
            'ends_at' => $end->format('Y-m-d\TH:i'),
        ];

        $this->actingAs($employee)
            ->post(route('meeting-room-bookings.store'), $bookingData)
            ->assertRedirect(route('meeting-rooms.index', [
                'date' => $start->toDateString(),
                'view' => 'day',
            ]));

        $this->post(route('meeting-room-bookings.store'), [
            ...$bookingData,
            'title' => 'Overlapping meeting',
            'starts_at' => $start->copy()->addMinutes(30)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('starts_at');

        $this->post(route('meeting-room-bookings.store'), [
            ...$bookingData,
            'title' => 'Next meeting',
            'starts_at' => $end->format('Y-m-d\TH:i'),
            'ends_at' => $end->copy()->addHour()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertDatabaseCount('meeting_room_bookings', 2);
    }

    public function test_employees_can_only_cancel_their_own_upcoming_bookings(): void
    {
        $this->createMeetingRoomSchema();
        $employee = $this->createUser('employee', 'employee');
        $otherEmployee = $this->createUser('employee', 'other');
        $room = MeetingRoom::create(['name' => 'Board Room', 'is_active' => true]);
        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $bookingId = DB::table('meeting_room_bookings')->insertGetId([
            'meeting_room_id' => $room->id,
            'user_id' => $employee->id,
            'booked_by_name' => 'Employee',
            'title' => 'Planning',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($otherEmployee)
            ->post(route('meeting-room-bookings.cancel', $bookingId))
            ->assertForbidden();
        $this->actingAs($employee)
            ->post(route('meeting-room-bookings.cancel', $bookingId))
            ->assertRedirect()
            ->assertSessionHas('success', 'Meeting room booking cancelled.');
        $this->assertNotNull(
            DB::table('meeting_room_bookings')->where('id', $bookingId)->value('cancelled_at')
        );
    }

    public function test_superadmin_can_manage_rooms_and_employees_cannot(): void
    {
        $this->createMeetingRoomSchema();
        $employee = $this->createUser('employee', 'employee');
        $superadmin = $this->createUser('superadmin', 'superadmin');

        $this->actingAs($employee)
            ->get(route('meeting-rooms.manage'))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->post(route('meeting-rooms.store'), [
                'name' => 'Board Room',
                'location' => 'Second floor',
                'capacity' => 12,
                'is_active' => '1',
            ])
            ->assertRedirect(route('meeting-rooms.manage'));
        $this->assertDatabaseHas('meeting_rooms', [
            'name' => 'Board Room',
            'location' => 'Second floor',
            'capacity' => 12,
            'is_active' => true,
        ]);
    }

    public function test_employees_cannot_book_inactive_rooms(): void
    {
        $this->createMeetingRoomSchema();
        $employee = $this->createUser('employee', 'employee');
        $room = MeetingRoom::create(['name' => 'Closed Room', 'is_active' => false]);
        $start = Carbon::tomorrow()->setTime(10, 0);

        $this->actingAs($employee)
            ->post(route('meeting-room-bookings.store'), [
                'meeting_room_id' => $room->id,
                'title' => 'Planning',
                'starts_at' => $start->format('Y-m-d\TH:i'),
                'ends_at' => $start->copy()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('meeting_room_id');
        $this->assertDatabaseCount('meeting_room_bookings', 0);
    }

    private function createMeetingRoomSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_name')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->foreignId('role_id');
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('meeting_room_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_room_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('booked_by_name');
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $roleSlug, string $suffix): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        return User::create([
            'name' => ucfirst($suffix),
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'role_id' => $role->id,
        ]);
    }
}
