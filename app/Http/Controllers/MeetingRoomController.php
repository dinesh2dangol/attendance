<?php

namespace App\Http\Controllers;

use App\Models\MeetingRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingRoomController extends Controller
{
    public function index()
    {
        $rooms = MeetingRoom::query()->orderBy('name')->get();

        return view('meeting-rooms.manage', compact('rooms'));
    }

    public function create()
    {
        return view('meeting-rooms.form', ['meetingRoom' => new MeetingRoom]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRoom($request);
        MeetingRoom::create($validated);

        return redirect()->route('meeting-rooms.manage')->with('success', 'Meeting room created.');
    }

    public function edit(MeetingRoom $meetingRoom)
    {
        return view('meeting-rooms.form', compact('meetingRoom'));
    }

    public function update(Request $request, MeetingRoom $meetingRoom)
    {
        $meetingRoom->update($this->validateRoom($request, $meetingRoom));

        return redirect()->route('meeting-rooms.manage')->with('success', 'Meeting room updated.');
    }

    private function validateRoom(Request $request, ?MeetingRoom $meetingRoom = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('meeting_rooms', 'name')->ignore($meetingRoom?->id),
            ],
            'location' => ['nullable', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
