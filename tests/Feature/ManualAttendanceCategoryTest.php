<?php

namespace Tests\Feature;

use App\Support\ManualAttendanceCategories;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManualAttendanceCategoryTest extends TestCase
{
    public function test_manual_attendance_reasons_are_saved_and_grouped_by_date(): void
    {
        Schema::create('ManualAttendance', function (Blueprint $table) {
            $table->string('user_id');
            $table->dateTime('timestamp');
            $table->string('category')->nullable();
            $table->text('remarks')->nullable();
        });

        DB::table('ManualAttendance')->insert([
            [
                'user_id' => 'EMP-001',
                'timestamp' => '2026-03-17 09:00:00',
                'category' => 'WFH',
                'remarks' => 'Worked from home',
            ],
            [
                'user_id' => 'EMP-001',
                'timestamp' => '2026-03-17 17:00:00',
                'category' => 'WFH',
                'remarks' => 'Worked from home',
            ],
            [
                'user_id' => 'EMP-001',
                'timestamp' => '2026-03-18 09:00:00',
                'category' => null,
                'remarks' => 'Legacy manual attendance',
            ],
            [
                'user_id' => 'EMP-002',
                'timestamp' => '2026-03-17 09:00:00',
                'category' => 'Forgot',
                'remarks' => null,
            ],
            [
                'user_id' => 'EMP-001',
                'timestamp' => '2026-03-19 09:00:00',
                'category' => null,
                'remarks' => "Reason: Others\nOld additional note",
            ],
        ]);

        $categories = ManualAttendanceCategories::byDate(
            'EMP-001',
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31')
        );

        $this->assertSame('WFH', $categories->get('2026-03-17'));
        $this->assertNull($categories->get('2026-03-18'));
        $this->assertTrue($categories->has('2026-03-18'));
        $this->assertSame('Others', $categories->get('2026-03-19'));
        $this->assertCount(3, $categories);
    }
}
