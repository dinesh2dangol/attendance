<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use HasFactory;

    // Existing schema uses leave_id as primary key and stores user_id, leave_date, etc.
    protected $table = 'leaves';
    protected $primaryKey = 'leave_id';
    public $incrementing = true;
    protected $keyType = 'int';
    // Legacy `leaves` table does not have `created_at` / `updated_at` columns
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'leave_date',
        'leave_type',
        'leave_description',
        'approval_status',
    ];

    protected $casts = [
        'leave_date' => 'date',
        'approval_status' => 'integer',
    ];

    public function employee()
    {
        // leaves.user_id maps to employees.user_id in this project
        return $this->belongsTo(Employee::class, 'user_id', 'user_id');
    }
}
