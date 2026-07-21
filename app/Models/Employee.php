<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_name',
        'join_date_eng',
        'join_date_npt',
        'photo',
        'status',
        'salary',
        'working_hours',
        'part_time',
        'department_id',
        'gender',
    ];

    protected $casts = [
        'join_date_eng' => 'datetime',
        'status' => 'integer',
        'salary' => 'decimal:2',
        'working_hours' => 'decimal:2',
        'part_time' => 'boolean',
        'department_id' => 'integer',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
