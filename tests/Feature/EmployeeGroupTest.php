<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\SupervisorGroupAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeGroupTest extends TestCase
{
    public function test_superadmin_can_assign_a_supervisor_and_share_members_across_groups(): void
    {
        $this->createGroupSchema();
        $superadmin = $this->createUser('superadmin', 'root');
        $supervisorOne = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor One');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');

        $this->actingAs($superadmin)
            ->post(route('employee-groups.store'), [
                'name' => 'Operations',
                'supervisor_employee_id' => $supervisorOne->id,
                'members' => [$member->id],
            ])
            ->assertRedirect(route('employee-groups.index'));

        $firstGroupId = DB::table('employee_groups')->where('name', 'Operations')->value('id');

        $this->post(route('employee-groups.store'), [
            'name' => 'Support',
            'supervisor_employee_id' => $supervisorOne->id,
            'members' => [$member->id],
        ])->assertRedirect(route('employee-groups.index'));

        $this->assertSame(
            2,
            DB::table('employee_group_members')->where('employee_id', $member->id)->count()
        );
        $this->assertSame(
            2,
            DB::table('employee_groups')->where('supervisor_employee_id', $supervisorOne->id)->count()
        );
        $this->assertSame(
            Role::where('slug', 'supervisor')->value('id'),
            $supervisorOne->account->fresh()->role_id
        );
        $this->assertSame(
            ['EMP-2'],
            SupervisorGroupAccess::memberUserIds($supervisorOne->account->fresh())
        );
        $this->assertNotNull($firstGroupId);
    }

    public function test_supervisor_can_view_all_their_groups(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Member');
        foreach (['Operations', 'Support'] as $name) {
            $groupId = DB::table('employee_groups')->insertGetId([
                'name' => $name,
                'supervisor_employee_id' => $supervisor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('employee_group_members')->insert([
                'employee_group_id' => $groupId,
                'employee_id' => $member->id,
            ]);
        }

        $this->actingAs($supervisor->account)
            ->get(route('employee.groups'))
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Support')
            ->assertSee('Team Attendance')
            ->assertSee('Leave Approvals')
            ->assertSee('WFH Requests');
    }

    public function test_superadmin_can_view_attendance_for_a_specific_group(): void
    {
        $this->createGroupSchema();
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('first_punch')->nullable();
            $table->string('last_punch')->nullable();
            $table->string('lunch_out')->nullable();
            $table->string('lunch_in')->nullable();
            foreach (range(1, 5) as $punchNumber) {
                $table->string('punch_'.$punchNumber)->nullable();
            }
        });
        $superadmin = $this->createUser('superadmin', 'root');
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other Employee');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $today = now()->toDateString();
        DB::table('work_from_home_requests')->insert([
            'user_id' => $member->user_id,
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Working remotely',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('daily_attendance_step3')->insert([
            [
                'user_id' => $supervisor->user_id,
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:00',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:00',
                'punch_2' => '12:00',
                'punch_3' => '12:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
            [
                'user_id' => $member->user_id,
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:30',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:30',
                'punch_2' => '12:00',
                'punch_3' => '12:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
            [
                'user_id' => $other->user_id,
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '09:00',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '09:00',
                'punch_2' => '12:00',
                'punch_3' => '12:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
        ]);

        $this->actingAs($superadmin)
            ->get(route('employee-groups.index'))
            ->assertOk()
            ->assertSee(route('employee-groups.attendance', $groupId), false);

        $this->get(route('employee-groups.attendance', ['employeeGroup' => $groupId, 'date' => $today]))
            ->assertOk()
            ->assertSee('Operations Attendance')
            ->assertSee('Supervisor (Supervisor)')
            ->assertSee(route('employee.attendance', $supervisor), false)
            ->assertSee('08:00')
            ->assertSee('Group Member')
            ->assertSee('12:00')
            ->assertSee('Review Punches')
            ->assertSee('Export to Excel')
            ->assertSee('Export Selected Employee')
            ->assertSee('name="group_id"', false)
            ->assertDontSee('Other Employee')
            ->assertSee('employee-user-id');

        $this->get(route('employee.attendance.team.punch-selection.edit', [
            'employee' => $member,
            'date' => $today,
            'group_id' => $groupId,
        ]))
            ->assertOk()
            ->assertSee('Review Attendance Punches')
            ->assertSee('group_id', false);
        $this->post(route('employee.attendance.team.punch-selection', $member), [
            'date' => $today,
            'group_id' => $groupId,
            'check_in_punch' => '1',
            'lunch_out_punch' => '2',
            'lunch_in_punch' => '3',
            'check_out_punch' => '4',
        ])->assertRedirect(route('employee-groups.attendance', [
            'employeeGroup' => $groupId,
            'date' => $today,
        ]));
        $this->assertDatabaseHas('supervisor_attendance_punch_selections', [
            'user_id' => $member->user_id,
            'attendance_date' => $today,
            'approved_by' => $superadmin->id,
        ]);
        $this->post(route('employee.attendance.team.punch-selection', $other), [
            'date' => $today,
            'group_id' => $groupId,
            'check_in_punch' => '1',
        ])->assertForbidden();

        $this->get(route('employee.attendance.team.export', [
            'group_id' => $groupId,
            'from' => $today,
            'to' => $today,
        ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=group-attendance-'.$groupId.'-'.$today.'-to-'.$today.'.csv');
        $groupExport = $this->get(route('employee.attendance.team.export', [
            'group_id' => $groupId,
            'from' => $today,
            'to' => $today,
        ]))->streamedContent();
        $this->assertStringContainsString('Group Member', $groupExport);
        $this->assertStringContainsString('Supervisor', $groupExport);
        $this->assertStringNotContainsString('Other Employee', $groupExport);

        $selectedExport = $this->get(route('employee.attendance.team.export', [
            'group_id' => $groupId,
            'employee_user_id' => $member->user_id,
            'from' => $today,
            'to' => $today,
        ]))->streamedContent();
        $this->assertStringContainsString('Group Member', $selectedExport);
        $this->assertStringContainsString('Supervisor', $selectedExport);
        $this->assertStringNotContainsString('Other Employee', $selectedExport);

        $this->get(route('employee.attendance.team.export', [
            'group_id' => $groupId,
            'employee_user_id' => $other->user_id,
            'from' => $today,
            'to' => $today,
        ]))->assertForbidden();

        $this->actingAs($supervisor->account)
            ->get(route('employee-groups.attendance', ['employeeGroup' => $groupId, 'date' => $today]))
            ->assertForbidden();
    }

    public function test_changing_a_groups_supervisor_updates_both_account_roles(): void
    {
        $this->createGroupSchema();
        $superadmin = $this->createUser('superadmin', 'root');
        $previousSupervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor One');
        $newSupervisor = $this->createEmployeeWithAccount(2, 'EMP-2', 'Supervisor Two');

        $this->actingAs($superadmin)->post(route('employee-groups.store'), [
            'name' => 'Operations',
            'supervisor_employee_id' => $previousSupervisor->id,
            'members' => [],
        ])->assertRedirect(route('employee-groups.index'));

        $groupId = DB::table('employee_groups')->value('id');
        $this->get(route('employee-groups.edit', $groupId))->assertOk();

        $this->put(route('employee-groups.update', $groupId), [
            'name' => 'Operations',
            'supervisor_employee_id' => $newSupervisor->id,
            'members' => [],
        ])->assertRedirect(route('employee-groups.index'));

        $this->assertSame(
            'employee',
            $previousSupervisor->account->fresh()->role->slug
        );
        $this->assertSame(
            'supervisor',
            $newSupervisor->account->fresh()->role->slug
        );
    }

    public function test_employees_without_login_accounts_are_available_as_group_members(): void
    {
        $this->createGroupSchema();
        $superadmin = $this->createUser('superadmin', 'root');
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor');
        $member = Employee::create([
            'id' => 2,
            'user_id' => 'EMP-2',
            'employee_name' => 'Employee Without Login',
        ]);

        $this->actingAs($superadmin)
            ->get(route('employee-groups.create'))
            ->assertOk()
            ->assertSee('Employee Without Login');

        $this->post(route('employee-groups.store'), [
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'members' => [$member->id],
        ])->assertRedirect(route('employee-groups.index'));

        $this->assertDatabaseHas('employee_group_members', [
            'employee_group_id' => DB::table('employee_groups')->where('name', 'Operations')->value('id'),
            'employee_id' => $member->id,
        ]);
    }

    public function test_supervisor_can_approve_only_leave_requests_for_group_members(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $memberLeaveId = DB::table('leaves')->insertGetId([
            'user_id' => $member->user_id,
            'leave_date' => '2026-11-02',
            'leave_type' => 'casual',
            'approval_status' => 0,
        ]);
        $otherLeaveId = DB::table('leaves')->insertGetId([
            'user_id' => $other->user_id,
            'leave_date' => '2026-11-03',
            'leave_type' => 'sick',
            'approval_status' => 0,
        ]);

        $this->actingAs($supervisor->account)
            ->post(route('leaves.approve', $memberLeaveId))
            ->assertRedirect();
        $this->post(route('leaves.approve', $otherLeaveId))->assertForbidden();
        $this->get(route('employee.attendance', $other))->assertForbidden();

        $this->assertDatabaseHas('leaves', ['leave_id' => $memberLeaveId, 'approval_status' => 1]);
        $this->assertDatabaseHas('leaves', ['leave_id' => $otherLeaveId, 'approval_status' => 0]);
    }

    public function test_supervisor_can_open_a_team_attendance_list_scoped_to_group_members(): void
    {
        $this->createGroupSchema();
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('first_punch')->nullable();
            $table->string('last_punch')->nullable();
            $table->string('lunch_out')->nullable();
            $table->string('lunch_in')->nullable();
            $table->string('punch_1')->nullable();
            $table->string('punch_2')->nullable();
            $table->string('punch_3')->nullable();
            $table->string('punch_4')->nullable();
            $table->string('punch_5')->nullable();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('holiday_name');
            $table->unsignedBigInteger('department_id')->nullable();
        });
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other Employee');
        $wfhMember = $this->createEmployeeWithAccount(4, 'EMP-4', 'WFH Member');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $wfhMember->id,
        ]);
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        DB::table('holidays')->insert([
            'holiday_date' => $yesterday,
            'holiday_name' => 'Founders Day',
            'department_id' => null,
        ]);
        DB::table('work_from_home_requests')->insert([
            ['user_id' => 'EMP-2', 'start_date' => $yesterday, 'end_date' => $yesterday, 'reason' => 'Test request', 'status' => 'pending'],
            ['user_id' => 'EMP-2', 'start_date' => $today, 'end_date' => $today, 'reason' => 'Test request', 'status' => 'approved'],
            ['user_id' => 'EMP-4', 'start_date' => $today, 'end_date' => $today, 'reason' => 'Test request', 'status' => 'approved'],
        ]);
        DB::table('daily_attendance_step3')->insert([
            [
                'user_id' => 'EMP-1',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:00',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:00',
                'punch_2' => '12:00',
                'punch_3' => '12:30',
                'punch_4' => '17:00',
                'punch_5' => null,
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-2',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:30',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:30',
                'punch_2' => '12:30',
                'punch_3' => '13:00',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-2',
                'attendance_date' => now()->subDay()->toDateString(),
                'attendance_status' => 'Absent',
                'first_punch' => null,
                'last_punch' => null,
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => null,
                'punch_2' => null,
                'punch_3' => null,
                'punch_4' => null,
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-3',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '09:00',
                'last_punch' => '17:00',
                'lunch_out' => '13:00',
                'lunch_in' => '13:30',
                'punch_1' => '09:00',
                'punch_2' => '13:00',
                'punch_3' => '13:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
        ]);

        $this->actingAs($supervisor->account)
            ->get(route('employee.attendance.team'))
            ->assertOk()
            ->assertSee('Group Member')
            ->assertSee('name="date"', false)
            ->assertSee('value="'.$today.'"', false)
            ->assertSee('Attendance ('.$today.')')
            ->assertSee($today)
            ->assertSee('Present')
            ->assertSee('<strong>WFH:</strong> Approved', false)
            ->assertSee('WFH Member')
            ->assertSee(route('manual-attendance.create', [
                'user_id' => $wfhMember->user_id,
                'timestamp' => $today.'T09:00',
                'status' => '1',
                'reason' => 'WFH',
            ]), false)
            ->assertSee('Mark Present')
            ->assertSee('Remote')
            ->assertDontSee('<th>WFH</th>', false)
            ->assertSee('08:30')
            ->assertSee('Supervisor (You)')
            ->assertSee('08:00')
            ->assertSee('Lunch Out')
            ->assertSee('12:30')
            ->assertSee('Lunch In')
            ->assertSee('13:00')
            ->assertSee('Lunch Time')
            ->assertSee('30 minutes')
            ->assertSee('Check Out')
            ->assertSee('Review Punches')
            ->assertSee('View Attendance Records')
            ->assertSee('id="employee-user-id"', false)
            ->assertSee($supervisor->user_id.'">'.$supervisor->employee_name.' (You)', false)
            ->assertDontSee('name="lunch_out_punch"', false)
            ->assertDontSee('name="lunch_in_punch"', false)
            ->assertDontSee('Absent')
            ->assertDontSee('09:00')
            ->assertSee(route('employee.attendance', $member), false)
            ->assertDontSee('Other Employee');

        $content = $this->get(route('employee.attendance.team'))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($content, '<td>Group Member</td>'),
            strpos($content, '<td>Supervisor (You)</td>')
        );

        $this->post(route('employee.attendance.team.punch-selection', $supervisor), [
            'date' => $today,
            'check_in_punch' => '1',
            'lunch_out_punch' => '2',
            'lunch_in_punch' => '3',
            'check_out_punch' => '4',
        ])->assertRedirect(route('employee.attendance.team', ['date' => $today]));
        $this->assertDatabaseHas('supervisor_attendance_punch_selections', [
            'user_id' => $supervisor->user_id,
            'attendance_date' => $today,
            'check_in_punch' => 1,
            'lunch_out_punch' => 2,
            'lunch_in_punch' => 3,
            'check_out_punch' => 4,
            'approved_by' => $supervisor->account->id,
        ]);

        $selectedDate = now()->subDay()->toDateString();
        $recordsResponse = $this->get(route('employee.attendance.team.records', [
            'employee_user_id' => $supervisor->user_id,
            'from' => $selectedDate,
            'to' => $today,
        ]))
            ->assertOk()
            ->assertSee('Team Attendance Records')
            ->assertSee('Supervisor: '.$selectedDate.' to '.$today)
            ->assertSee('Check In')
            ->assertSee('12:00')
            ->assertSee('12:30')
            ->assertSee('30');
        $this->assertStringNotContainsString('Group Member</td>', $recordsResponse->getContent());
        $memberRecordsResponse = $this->get(route('employee.attendance.team.records', [
            'employee_user_id' => $member->user_id,
            'from' => $selectedDate,
            'to' => $today,
        ]))
            ->assertOk()
            ->assertSee('Group Member: '.$selectedDate.' to '.$today)
            ->assertSee('Absent')
            ->assertSee('08:30')
            ->assertSee('12:30')
            ->assertSee('13:00')
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->assertSee('Founders Day')
            ->assertSee('Holiday');
        $this->assertStringNotContainsString('<td>Supervisor (You)</td>', $memberRecordsResponse->getContent());
        $this->get(route('employee.attendance.team.records', [
            'employee_user_id' => 'EMP-3',
            'from' => $selectedDate,
            'to' => $today,
        ]))->assertForbidden();

        $this->get(route('employee.attendance.team', ['date' => $selectedDate]))
            ->assertOk()
            ->assertSee('value="'.$selectedDate.'"', false)
            ->assertSee('Attendance ('.$selectedDate.')')
            ->assertSee('Absent')
            ->assertDontSee('08:30');
    }

    public function test_supervisor_can_approve_custom_punch_assignments_for_group_members(): void
    {
        $this->createGroupSchema();
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('first_punch')->nullable();
            $table->string('last_punch')->nullable();
            $table->string('lunch_out')->nullable();
            $table->string('lunch_in')->nullable();
            foreach (range(1, 5) as $punchNumber) {
                $table->string('punch_'.$punchNumber)->nullable();
            }
        });
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other Employee');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $today = now()->toDateString();
        DB::table('daily_attendance_step3')->insert([
            'user_id' => 'EMP-2',
            'attendance_date' => $today,
            'attendance_status' => 'Present',
            'first_punch' => '08:30',
            'last_punch' => '17:30',
            'lunch_out' => '08:35',
            'lunch_in' => '12:30',
            'punch_1' => '08:30',
            'punch_2' => '08:35',
            'punch_3' => '12:30',
            'punch_4' => '13:00',
            'punch_5' => '17:30',
        ]);

        $this->actingAs($supervisor->account);
        $this->get(route('employee.attendance.team.punch-selection.edit', [
            'employee' => $member,
            'date' => $today,
        ]))
            ->assertOk()
            ->assertSee('Review Attendance Punches')
            ->assertSee('name="lunch_out_punch"', false)
            ->assertSee('name="lunch_in_punch"', false)
            ->assertSee('Punch 2 — 08:35')
            ->assertSee('selected', false);
        $this->post(route('employee.attendance.team.punch-selection', $member), [
            'date' => $today,
            'check_in_punch' => '1',
            'lunch_out_punch' => '3',
            'lunch_in_punch' => '4',
            'check_out_punch' => '5',
            'remarks' => 'Punch 2 was an accidental duplicate check-in.',
        ])
            ->assertRedirect(route('employee.attendance.team', ['date' => $today]));

        $this->assertDatabaseHas('supervisor_attendance_punch_selections', [
            'user_id' => 'EMP-2',
            'attendance_date' => $today,
            'check_in_punch' => 1,
            'lunch_out_punch' => 3,
            'lunch_in_punch' => 4,
            'check_out_punch' => 5,
            'remarks' => 'Punch 2 was an accidental duplicate check-in.',
            'approved_by' => $supervisor->account->id,
        ]);

        $response = $this->get(route('employee.attendance.team', ['date' => $today]))
            ->assertOk()
            ->assertSee('Approved by')
            ->assertSee('Edit Approval')
            ->assertSee('12:30')
            ->assertSee('13:00')
            ->assertSee('30 minutes')
            ->assertSee('17:30')
            ->assertSee('Punch 2 was an accidental duplicate check-in.')
            ->assertDontSee('name="lunch_out_punch"', false)
            ->assertDontSee('name="lunch_in_punch"', false);

        $this->post(route('employee.attendance.team.punch-selection', $other), [
            'date' => $today,
            'check_in_punch' => '1',
            'lunch_out_punch' => '3',
            'lunch_in_punch' => '4',
            'check_out_punch' => '5',
        ])->assertForbidden();
    }

    public function test_supervisor_can_export_scoped_team_attendance_with_approved_punches(): void
    {
        $this->createGroupSchema();
        Schema::create('daily_attendance_step3', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('attendance_date');
            $table->string('attendance_status');
            $table->string('first_punch')->nullable();
            $table->string('last_punch')->nullable();
            $table->string('lunch_out')->nullable();
            $table->string('lunch_in')->nullable();
            foreach (range(1, 5) as $punchNumber) {
                $table->string('punch_'.$punchNumber)->nullable();
            }
        });
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');
        $secondMember = $this->createEmployeeWithAccount(4, 'EMP-4', 'Second Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other Employee');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            [
                'employee_group_id' => $groupId,
                'employee_id' => $member->id,
            ],
            [
                'employee_group_id' => $groupId,
                'employee_id' => $secondMember->id,
            ],
        ]);
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        DB::table('daily_attendance_step3')->insert([
            [
                'user_id' => 'EMP-1',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:00',
                'last_punch' => '17:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:00',
                'punch_2' => '12:00',
                'punch_3' => '12:30',
                'punch_4' => '17:00',
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-2',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '08:30',
                'last_punch' => '17:30',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '08:30',
                'punch_2' => '08:35',
                'punch_3' => '12:30',
                'punch_4' => '13:00',
                'punch_5' => '17:30',
            ],
            [
                'user_id' => 'EMP-4',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => '09:00',
                'last_punch' => '18:00',
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => '09:00',
                'punch_2' => '12:00',
                'punch_3' => '12:45',
                'punch_4' => '18:00',
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-2',
                'attendance_date' => $yesterday,
                'attendance_status' => 'Absent',
                'first_punch' => null,
                'last_punch' => null,
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => null,
                'punch_2' => null,
                'punch_3' => null,
                'punch_4' => null,
                'punch_5' => null,
            ],
            [
                'user_id' => 'EMP-3',
                'attendance_date' => $today,
                'attendance_status' => 'Present',
                'first_punch' => null,
                'last_punch' => null,
                'lunch_out' => null,
                'lunch_in' => null,
                'punch_1' => null,
                'punch_2' => null,
                'punch_3' => null,
                'punch_4' => null,
                'punch_5' => null,
            ],
        ]);
        DB::table('supervisor_attendance_punch_selections')->insert([
            'user_id' => 'EMP-2',
            'attendance_date' => $today,
            'check_in_punch' => 1,
            'lunch_out_punch' => 3,
            'lunch_in_punch' => 4,
            'check_out_punch' => 5,
            'remarks' => 'Duplicate check-in excluded.',
            'approved_by' => $supervisor->account->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('work_from_home_requests')->insert([
            'user_id' => $member->user_id,
            'start_date' => $today,
            'end_date' => $today,
            'reason' => 'Working remotely',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($supervisor->account)
            ->get(route('employee.attendance.team.export', [
                'from' => $today,
                'to' => $today,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=team-attendance-'.$today.'-to-'.$today.'.csv');

        $this->assertStringContainsString('Lunch Time (minutes)', $response->streamedContent());
        $this->assertStringContainsString('EMP-1,Supervisor,'.$today.',Present,08:00,12:00,12:30,30,17:00', $response->streamedContent());
        $this->assertStringContainsString('EMP-2,"Group Member",'.$today.',Present,08:30,12:30,13:00,30,17:30', $response->streamedContent());
        $this->assertStringContainsString('Duplicate check-in excluded.', $response->streamedContent());
        $this->assertStringContainsString('Remote; Duplicate check-in excluded.', $response->streamedContent());
        $this->assertStringNotContainsString('Other Employee', $response->streamedContent());
        $this->assertStringNotContainsString('Absent', $response->streamedContent());

        $memberResponse = $this->get(route('employee.attendance.team.export', [
            'employee_user_id' => $member->user_id,
            'from' => $today,
            'to' => $today,
        ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=employee-attendance-'.$member->id.'-'.$today.'-to-'.$today.'.csv');
        $this->assertStringContainsString('Group Member', $memberResponse->streamedContent());
        $this->assertStringNotContainsString('Second Member', $memberResponse->streamedContent());
        $this->assertStringNotContainsString('EMP-1,Supervisor,'.$today.',', $memberResponse->streamedContent());
        $this->assertStringContainsString('Remote; Duplicate check-in excluded.', $memberResponse->streamedContent());
        $this->assertStringNotContainsString('Other Employee', $memberResponse->streamedContent());

        $supervisorResponse = $this->get(route('employee.attendance.team.export', [
            'employee_user_id' => $supervisor->user_id,
            'from' => $today,
            'to' => $today,
        ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=employee-attendance-'.$supervisor->id.'-'.$today.'-to-'.$today.'.csv');
        $this->assertStringContainsString('EMP-1,Supervisor,'.$today.',Present,08:00,12:00,12:30,30,17:00', $supervisorResponse->streamedContent());
        $this->assertStringNotContainsString('Group Member', $supervisorResponse->streamedContent());

        $this->get(route('employee.attendance.team.export', [
            'employee_user_id' => $other->user_id,
            'from' => $today,
            'to' => $today,
        ]))->assertForbidden();
    }

    public function test_supervisor_can_approve_only_wfh_requests_for_group_members(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $memberRequestId = DB::table('work_from_home_requests')->insertGetId([
            'user_id' => $member->user_id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-02',
            'reason' => 'Home repair',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherRequestId = DB::table('work_from_home_requests')->insertGetId([
            'user_id' => $other->user_id,
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-03',
            'reason' => 'Appointment',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($supervisor->account)
            ->post(route('wfh-requests.approve', $memberRequestId))
            ->assertRedirect(route('wfh-requests.index'));
        $this->post(route('wfh-requests.approve', $otherRequestId))->assertForbidden();

        $this->assertDatabaseHas('work_from_home_requests', [
            'id' => $memberRequestId,
            'status' => 'approved',
            'reviewed_by' => $supervisor->account->id,
        ]);
        $this->assertDatabaseHas('work_from_home_requests', [
            'id' => $otherRequestId,
            'status' => 'pending',
        ]);
    }

    public function test_supervisor_retains_employee_leave_and_wfh_self_service(): void
    {
        $this->createGroupSchema();
        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');

        $this->actingAs($supervisor->account)
            ->get(route('employee.leaves.create'))
            ->assertOk();
        $this->get(route('employee.wfh.create'))->assertOk();
    }

    public function test_supervisor_can_mark_present_only_for_group_members_with_approved_wfh(): void
    {
        $this->createGroupSchema();
        Schema::create('ManualAttendance', function (Blueprint $table) {
            $table->string('user_id');
            $table->dateTime('timestamp');
            $table->integer('status');
            $table->string('punch_type');
            $table->string('category')->nullable();
            $table->text('remarks')->nullable();
        });

        $supervisor = $this->createEmployeeWithAccount(1, 'EMP-1', 'Supervisor', 'supervisor');
        $member = $this->createEmployeeWithAccount(2, 'EMP-2', 'Group Member');
        $other = $this->createEmployeeWithAccount(3, 'EMP-3', 'Other Employee');
        $groupId = DB::table('employee_groups')->insertGetId([
            'name' => 'Operations',
            'supervisor_employee_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_group_members')->insert([
            'employee_group_id' => $groupId,
            'employee_id' => $member->id,
        ]);
        $date = '2026-11-12';
        DB::table('work_from_home_requests')->insert([
            [
                'user_id' => $member->user_id,
                'start_date' => $date,
                'end_date' => $date,
                'reason' => 'Working remotely',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $other->user_id,
                'start_date' => $date,
                'end_date' => $date,
                'reason' => 'Working remotely',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($supervisor->account)
            ->get(route('manual-attendance.create', [
                'user_id' => $member->user_id,
                'timestamp' => $date.'T09:00',
                'status' => '1',
                'reason' => 'WFH',
            ]))
            ->assertOk()
            ->assertSee('Group Member')
            ->assertSee('value="WFH" selected', false);

        $this->post(route('manual-attendance.store'), [
            'user_id' => $member->user_id,
            'attendance_date' => $date,
            'status' => '1',
            'reason' => 'WFH',
            'punch_IN' => '09:00',
            'punch_OUT' => '17:00',
        ])->assertRedirect(route('employee.attendance', [
            'employee' => $member,
            'month' => 11,
            'year' => 2026,
        ]));

        $this->assertDatabaseHas('ManualAttendance', [
            'user_id' => $member->user_id,
            'timestamp' => $date.' 09:00:00',
            'status' => 1,
            'punch_type' => 'IN',
            'category' => 'WFH',
        ]);
        $this->assertDatabaseCount('ManualAttendance', 2);

        $this->post(route('manual-attendance.store'), [
            'user_id' => $other->user_id,
            'attendance_date' => $date,
            'status' => '1',
            'reason' => 'WFH',
            'punch_IN' => '09:00',
            'punch_OUT' => '17:00',
        ])->assertForbidden();

        $this->post(route('manual-attendance.store'), [
            'user_id' => $member->user_id,
            'attendance_date' => $date,
            'status' => '1',
            'reason' => 'Forgot',
            'punch_IN' => '09:00',
            'punch_OUT' => '17:00',
        ])->assertForbidden();
    }

    private function createGroupSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50)->nullable();
            $table->string('employee_name');
            $table->timestamps();
        });
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('supervisor_employee_id');
            $table->timestamps();
        });
        Schema::create('employee_group_members', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_group_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['employee_group_id', 'employee_id']);
        });
        Schema::create('leaves', function (Blueprint $table) {
            $table->id('leave_id');
            $table->string('user_id');
            $table->date('leave_date');
            $table->string('leave_type');
            $table->string('leave_description')->nullable();
            $table->integer('approval_status');
        });
        Schema::create('work_from_home_requests', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('supervisor_attendance_punch_selections', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->date('attendance_date');
            $table->unsignedTinyInteger('check_in_punch')->nullable();
            $table->unsignedTinyInteger('lunch_out_punch')->nullable();
            $table->unsignedTinyInteger('lunch_in_punch')->nullable();
            $table->unsignedTinyInteger('check_out_punch')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('approved_by');
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
        });

        DB::table('roles')->insert([
            ['name' => 'Administrator', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Employee', 'slug' => 'employee', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Superadmin', 'slug' => 'superadmin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Supervisor', 'slug' => 'supervisor', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createEmployeeWithAccount(
        int $id,
        string $userId,
        string $name,
        string $roleSlug = 'employee'
    ): Employee {
        $employee = Employee::create([
            'id' => $id,
            'user_id' => $userId,
            'employee_name' => $name,
        ]);
        $this->createUser($roleSlug, strtolower(str_replace(' ', '.', $name)), $id);

        return $employee;
    }

    private function createUser(string $roleSlug, string $suffix, ?int $employeeId = null): User
    {
        return User::create([
            'name' => ucfirst($suffix),
            'email' => $suffix.'@example.test',
            'password' => 'password',
            'role_id' => Role::where('slug', $roleSlug)->value('id'),
            'employee_id' => $employeeId,
        ]);
    }
}
