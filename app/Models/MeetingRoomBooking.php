<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingRoomBooking extends Model
{
    protected $fillable = [
        'meeting_room_id',
        'user_id',
        'booked_by_name',
        'title',
        'starts_at',
        'ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(MeetingRoom::class, 'meeting_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
