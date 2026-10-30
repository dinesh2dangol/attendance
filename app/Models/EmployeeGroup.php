<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeGroup extends Model
{
    protected $fillable = ['name', 'supervisor_employee_id'];

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_employee_id');
    }

    public function members()
    {
        return $this->belongsToMany(Employee::class, 'employee_group_members');
    }
}
