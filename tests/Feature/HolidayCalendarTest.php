<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Support\HolidayCalendar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
{
    public function test_it_returns_future_holidays_and_prefers_department_specific_holidays(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('holiday_name');
            $table->unsignedBigInteger('department_id')->nullable();
        });

        DB::table('holidays')->insert([
            [
                'holiday_date' => '2026-11-09',
                'holiday_name' => 'Global Holiday',
                'department_id' => null,
            ],
            [
                'holiday_date' => '2026-11-09',
                'holiday_name' => 'Department Holiday',
                'department_id' => 12,
            ],
            [
                'holiday_date' => '2026-11-10',
                'holiday_name' => 'Other Department Holiday',
                'department_id' => 13,
            ],
        ]);

        $employee = new Employee(['department_id' => 12]);
        $holidays = HolidayCalendar::byDate(
            $employee,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30')
        );

        $this->assertSame('Department Holiday', $holidays->get('2026-11-09')->holiday_name);
        $this->assertFalse($holidays->has('2026-11-10'));
    }
}
