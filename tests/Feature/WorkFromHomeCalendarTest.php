<?php

namespace Tests\Feature;

use App\Support\WorkFromHomeCalendar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkFromHomeCalendarTest extends TestCase
{
    public function test_it_expands_only_approved_requests_within_the_requested_calendar_range(): void
    {
        Schema::create('work_from_home_requests', function (Blueprint $table) {
            $table->string('user_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
        });

        DB::table('work_from_home_requests')->insert([
            ['user_id' => 'EMP-1', 'start_date' => '2026-03-16', 'end_date' => '2026-03-19', 'status' => 'approved'],
            ['user_id' => 'EMP-1', 'start_date' => '2026-03-17', 'end_date' => '2026-03-18', 'status' => 'pending'],
            ['user_id' => 'EMP-2', 'start_date' => '2026-03-17', 'end_date' => '2026-03-18', 'status' => 'approved'],
        ]);

        $dates = WorkFromHomeCalendar::approvedDatesByDate(
            'EMP-1',
            Carbon::parse('2026-03-17'),
            Carbon::parse('2026-03-18')
        );

        $this->assertSame([
            '2026-03-17' => true,
            '2026-03-18' => true,
        ], $dates->all());
    }
}
